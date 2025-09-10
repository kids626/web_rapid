<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        // 讀取查詢關鍵字（使用者輸入的型號或產品名稱），去除前後空白
        $keyword = trim($request->get('q', ''));
        if ($keyword === '') {
            // 若未提供關鍵字：從產品檔取最近更新的一筆型號作為預設值（以 data_timestamp DESC）
            $defaultProdNo = DB::table('product_m')->orderBy('data_timestamp', 'desc')->value('prod_no');
            $keyword = $defaultProdNo ? (string) $defaultProdNo : '';
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

    public function tickets(Request $request)
    {
        // 讀取欲使用的日期欄位參數（預設為 create_time，可選 use_date 或 create_time）
        $dateField = $request->get('date_field', 'create_time'); // use_date | create_time
        // 讀取查詢的開始與結束日期（格式預期 YYYY-MM-DD）
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        // 讀取查詢關鍵字（用於型號/產品名稱的模糊比對）
        $keyword = trim($request->get('q', ''));
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
            ->where('m.cancel_flag', 0)
            ->whereIn('m.io_kind', ['1', '2'])
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

        return view('sales.tickets', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'dateField' => $dateField,
            'keyword' => $keyword,
        ]);
    }

    public function productMonthly(Request $request)
    {
        $keyword = trim($request->get('q', ''));        

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
            ->select([
                DB::raw('s.store_name as store_name'),
                DB::raw('s.fac_no as fac_no'),
                DB::raw('t.update_user as update_user'),
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
            ->select([
                DB::raw('COALESCE(s.store_name, "") as store_name'),
                DB::raw('COALESCE(s.fac_no, "") as fac_no'),
                DB::raw('COALESCE(t.update_user, "") as update_user'),
                DB::raw('COALESCE(m.mb_name, "") as mb_name'),
                DB::raw('COALESCE(t.create_time, "") as create_time'),
                DB::raw('COALESCE(t.use_date, "") as use_date'),
                DB::raw('COALESCE(p.prod_no, "") as prod_no'),
                DB::raw('COALESCE(p.prod_name, "") as prod_name'),
            ])
            ->orderBy('s.store_name');
        $rows = $query->get();

        return response()->json(['data' => $rows]);
    }
}
