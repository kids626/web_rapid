<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    /**
     * 需排除的型號清單 目前使用在每月Top10產品
     */
    private function excludedProdNos(): array
    {
        return ['EMA-001', 'EM-002', 'EM-003','point','EMAC-0027','EMAC-0039','EM-001'];
    }

    public function index(Request $request)
    {
        // 讀取查詢關鍵字（使用者輸入的型號或產品名稱），去除前後空白
        $keyword = trim($request->get('q', ''));
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

        // 建立彙總查詢：以訂單明細 d 為主，關聯產品 p 與訂單主檔 m
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
            ])
            // 排除非銷售訂單
            ->where('m.order_kind', '<>', '99')
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
            // 若無關鍵字，改為帶入前 10 個型號做為 whereIn 篩選
            ->when($keyword === '' && !empty($topProdNos), function ($q) use ($topProdNos) {
                $q->whereIn('p.prod_no', $topProdNos);
            })
            // 若提供關鍵字：對型號或產品名稱進行模糊比對
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($qq) use ($keyword) {
                    $qq->where('p.prod_no', 'like', "%{$keyword}%")
                       ->orWhere('p.prod_name', 'like', "%{$keyword}%");
                });
            })
            // 依型號＋產品名稱進行彙總（避免名稱相同但型號不同的紀錄被合併）
            ->groupBy('p.prod_no', 'p.prod_name')
            // 以型號排序，讓結果穩定
            ->orderBy('p.prod_no');

        // 若有提供日期區間：回傳完整結果；否則預設僅取前 5 筆示例
        $rows = ($start || $end) ? $query->get() : $query->limit(5)->get();

        // 將結果與查詢條件回傳到 Blade 視圖
        return view('sales.report', [
            'rows' => $rows,
            'keyword' => $keyword,
            'start' => $start,
            'end' => $end,
            'autoProdNos' => $topProdNos,
        ]);
    }

    public function monthly(Request $request)
    {
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
            ->where('m.order_kind', '<>', '99')
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
            $labels[] = date('Y-m', $cursor);
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
            ->where('m.order_kind', '<>', '99')
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
            ->where('m.order_kind', '<>', '99')
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
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        $excluded = $this->excludedProdNos();

        if (!$start && !$end) {
            $start = date('Y-01-01');
            $end = date('Y-m-d');
        }

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
            ->where('m.order_kind', '<>', '99')
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

        return view('sales.top_monthly', [
            'rows' => $ranked,
            'start' => $start,
            'end' => $end,
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
        $dateExpr = "STR_TO_DATE(t." . $dateField . ", '%Y-%m-%d %H:%i:%s')";

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
            ->where('m.order_kind', '<>', '99')
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
        $excluded = $this->excludedProdNos();

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
                'p.prod_no',
                'p.prod_name',
                DB::raw("DATE_FORMAT(m.create_date, '%Y-%m') AS ym"),
                DB::raw('SUM(COALESCE(d.qty,0)) AS total_qty'),
                DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.price,0)) AS total_amount'),
                DB::raw('SUM(COALESCE(d.qty,0) * COALESCE(d.unit_pv,0)) AS total_pv'),
            ])
            ->where('m.order_kind', '<>', '99')
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
                    $qq->where('p.prod_no', 'like', "%{$keyword}%")
                       ->orWhere('p.prod_name', 'like', "%{$keyword}%");
                });
            })
            ->groupBy('p.prod_no', 'p.prod_name', DB::raw("DATE_FORMAT(m.create_date, '%Y-%m')"))
            ->orderBy('ym')
            ->orderBy('p.prod_no');

        $rows = $query->get();

        return view('sales.product_monthly', [
            'rows' => $rows,
            'keyword' => $keyword,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function ticketUsedList(Request $request)
    {
        $ym = $request->get('ym');
        $prodNo = $request->get('prod_no');
        $facNo = $request->get('fac_no');

        if (!$ym || !$prodNo) {
            return response()->json(['data' => []]);
        }

        // 對應月份區間
        $start = $ym . '-01 00:00:00';
        // 取該月最後一天
        $end = date('Y-m-t 23:59:59', strtotime($ym . '-01'));

        $query = DB::table('ticket as t')
            ->join('order_m as m', 'm.ord_no', '=', 't.ord_no')
            ->leftJoin('store_tb as s', 's.fac_no', '=', 't.check_fac_no')
            ->leftJoin('product_m as p', 'p.prod_no', '=', 't.prod_no')
            ->where('t.status', '1')
            ->where('t.prod_no', $prodNo)
            ->whereBetween(DB::raw("STR_TO_DATE(t.create_time, '%Y-%m-%d %H:%i:%s')"), [$start, $end])
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
        //ddSql($query);    
        $rows = $query->get();    
        
        return response()->json(['data' => $rows]);
    }

    public function ticketNotUsedList(Request $request)
    {
        $ym = $request->get('ym');
        $prodNo = $request->get('prod_no');
        $facNo = $request->get('fac_no');

        if (!$ym || !$prodNo) {
            return response()->json(['data' => []]);
        }

        // 對應月份區間（用建立時間作為參考，未使用沒有 use_date）
        $start = $ym . '-01 00:00:00';
        $end = date('Y-m-t 23:59:59', strtotime($ym . '-01'));

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
