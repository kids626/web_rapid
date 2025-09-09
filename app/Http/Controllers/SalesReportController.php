<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        $keyword = trim($request->get('q', ''));
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        $query = DB::table('order_d as d')
            ->join('product_m as p', 'p.prod_no', '=', 'd.prod_no')
            ->join('order_m as m', 'm.ord_no', '=', 'd.ord_no')
            ->select([
                'p.prod_no',
                'p.prod_name',
                DB::raw('SUM(COALESCE(d.qty, 0)) as total_qty'),
                DB::raw('SUM(COALESCE(d.sub_money, COALESCE(d.qty,0) * COALESCE(d.price,0))) as total_amount'),
            ])
            ->when($start, function ($q) use ($start) {
                $q->whereDate('m.ord_date2', '>=', $start);
            })
            ->when($end, function ($q) use ($end) {
                $q->whereDate('m.ord_date2', '<=', $end);
            })
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($qq) use ($keyword) {
                    $qq->where('p.prod_no', 'like', "%{$keyword}%")
                       ->orWhere('p.prod_name', 'like', "%{$keyword}%");
                });
            })
            ->groupBy('p.prod_no', 'p.prod_name')
            ->orderBy('p.prod_no');

        $rows = $query->paginate(15)->appends($request->query());

        return view('sales.report', [
            'rows' => $rows,
            'keyword' => $keyword,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
