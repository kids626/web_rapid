<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    /**
     * 需排除的型號清單 目前使用在每月Top10產品
     */
    
     private function hasChinese($str)
    {// 判斷關鍵字是否包含中文字元（用於決定是否以產品名稱進行模糊查詢）
        return preg_match("/[\x{4e00}-\x{9fff}]/u", $str);
    }

    private function excludedProdNos()
    {
        return ['EMA-001', 'EM-002', 'EM-003','point','EMAC-0027','EMAC-0039','EM-001'];
    }

    public function index(Request $request)
    {//目前至今銷售額/數量查詢(請搜尋型號或產品名稱)
        
               
        // 讀取查詢關鍵字（使用者輸入的型號或產品名稱），去除前後空白
        $keyword = trim($request->get('q', ''));
        // 紀錄最近搜尋關鍵字於 session（前 10 筆，最新在前）
        $recentKeywords = (array) $request->session()->get('sales.report.recent_keywords', []);
        if ($keyword !== '') {
            $recentKeywords = array_values(array_unique(array_merge([$keyword], $recentKeywords)));
            if (count($recentKeywords) > 10) {
                $recentKeywords = array_slice($recentKeywords, 0, 10);
            }
            $request->session()->put('sales.report.recent_keywords', $recentKeywords);
        }

        
        
        
        $byDetail = (bool) $request->get('by_detail', false);
        // 若未提供關鍵字：從產品檔取最近更新的前 10 筆型號作為預設清單（以 data_timestamp DESC）
        $topProdNos = [];
        if ($keyword === '') {
            // 建立預設型號清單陣列（未輸入關鍵字時使用）
            // 指定來源資料表：產品主檔 product_m
            $topProdNos = DB::table('product_m')
                // 依最後更新時間由新到舊排序，確保取到最新商品
                ->orderBy('data_timestamp', 'desc')
                // 只取前 10 筆做為預設查詢清單
                ->limit(10)
                // 只擷取欄位 prod_no（型號），回傳為集合
                ->pluck('prod_no')
                // 轉換為 PHP 陣列，方便後續 whereIn 使用
                ->toArray();
        }
        // 讀取查詢的開始日期（YYYY-MM-DD）
        $start = $request->get('start_date');
        // 讀取查詢的結束日期（YYYY-MM-DD）
        $end = $request->get('end_date');

        // 建立彙總查詢
        if ($byDetail) {
            // 依細項商品（order_dd.detail_prod_no）彙總（僅數量）- 僅 join order_m，不 join order_d
            // 贈品數量：以 order_gift_d 依 prod_no 加總，左連接至細項彙總
            // has_subtype：判斷該細項型號所屬之訂單，是否存在任何主檔 order_d 帶有 subtype（依日期條件）
            $subtypeExistsSub = DB::table('order_dd as dd2')
                ->join('order_m as sm', 'sm.ord_no', '=', 'dd2.ord_no')
                ->join('order_d as sd', 'sd.ord_no', '=', 'dd2.ord_no')
                ->select(DB::raw('dd2.detail_prod_no as detail_prod_no'))
                ->whereNotIn('sm.order_kind', ['98','99'])
                ->where('sm.cancel_flag', 0)
                ->whereIn('sm.io_kind', ['1', '2'])
                ->whereNotNull('sd.subtype')
                ->where('sd.subtype', '<>', '')
                ->where('sd.subtype', '<>', '-1')
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('sm.ord_date2', '>=', $start);
                })
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('sm.ord_date2', '<=', $end);
                })
                ->groupBy('dd2.detail_prod_no');
                
            $giftSub = DB::table('order_gift_d as gd')
                ->join('order_m as m2', 'm2.ord_no', '=', 'gd.ord_no')
                ->select('gd.prod_no', DB::raw('SUM(COALESCE(gd.qty,0)) AS gift_qty'))
                ->whereNotIn('m2.order_kind', ['98','99'])
                ->where('m2.cancel_flag', 0)
                ->whereIn('m2.io_kind', ['1', '2'])
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('m2.ord_date2', '>=', $start);
                })
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('m2.ord_date2', '<=', $end);
                })
                ->groupBy('gd.prod_no');

            // 獎勵數量（order_kind=98，依細項商品彙總）
            $rewardSub = DB::table('order_dd as rdd')
                ->join('order_m as rm', 'rm.ord_no', '=', 'rdd.ord_no')
                ->select(DB::raw('rdd.detail_prod_no as prod_no'), DB::raw('SUM(COALESCE(rdd.qty,0)) AS reward_qty'))
                ->where('rm.order_kind', '98')
                ->where('rm.cancel_flag', 0)
                ->whereIn('rm.io_kind', ['1', '2'])
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('rm.ord_date2', '>=', $start);
                })
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('rm.ord_date2', '<=', $end);
                })
                ->groupBy('rdd.detail_prod_no');

            // 未領取獎勵數（moneypv_award.create_order IS NULL）
            $unclaimedAwardSub = DB::table('moneypv_award as aw')
                ->select([
                    DB::raw('aw.prod_no as prod_no'),
                    DB::raw('SUM(COALESCE(aw.qty,0)) AS unclaimed_qty'),
                ])
                ->whereNull('aw.create_order')                
                ->groupBy('aw.prod_no');

            // 銷售數量（不含98/99）彙總
            $salesAgg = DB::table('order_dd as sdd')
                ->join('order_m as sm1', 'sm1.ord_no', '=', 'sdd.ord_no')
                ->select(DB::raw('sdd.detail_prod_no as prod_no'), DB::raw('SUM(COALESCE(sdd.qty,0)) AS total_qty'))
                ->whereNotIn('sm1.order_kind', ['98','99'])
                ->where('sm1.cancel_flag', 0)
                ->whereIn('sm1.io_kind', ['1', '2'])
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('sm1.ord_date2', '>=', $start);
                })
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('sm1.ord_date2', '<=', $end);
                })
                ->groupBy('sdd.detail_prod_no');

            // 鍵集合：非98/99 銷售鍵 UNION 98 獎勵鍵
            $keysNon98 = DB::table('order_dd as dd0')
                ->join('order_m as m0', 'm0.ord_no', '=', 'dd0.ord_no')
                ->whereNotIn('m0.order_kind', ['98','99'])
                ->where('m0.cancel_flag', 0)
                ->whereIn('m0.io_kind', ['1', '2'])
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('m0.ord_date2', '>=', $start);
                })
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('m0.ord_date2', '<=', $end);
                })
                ->select(DB::raw('dd0.detail_prod_no as detail_prod_no'))
                ->groupBy('dd0.detail_prod_no');

            $keys98 = DB::table('order_dd as ddr')
                ->join('order_m as mr', 'mr.ord_no', '=', 'ddr.ord_no')
                ->where('mr.order_kind', '98')
                ->where('mr.cancel_flag', 0)
                ->whereIn('mr.io_kind', ['1', '2'])
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('mr.ord_date2', '>=', $start);
                })
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('mr.ord_date2', '<=', $end);
                })
                ->select(DB::raw('ddr.detail_prod_no as detail_prod_no'))
                ->groupBy('ddr.detail_prod_no');

            // 只有未領取獎勵也能出現：加入 moneypv_award 的型號
            $keysUnclaimed = DB::table('moneypv_award as akw')
                ->select(DB::raw('akw.prod_no as detail_prod_no'))
                ->whereNull('akw.create_order')                
                ->groupBy('akw.prod_no');

            $keysUnion = $keysNon98->union($keys98)->union($keysUnclaimed);

            $query = DB::query()
                ->fromSub($keysUnion, 'k')
                ->leftJoin('product_m as pd', 'pd.prod_no', '=', 'k.detail_prod_no')
                ->leftJoinSub($salesAgg, 'sale', function ($j) {
                    $j->on('sale.prod_no', '=', 'k.detail_prod_no');
                })
                ->leftJoinSub($giftSub, 'gft', function ($j) {
                    $j->on('gft.prod_no', '=', 'k.detail_prod_no');
                })
                ->leftJoinSub($rewardSub, 'rwd', function ($j) {
                    $j->on('rwd.prod_no', '=', 'k.detail_prod_no');
                })
                ->leftJoinSub($unclaimedAwardSub, 'urw', function ($j) {
                    $j->on('urw.prod_no', '=', 'k.detail_prod_no');
                })
                ->leftJoinSub($subtypeExistsSub, 'st', function ($j) {
                    $j->on('st.detail_prod_no', '=', 'k.detail_prod_no');
                })
                ->select([
                    DB::raw('k.detail_prod_no as prod_no'),
                    DB::raw('COALESCE(pd.prod_name, "") as prod_name'),
                    DB::raw('COALESCE(sale.total_qty, 0) as total_qty'),
                    DB::raw('COALESCE(gft.gift_qty, 0) as gift_qty'),
                    DB::raw('COALESCE(rwd.reward_qty, 0) as reward_qty'),
                    DB::raw('COALESCE(urw.unclaimed_qty, 0) as unclaimed_qty'),
                    DB::raw('COALESCE(sale.total_qty,0) + COALESCE(gft.gift_qty,0) + COALESCE(rwd.reward_qty,0) + COALESCE(urw.unclaimed_qty,0) as total_qty_with_gift'),
                    DB::raw('0 as total_amount'),
                    DB::raw('CASE WHEN st.detail_prod_no IS NULL THEN 0 ELSE 1 END as has_subtype'),
                ])
                ->when($keyword !== '', function ($q) use ($keyword) {
                    $q->where(function ($qq) use ($keyword) {
                        if ($this->hasChinese($keyword)) {
                            $qq->orWhere('pd.prod_name', 'like', "%{$keyword}%");
                        } else {
                            $qq->where('k.detail_prod_no', 'like', "%{$keyword}%");
                        }
                    });
                })
                ->groupBy('k.detail_prod_no', 'pd.prod_name', 'sale.total_qty', 'gft.gift_qty', 'rwd.reward_qty', 'urw.unclaimed_qty', 'st.detail_prod_no')
                ->orderBy('k.detail_prod_no');
        } else {
            // 依主商品（order_d.prod_no）彙總
            $query = DB::table('order_d as d')
                // 關聯產品檔，取得型號與產品名稱
                ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
                // 關聯訂單主檔，用於日期與訂單狀態過濾
                ->join('order_m as m', 'm.ord_no', '=', 'd.ord_no')
                // 選取要顯示／彙總的欄位
                ->select([
                    'p.prod_no',
                    'p.prod_name',
                    // 銷售數量：合計明細數量，空值以 0 代入
                    DB::raw('SUM(COALESCE(d.qty, 0)) as total_qty'),
                    // 銷售額：優先採用明細小計 sub_money；若無則以 qty*price 計算
                    DB::raw('SUM(COALESCE(d.sub_money, COALESCE(d.qty,0) * COALESCE(d.price,0))) as total_amount'),
                    DB::raw("MAX(CASE WHEN COALESCE(d.subtype,'') <> '' AND d.subtype <> '-1' THEN 1 ELSE 0 END) as has_subtype"),
                ])
                // 排除非銷售訂單
                ->whereNotIn('m.order_kind', ['98','99'])
                // 排除已取消訂單
                ->where('m.cancel_flag', 0)
                // 僅統計 io_kind 在 1、2 範圍（銷出/退貨等業務定義）
                ->whereIn('m.io_kind', ['1', '2'])
                // 若有開始日期：以 m.ord_date2 作為起始過濾（含當日）
                ->when($start, function ($q) use ($start) {
                    $q->whereDate('m.ord_date2', '>=', $start);
                })
                // 若有結束日期：以 m.ord_date2 作為結束過濾（含當日）
                ->when($end, function ($q) use ($end) {
                    $q->whereDate('m.ord_date2', '<=', $end);
                })
                //--------------------------------
                // 注意這裡的判斷-若無關鍵字且無日期篩選，帶入前 10 個型號做為 whereIn 篩選
                ->when($keyword === '' && !$start && !$end && !empty($topProdNos), function ($q) use ($topProdNos) {
                    $q->whereIn('p.prod_no', $topProdNos);
                })
                // 若提供關鍵字：對型號或產品名稱進行模糊比對
                ->when($keyword !== '', function ($q) use ($keyword) {
                    $q->where(function ($qq) use ($keyword) {
                        if ($this->hasChinese($keyword)) {
                            $qq->orWhere('p.prod_name', 'like', "%{$keyword}%");
                        }
                        else
                        {
                            $qq->where('p.prod_no', 'like', "%{$keyword}%");
                        }
                    });
                })
                // 依型號＋產品名稱進行彙總（避免名稱相同但型號不同的紀錄被合併）
                ->groupBy('p.prod_no', 'p.prod_name')
                // 以型號排序，讓結果穩定
                ->orderBy('p.prod_no');
        }

        // 若有提供日期區間：回傳完整結果；否則預設僅取前 5 筆示例
        //ddSql($query);
        $rows = ($start || $end) ? $query->get() : $query->limit(5)->get();

        // 準備「今天 / 昨天」產品銷售量圖表資料
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // 熱門關鍵字：依產品主檔最近更新順序
        $hotList = DB::table('product_m')
            ->select(['prod_no', 'prod_name'])
            ->orderBy('data_timestamp', 'desc')
            ->limit(5)
            ->get();
        $hotKeywords = [];
        foreach ($hotList as $r) {
            $prodNo = (string)($r->prod_no ?? '');
            $prodName = trim((string)($r->prod_name ?? ''));
            $label = $prodNo !== '' && $prodName !== '' ? ($prodNo . ' (' . $prodName . ')') : ($prodNo ?: $prodName);
            if ($label === '') continue;
            $hotKeywords[] = ['label' => $label, 'value' => $prodNo !== '' ? $prodNo : $prodName];
        }

        $buildDailyChart = function(string $targetDate) use ($byDetail) {
            $excluded = $this->excludedProdNos();
            if ($byDetail) {
                // 贈品數量（依細項商品、單日）
                $giftSub = DB::table('order_gift_d as gd')
                    ->join('order_m as m2', 'm2.ord_no', '=', 'gd.ord_no')
                    ->select('gd.prod_no', DB::raw('SUM(COALESCE(gd.qty,0)) AS gift_qty'))
                    ->whereNotIn('m2.order_kind', ['98','99'])
                    ->where('m2.cancel_flag', 0)
                    ->whereIn('m2.io_kind', ['1', '2'])
                    ->whereDate('m2.ord_date2', '=', $targetDate)
                    ->groupBy('gd.prod_no');

                $q = DB::table('order_dd as dd')
                    ->join('order_m as m', 'm.ord_no', '=', 'dd.ord_no')
                    ->leftJoin('product_m as pd', 'pd.prod_no', '=', 'dd.detail_prod_no')
                    ->leftJoinSub($giftSub, 'gft', function ($j) {
                        $j->on('gft.prod_no', '=', 'dd.detail_prod_no');
                    })
                    ->select([
                        DB::raw('dd.detail_prod_no as prod_no'),
                        DB::raw('COALESCE(pd.prod_name, "") as prod_name'),
                        DB::raw('SUM(COALESCE(dd.qty,0)) as qty'),
                        DB::raw('COALESCE(MAX(gft.gift_qty), 0) as gift_qty'),
                    ])
                    ->whereNotIn('m.order_kind', ['98','99'])
                    ->where('m.cancel_flag', 0)
                    ->whereIn('m.io_kind', ['1', '2'])
                    ->whereNotIn('dd.detail_prod_no', $excluded)
                    ->whereDate('m.ord_date2', '=', $targetDate)
                    ->groupBy('dd.detail_prod_no', 'pd.prod_name')
                    ->orderBy('dd.detail_prod_no');
            } else {
                $q = DB::table('order_m as m')
                    ->join('order_d as d', 'd.ord_no', '=', 'm.ord_no')
                    ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
                    ->select([
                        'p.prod_no',
                        'p.prod_name',
                        DB::raw('SUM(COALESCE(d.qty,0)) as qty'),
                    ])
                    ->whereNotIn('m.order_kind', ['98','99'])
                    ->where('m.cancel_flag', 0)
                    ->whereIn('m.io_kind', ['1', '2'])
                    ->whereNotIn('p.prod_no', $excluded)
                    ->whereDate('m.ord_date2', '=', $targetDate)
                    ->groupBy('p.prod_no', 'p.prod_name')
                    ->orderBy('p.prod_no');
            }

            $list = $q->get();

            $agg = [];
            foreach ($list as $r) {
                $prodNo = (string) ($r->prod_no ?? '');
                if ($prodNo === '') continue;
                if (!isset($agg[$prodNo])) {
                    $agg[$prodNo] = [
                        'prod_no' => $prodNo,
                        'prod_name' => (string) ($r->prod_name ?? ''),
                        'qty' => 0.0,
                    ];
                }
                $baseQty = (float) ($r->qty ?? 0);
                $giftQty = isset($r->gift_qty) ? (float) $r->gift_qty : 0.0;
                $agg[$prodNo]['qty'] += ($byDetail ? ($baseQty + $giftQty) : $baseQty);
            }

            $arr = array_values($agg);
            usort($arr, function($a, $b){ return $b['qty'] <=> $a['qty']; });
            $top = $arr; // 顯示全部產品

            $labels = [];
            $data = [];
            $rank = 1;
            foreach ($top as $it) {
                $labelBase = trim($it['prod_no'] . ($it['prod_name'] !== '' ? ('(' . $it['prod_name'] . ')') : ''));
                $labels[] = $rank . '. ' . ($labelBase !== '' ? $labelBase : $it['prod_no']);
                $data[] = (float) $it['qty'];
                $rank++;
            }

            return [$labels, $data];
        };

        list($chartTodayLabels, $chartTodayData) = $buildDailyChart($today);
        list($chartYesterdayLabels, $chartYesterdayData) = $buildDailyChart($yesterday);

        // 將結果與查詢條件回傳到 Blade 視圖
        return view('sales.report', [
            'rows' => $rows,
            'keyword' => $keyword,
            'start' => $start,
            'end' => $end,
            'autoProdNos' => $topProdNos,
            'byDetail' => $byDetail,
            'chartTodayLabels' => $chartTodayLabels,
            'chartTodayData' => $chartTodayData,
            'chartYesterdayLabels' => $chartYesterdayLabels,
            'chartYesterdayData' => $chartYesterdayData,
            'todayDate' => $today,
            'yesterdayDate' => $yesterday,
            'hotKeywords' => $hotKeywords,
            'recentKeywords' => $recentKeywords,
        ]);
    }

    public function subtypeBreakdown(Request $request)
    {
        $prodNo = trim($request->get('prod_no', ''));
        if ($prodNo === '') {
            return response()->json(['ok' => false, 'message' => '缺少型號'], 400);
        }
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        
        // 這一行 SQL 的意思如下：
             // 1. 從 order_d 資料表選取 ord_no（訂單編號）、prod_no（產品型號）、qty（數量）。
             // 2. 對 subtype 欄位（子型號資訊）做字串處理：
             //    - 先用 TRIM(BOTH '@' FROM d.subtype) 去除前後的 @ 字元（避免多餘分隔符號）。
             //    - 再用 REPLACE(..., '@@', '@') 把連續的 @@ 取代成單一 @，確保分隔符號正確。
             //    - 最後命名為 cleaned，表示已清理過的子型號字串。
             // 3. 最後多選一個常數 1，命名為 idx，作為遞迴 CTE 的初始索引（用來分割子型號字串）。
        $sql = "WITH RECURSIVE tok AS (\n"             
             . "  SELECT d.ord_no, d.prod_no, d.qty, REPLACE(TRIM(BOTH '@' FROM d.subtype),'@@','@') AS cleaned, 1 AS idx\n"
             . "  FROM order_d d\n"
             . "  JOIN order_m m ON m.ord_no = d.ord_no\n"
             . "  WHERE d.subtype IS NOT NULL AND d.subtype <> '' AND d.subtype <> '-1'\n"
             . "    AND (d.prod_no = ? OR EXISTS (SELECT 1 FROM order_dd dd WHERE dd.ord_no = d.ord_no AND dd.detail_prod_no = ?))\n"
             . "    AND m.order_kind NOT IN (98,99) AND m.cancel_flag = 0 AND m.io_kind IN (1,2)";
        // 設定 SQL 綁定參數，初始只放入產品型號（兩次：主檔 prod_no 或細項 detail_prod_no）
        $bindings = [$prodNo, $prodNo];

        // 如果有指定開始或結束日期，則動態組合 SQL 條件
        if (!empty($start) || !empty($end)) {
            // 為了方便後續 AND 條件拼接，先加一個恆成立的 1=1
            $sql .= " AND (1=1";
            // 如果有開始日期，則加入訂單日期或建立日期大於等於開始日的條件
            if (!empty($start)) {
                $sql .= " AND (DATE(m.ord_date2) >= ? OR DATE(m.create_date) >= ?)";
                // 將開始日期參數加入綁定陣列（兩次，分別對應 ord_date2 與 create_date）
                $bindings[] = $start;
                $bindings[] = $start;
            }
            // 如果有結束日期，則加入訂單日期或建立日期小於等於結束日的條件
            if (!empty($end)) {
                $sql .= " AND (DATE(m.ord_date2) <= ? OR DATE(m.create_date) <= ?)";
                // 將結束日期參數加入綁定陣列（兩次，分別對應 ord_date2 與 create_date）
                $bindings[] = $end;
                $bindings[] = $end;
            }
            // 關閉 AND 條件的括號
            $sql .= ")";
        }
        $sql .= "\n"
             . "  UNION ALL\n"
             . "  SELECT ord_no, prod_no, qty, cleaned, idx + 1\n"
             . "  FROM tok\n"
             . "  WHERE idx < 1 + LENGTH(cleaned) - LENGTH(REPLACE(cleaned,'@',''))\n"
             . "), parsed AS (\n"
             . "  SELECT ord_no, prod_no, qty, SUBSTRING_INDEX(SUBSTRING_INDEX(cleaned,'@', idx),'@',-1) AS token\n"
             . "  FROM tok\n"
             . "), split AS (\n"
             . "  SELECT ord_no, prod_no, qty, CAST(SUBSTRING_INDEX(token,'_',1) AS UNSIGNED) AS sn, CAST(SUBSTRING_INDEX(token,'_',-1) AS UNSIGNED) AS sub_qty\n"
             . "  FROM parsed WHERE token <> ''\n"
             . ")\n"
             . "SELECT s.sn, p.type_name, SUM(s.sub_qty * s.qty) AS new_qty\n"
             . "FROM split s JOIN prod_subtype p ON p.sn = s.sn\n"
             . "GROUP BY s.sn, p.type_name\n"
             . "ORDER BY new_qty DESC";
        //echo $sql;
        $rows = DB::select($sql, $bindings);
        if (strtolower((string)$request->get('format')) === 'json' || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'prodNo' => $prodNo,
                'start' => $start,
                'end' => $end,
                'rows' => $rows,
            ]);
        }
        return view('sales.subtype_breakdown', [
            'prodNo' => $prodNo,
            'rows' => $rows,
        ]);
    }

    public function monthly(Request $request)
    {//每月銷售額彙總
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        if (!$start && !$end) {
            $start = date('Y-01-01');
            $end = date('Y-m-d');
        }

        $query = DB::table('order_m as m')
            ->join('order_d as d', 'd.ord_no', '=', 'm.ord_no')
            ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
            ->select([
                DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                DB::raw('SUM(COALESCE(d.qty,0)) AS total_qty'),
                DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.price,0)) AS total_amount'),
                DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.unit_pv,0)) AS total_pv'),
                DB::raw('COUNT(DISTINCT m.ord_no) AS order_count'),
            ])
            ->whereNotIn('m.order_kind', ['98','99'])
            ->where('m.cancel_flag', 0)
            ->whereIn('m.io_kind', ['1', '2'])
            ->when($start, function ($q) use ($start) {
                $q->where('m.create_date', '>=', $start . ' 00:00:00');
            })
            ->when($end, function ($q) use ($end) {
                $q->where('m.create_date', '<=', $end . ' 23:59:59');
            })
            ->groupBy(DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"))
            ->orderBy('ym');

        $rows = ($start || $end) ? $query->get() : $query->limit(5)->get();

        // 準備今年月份列表
        $labels = [];
        $cursor = strtotime(substr($start, 0, 10));
        $endTs = strtotime(substr($end, 0, 10));
        while ($cursor <= $endTs) {
            $labels[] = date('Y-m', $cursor);//圖形下面的字
            $cursor = strtotime('+1 month', $cursor);
        }

        // 今年金額映射
        $currMap = [];
        foreach ($rows as $r) {
            $currMap[$r->ym] = (float) $r->total_amount;
        }

        // 去年同期間
        $prevStart = date('Y-m-d', strtotime('-1 year', strtotime($start)));
        $prevEnd = date('Y-m-d', strtotime('-1 year', strtotime($end)));

        $prevRows = DB::table('order_m as m')
            ->join('order_d as d', 'd.ord_no', '=', 'm.ord_no')
            ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
            ->select([
                DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.price,0)) AS total_amount'),
            ])
            ->whereNotIn('m.order_kind', ['98','99'])
            ->where('m.cancel_flag', 0)
            ->whereIn('m.io_kind', ['1', '2'])
            ->where('m.create_date', '>=', $prevStart . ' 00:00:00')
            ->where('m.create_date', '<=', $prevEnd . ' 23:59:59')
            ->groupBy(DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"))
            ->orderBy('ym')
            ->get();

        // 去年金額映射（鍵為去年年月）
        $prevMapRaw = [];
        foreach ($prevRows as $pr) {
            $prevMapRaw[$pr->ym] = (float) $pr->total_amount;
        }

        // 產出圖表資料：以今年月份 labels 對齊，去年資料取該月份減一年的值
        $chartCurrent = [];
        $chartPrev = [];
        foreach ($labels as $ym) {
            $chartCurrent[] = isset($currMap[$ym]) ? (float) $currMap[$ym] : 0.0;
            $prevYm = date('Y-m', strtotime('-1 year', strtotime($ym . '-01')));
            $chartPrev[] = isset($prevMapRaw[$prevYm]) ? (float) $prevMapRaw[$prevYm] : 0.0;
        }

        return view('sales.monthly', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'chartLabels' => $labels,
            'chartCurrent' => $chartCurrent,
            'chartPrev' => $chartPrev,
        ]);
    }

    public function yearly(Request $request)
    {
        //年度銷售額彙總
        // 讀取年度區間，若未提供則預設顯示近三年（含今年）
        $startYear = (int) ($request->get('start_year') ?: 2019);
        $endYear = (int) ($request->get('end_year') ?: date('Y'));

        // 轉換為日期區間：起始 >= yyyy-01-01 00:00:00，結束 < (endYear+1)-01-01 00:00:00
        $startDate = $startYear . '-01-01 00:00:00';
        $endExclusive = ($endYear + 1) . '-01-01 00:00:00';

        $rows = DB::table('order_m as m')
            ->join('order_d as d', 'd.ord_no', '=', 'm.ord_no')
            ->select([
                DB::raw('YEAR(m.create_date) AS y'),
                DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.price,0)) AS total_amount'),
            ])
            ->whereNotIn('m.order_kind', ['98','99'])
            ->where('m.cancel_flag', 0)
            ->whereIn('m.io_kind', ['1', '2'])
            ->where('m.create_date', '>=', $startDate)
            ->where('m.create_date', '<', $endExclusive)
            ->groupBy(DB::raw('YEAR(m.create_date)'))
            ->orderBy('y')
            ->get();

        // 準備圖表資料
        $labels = [];
        $data = [];
        foreach ($rows as $r) {
            $labels[] = (string) $r->y;
            $data[] = (float) $r->total_amount;
        }

        return view('sales.yearly', [
            'rows' => $rows,
            'startYear' => $startYear,
            'endYear' => $endYear,
            'chartLabels' => $labels,
            'chartData' => $data,
        ]);
    }

    public function topMonthly(Request $request)
    {
        //每月Top10產品
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        $byDetail = (bool) $request->get('by_detail', false);
        $isChinese = false; // 此頁暫不提供關鍵字；若後續加入可沿用判斷
        $excluded = $this->excludedProdNos();

        if (!$start && !$end) {
            $start = date('Y-01-01');
            $end = date('Y-m-d');
        }

        if ($byDetail) {
            // 依細項商品：order_dd + order_m（不 join order_d）
            $query = DB::table('order_dd as dd')
                ->join('order_m as m', 'm.ord_no', '=', 'dd.ord_no')
                ->leftJoin('product_m as pd', 'pd.prod_no', '=', 'dd.detail_prod_no')
                ->select([
                    DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                    DB::raw('dd.detail_prod_no as prod_no'),
                    DB::raw('COALESCE(pd.prod_name, "") as prod_name'),
                    DB::raw('SUM(COALESCE(dd.qty,0)) AS total_qty'),
                    DB::raw('0 AS total_amount'),
                ])
                ->whereNotIn('m.order_kind', ['98','99'])
                ->where('m.cancel_flag', 0)
                ->whereIn('m.io_kind', ['1', '2'])
                ->whereNotIn('dd.detail_prod_no', $excluded)
                ->when($start, function ($q) use ($start) {
                    $q->where('m.create_date', '>=', $start . ' 00:00:00');
                })
                ->when($end, function ($q) use ($end) {
                    $q->where('m.create_date', '<=', $end . ' 23:59:59');
                })
                ->groupBy(DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"), 'dd.detail_prod_no', 'pd.prod_name')
                ->orderBy('ym')
                ->orderByRaw('SUM(COALESCE(dd.qty,0)) DESC');
        } else {
            $query = DB::table('order_m as m')
                ->join('order_d as d', 'd.ord_no', '=', 'm.ord_no')
                ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
                ->select([
                    DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                    'p.prod_no',
                    'p.prod_name',
                    DB::raw('SUM(COALESCE(d.qty,0)) AS total_qty'),
                    DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.price,0)) AS total_amount'),
                ])
                ->whereNotIn('m.order_kind', ['98','99'])
                ->where('m.cancel_flag', 0)
                ->whereIn('m.io_kind', ['1', '2'])
                ->whereNotIn('p.prod_no', $excluded)
                ->when($start, function ($q) use ($start) {
                    $q->where('m.create_date', '>=', $start . ' 00:00:00');
                })
                ->when($end, function ($q) use ($end) {
                    $q->where('m.create_date', '<=', $end . ' 23:59:59');
                })
                ->groupBy(DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"), 'p.prod_no', 'p.prod_name')
                ->orderBy('ym')
                ->orderByRaw('SUM(COALESCE(d.qty,0)) DESC');
        }

        $rows = $query->get();

        // 依月份分組並取各月前 10 名
        $tops = [];
        foreach ($rows as $r) {
            $ym = $r->ym;
            if (!isset($tops[$ym])) {
                $tops[$ym] = [];
            }
            if (count($tops[$ym]) < 10) {
                $tops[$ym][] = $r;
            }
        }

        // 為顯示加入排名
        $ranked = [];
        foreach ($tops as $ym => $list) {
            $rank = 1;
            foreach ($list as $item) {
                $ranked[] = (object) [
                    'ym' => $ym,
                    'rank' => $rank++,
                    'prod_no' => $item->prod_no,
                    'prod_name' => $item->prod_name,
                    'total_qty' => $item->total_qty,
                    'total_amount' => $item->total_amount,
                ];
            }
        }

        // 準備每月圖表資料（Top10 數量）
        $chartByMonth = [];
        foreach ($tops as $ym => $list) {
            $labels = [];
            $data = [];
            foreach ($list as $row) {
                $labels[] = $row->prod_no.'('.$row->prod_name.')';
                $data[] = (int) $row->total_qty;
            }
            $chartByMonth[$ym] = [
                'labels' => $labels,
                'data' => $data,
            ];
        }

        return view('sales.top_monthly', [
            'rows' => $ranked,
            'start' => $start,
            'end' => $end,
            'byDetail' => $byDetail,
            'chartByMonth' => $chartByMonth,
        ]);
    }

    public function tickets(Request $request)
    {
        // 讀取欲使用的日期欄位參數（預設為 create_time，可選 use_date 或 create_time）
        $dateField = $request->get('date_field', 'create_time'); // use_date | create_time
        // 讀取查詢的開始與結束日期（格式預期 YYYY-MM-DD）
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        // 讀取查詢關鍵字（用於型號/產品名稱的模糊比對）
        $keyword = trim($request->get('q', ''));
        // 讀取店家編號（下拉值 fac_no，顯示為店家名稱）
        $facNo = trim($request->get('fac_no', ''));
        // 白名單限制日期欄位，避免被任意指定造成 SQL 注入風險
        $dateField = $dateField === 'create_time' ? 'create_time' : 'use_date';

        // 以所選日期欄位建立 STR_TO_DATE 表達式，便於做區間與年月彙總
        $dateExpr = $dateField === 'use_date'
            ? "FROM_UNIXTIME(t.use_date)"
            : "STR_TO_DATE(t.create_time, '%Y-%m-%d %H:%i:%s')";

        // 建立查詢：自 ticket t 出發，連接訂單主檔 m 與產品檔 p
        $query = DB::table('ticket as t')
            // 與訂單主檔關聯，用於套用訂單層的篩選（order_kind / cancel_flag / io_kind）
            ->join('order_m as m', 'm.ord_no', '=', 't.ord_no')
            // 關聯產品檔以取得型號與名稱（有些票券可能沒有對應產品，故使用 left join）
            ->leftJoin('product_m as p', 'p.prod_no', '=', 't.prod_no')
            // 選取彙總所需欄位
            ->select([
                // ym：以票券日期欄位轉為 年-月 字串，作為彙總鍵
                DB::raw("DATE_FORMAT($dateExpr, '%Y-%m') AS ym"),
                // 產品識別：型號與名稱（用於前端表格展示與分組）
                'p.prod_no',
                'p.prod_name',
                // 票券總數
                DB::raw('COUNT(*) AS ticket_count'),
                // 狀態彙總：已用、未用、取消/失敗
                DB::raw("SUM(CASE WHEN t.status='1' THEN 1 ELSE 0 END) AS used_count"),
                DB::raw("SUM(CASE WHEN t.status='0' THEN 1 ELSE 0 END) AS not_used_count"),
                DB::raw("SUM(CASE WHEN t.status='2' THEN 1 ELSE 0 END) AS cancel_count"),
                // 訂單數：同一訂單不重覆計數
                DB::raw('COUNT(DISTINCT m.ord_no) AS order_count'),
            ])
            // 訂單狀態過濾：排除非銷售/取消，僅統計銷出與退貨類別 1、2
            ->whereNotIn('m.order_kind', ['98','99'])
            //->where('m.cancel_flag', 0)
            ->whereIn('m.io_kind', ['1', '2'])
            // 店家過濾（依票券核銷店家編號）
            ->when($facNo !== '', function ($q) use ($facNo) {
                $q->where('t.check_fac_no', $facNo);
            })
            // 型號/產品名稱模糊查詢（q 參數）
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($qq) use ($keyword) {
                    $qq->where('p.prod_no', 'like', "%{$keyword}%")
                       ->orWhere('p.prod_name', 'like', "%{$keyword}%");
                });
            })
            // 日期區間過濾（以選定的票券日期欄位為準）
            ->when($start, function ($q) use ($dateExpr, $start) {
                $q->whereRaw("$dateExpr >= ?", [$start . ' 00:00:00']);
            })
            ->when($end, function ($q) use ($dateExpr, $end) {
                $q->whereRaw("$dateExpr <= ?", [$end . ' 23:59:59']);
            })
            // 依「月份 + 產品」彙總
            ->groupBy(DB::raw("DATE_FORMAT($dateExpr, '%Y-%m')"), 'p.prod_no', 'p.prod_name')
            // 先按月份排序，再按型號排序
            ->orderBy('ym')
            ->orderBy('p.prod_no');
        // 偵錯：輸出最終 SQL（不含綁定值）並中止執行
        //ddSql($query);
        // 執行查詢：目前未提供日期也會取完整結果（可視需求改為 limit）
        $rows = ($start || $end) ? $query->get() : $query->get();

        // 店家下拉清單
        $stores = DB::table('store_tb')
            ->select(['fac_no', 'store_name'])
            ->orderBy('store_name')
            ->get();

        return view('sales.tickets', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'dateField' => $dateField,
            'keyword' => $keyword,
            'facNo' => $facNo,
            'stores' => $stores,
        ]);
    }

    public function productMonthly(Request $request)
    {
        $keyword = trim($request->get('q', ''));
        $byDetail = (bool) $request->get('by_detail', false);        
        $excluded = $this->excludedProdNos();

        $start = $request->get('start_date');
        $end = $request->get('end_date');

        if (!$start && !$end) {
            $start = date('Y-01-01');
            $end = date('Y-m-d');
        }

        if ($byDetail) {
            // 依細項商品（order_dd.detail_prod_no）彙總數量 - 僅使用 order_dd + order_m
            $query = DB::table('order_dd as dd')
                ->join('order_m as m', 'm.ord_no', '=', 'dd.ord_no')
                ->leftJoin('product_m as pd', 'pd.prod_no', '=', 'dd.detail_prod_no')
                ->select([
                    DB::raw('dd.detail_prod_no as prod_no'),
                    DB::raw('COALESCE(pd.prod_name, "") as prod_name'),
                    DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                    DB::raw('SUM(COALESCE(dd.qty,0)) AS total_qty'),
                    DB::raw('0 AS total_amount'),
                    DB::raw('0 AS total_pv'),
                ])
                ->whereNotIn('m.order_kind', ['98','99'])
                ->where('m.cancel_flag', 0)
                ->whereIn('m.io_kind', ['1', '2'])
                ->whereNotIn('dd.detail_prod_no', $excluded)
                ->when($start, function ($q) use ($start) {
                    $q->where('m.create_date', '>=', $start . ' 00:00:00');
                })
                ->when($end, function ($q) use ($end) {
                    $q->where('m.create_date', '<=', $end . ' 23:59:59');
                })
                ->when($keyword !== '', function ($q) use ($keyword) {
                    $q->where(function ($qq) use ($keyword) {                        
                        if ($this->hasChinese($keyword)) {
                            $qq->orWhere('pd.prod_name', 'like', "%{$keyword}%");
                        }
                        else
                        {
                            $qq->where('dd.detail_prod_no', 'like', "%{$keyword}%");
                        }
                    });
                })
                ->groupBy(DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"), 'dd.detail_prod_no', 'pd.prod_name')
                ->orderBy('ym')
                ->orderBy('dd.detail_prod_no');
        } else {
            // 依主商品（order_d.prod_no）彙總
            $query = DB::table('order_m as m')
                ->join('order_d as d', 'd.ord_no', '=', 'm.ord_no')
                ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
                ->select([
                    'p.prod_no',
                    'p.prod_name',
                    DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                    DB::raw('SUM(COALESCE(d.qty,0)) AS total_qty'),
                    DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.price,0)) AS total_amount'),
                    DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.unit_pv,0)) AS total_pv'),
                ])
                ->whereNotIn('m.order_kind', ['98','99'])
                ->where('m.cancel_flag', 0)
                ->whereIn('m.io_kind', ['1', '2'])
                ->whereNotIn('p.prod_no', $excluded)
                ->when($start, function ($q) use ($start) {
                    $q->where('m.create_date', '>=', $start . ' 00:00:00');
                })
                ->when($end, function ($q) use ($end) {
                    $q->where('m.create_date', '<=', $end . ' 23:59:59');
                })
                ->when($keyword !== '', function ($q) use ($keyword) {
                    $q->where(function ($qq) use ($keyword) {
                        if ($this->hasChinese($keyword)) {
                            $qq->orWhere('p.prod_name', 'like', "%{$keyword}%");
                        }
                        else
                        {
                            $qq->where('p.prod_no', 'like', "%{$keyword}%");
                        }
                    });
                })
                ->groupBy('p.prod_no', 'p.prod_name', DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"))
                ->orderBy('ym')
                ->orderBy('p.prod_no');
        }

        $rows = $query->get();

        // 準備圖表資料（目前區間內產品銷售數量橫條圖）— 取前 10 名（依數量）
        $sumByProd = [];
        foreach ($rows as $r) {
            $prodNo = (string) ($r->prod_no ?? '');
            if ($prodNo === '') continue;
            if (!isset($sumByProd[$prodNo])) {
                $sumByProd[$prodNo] = [
                    'prod_no' => $prodNo,
                    'prod_name' => (string) ($r->prod_name ?? ''),
                    'qty' => 0.0,
                ];
            }
            $sumByProd[$prodNo]['qty'] += (float) ($r->total_qty ?? 0);
        }
        // 轉為陣列並依數量由大到小排序
        $aggList = array_values($sumByProd);
        usort($aggList, function($a, $b){ return $b['qty'] <=> $a['qty']; });
        // 取前 20 筆
        $topList = array_slice($aggList, 0, 20);
        $chartProdLabels = [];
        $chartProdData = [];
        $rank = 1;
        foreach ($topList as $it) {
            $labelBase = trim($it['prod_no'] . ($it['prod_name'] !== '' ? ('(' . $it['prod_name'] . ')') : ''));
            $labelBase = $labelBase !== '' ? $labelBase : $it['prod_no'];
            $chartProdLabels[] = $rank . '. ' . $labelBase;
            $chartProdData[] = (float) $it['qty'];
            $rank++;
        }

        return view('sales.product_monthly', [
            'rows' => $rows,
            'keyword' => $keyword,
            'start' => $start,
            'end' => $end,
            'byDetail' => $byDetail,
            'chartProdLabels' => $chartProdLabels,
            'chartProdData' => $chartProdData,
        ]);
    }

    public function ticketUsedList(Request $request)
    {//已用票券清單
        $ym = $request->get('ym');
        $prodNo = $request->get('prod_no');
        $facNo = $request->get('fac_no');
        $sd = $request->get('start_date');
        $ed = $request->get('end_date');
        $dateField = $request->get('date_field', 'use_date');

        if (!$prodNo) {
            return response()->json(['data' => []]);
        }

        // 區間：若提供 start/end 則優先；否則回退 ym 月份
        if (!empty($sd) || !empty($ed)) {
            if (empty($sd) && !empty($ym)) {
                $sd = $ym . '-01';
            }
            if (empty($ed) && !empty($ym)) {
                $ed = date('Y-m-t', strtotime($ym . '-01'));
            }
            // 依 date_field 轉換區間：use_date 用 Unix 秒，create_time 用日期字串
            if ($dateField === 'create_time') {
                $startStr = !empty($sd) ? ($sd . ' 00:00:00') : '1970-01-01 00:00:00';
                $endStr = !empty($ed) ? ($ed . ' 23:59:59') : date('Y-m-d 23:59:59');
            } else {
                $startTs = !empty($sd) ? strtotime($sd . ' 00:00:00') : strtotime('1970-01-01 00:00:00');
                $endTs = !empty($ed) ? strtotime($ed . ' 23:59:59') : time();
            }
        } else {
            if (empty($ym)) {
                return response()->json(['data' => []]);
            }
            $start = $ym . '-01 00:00:00';
            $end = date('Y-m-t 23:59:59', strtotime($ym . '-01'));
            if ($dateField === 'create_time') {
                $startStr = $start;
                $endStr = $end;
            } else {
                $startTs = strtotime($start);
                $endTs = strtotime($end);
            }
        }

        $query = DB::table('ticket as t')
            ->join('order_m as m', 'm.ord_no', '=', 't.ord_no')
            ->leftJoin('store_tb as s', 's.fac_no', '=', 't.check_fac_no')
            ->leftJoin('product_m as p', 'p.prod_no', '=', 't.prod_no')
            ->where('t.status', '1')
            ->where('t.prod_no', $prodNo)
            ->when(!empty($facNo), function($q) use ($facNo) {
                $q->where('t.check_fac_no', $facNo);
            })
            ->select([
                DB::raw('s.store_name as store_name'),
                DB::raw('s.fac_no as fac_no'),
                DB::raw('t.update_user as update_user'),
                DB::raw('m.ord_no as ord_no'),
                DB::raw('m.mb_no as mb_no'),
                DB::raw('m.mb_name as mb_name'),
                DB::raw('t.create_time as create_time'),
                DB::raw('FROM_UNIXTIME(t.use_date) as use_date'),
                DB::raw('p.prod_no as prod_no'),
                DB::raw('p.prod_name as prod_name'),
            ])
            ->orderBy('s.store_name');

        // 依 date_field 套用區間條件（避免閉包捕捉未定義變數）
        if ($dateField === 'create_time') {
            $query->whereBetween(DB::raw("STR_TO_DATE(t.create_time, '%Y-%m-%d %H:%i:%s')"), [$startStr, $endStr]);
        } else {
            $query->whereBetween('t.use_date', [$startTs, $endTs]);
        }
        //ddSql($query);    
        $rows = $query->get();    
        
        return response()->json(['data' => $rows]);
    }

    public function ticketNotUsedList(Request $request)
    {//未用票券清單
        $ym = $request->get('ym');
        $prodNo = $request->get('prod_no');
        $facNo = $request->get('fac_no');
        $sd = $request->get('start_date');
        $ed = $request->get('end_date');

        if (!$prodNo) {
            return response()->json(['data' => []]);
        }

        // 區間：若提供 start/end 則優先；否則回退 ym 月份（未使用沒有 use_date）
        if (!empty($sd) || !empty($ed)) {
            if (empty($sd) && !empty($ym)) {
                $sd = $ym . '-01';
            }
            if (empty($ed) && !empty($ym)) {
                $ed = date('Y-m-t', strtotime($ym . '-01'));
            }
            $start = !empty($sd) ? ($sd . ' 00:00:00') : '1970-01-01 00:00:00';
            $end = !empty($ed) ? ($ed . ' 23:59:59') : date('Y-m-d 23:59:59');
        } else {
            if (empty($ym)) {
                return response()->json(['data' => []]);
            }
            $start = $ym . '-01 00:00:00';
            $end = date('Y-m-t 23:59:59', strtotime($ym . '-01'));
        }

        $query = DB::table('ticket as t')
            ->join('order_m as m', 'm.ord_no', '=', 't.ord_no')
            ->leftJoin('store_tb as s', 's.fac_no', '=', 't.check_fac_no')
            ->leftJoin('product_m as p', 'p.prod_no', '=', 't.prod_no')
            ->where('t.status', '0')
            ->where('t.prod_no', $prodNo)
            ->whereBetween(DB::raw("STR_TO_DATE(t.create_time, '%Y-%m-%d %H:%i:%s')"), [$start, $end])
            ->when(!empty($facNo), function($q) use ($facNo) {
                $q->where('t.check_fac_no', $facNo);
            })
            ->select([
                DB::raw('COALESCE(s.store_name, "") as store_name'),
                DB::raw('COALESCE(s.fac_no, "") as fac_no'),
                DB::raw('COALESCE(t.update_user, "") as update_user'),
                DB::raw('COALESCE(m.ord_no, "") as ord_no'),
                DB::raw('COALESCE(m.mb_no, "") as mb_no'),
                DB::raw('COALESCE(m.mb_name, "") as mb_name'),
                DB::raw('COALESCE(t.create_time, "") as create_time'),
                DB::raw('COALESCE(t.use_date, "") as use_date'),
                DB::raw('COALESCE(p.prod_no, "") as prod_no'),
                DB::raw('COALESCE(p.prod_name, "") as prod_name'),
            ])
            ->orderBy('s.store_name');
        //ddSql($query);        
        $rows = $query->get();

        return response()->json(['data' => $rows]);
    }
}
