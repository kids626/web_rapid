<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>產品-月份彙總查詢</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .container { max-width: 1080px; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">產品-月份彙總查詢</h3>
        <div>
            <div class="dropdown d-inline-block">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="reportMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">切換報表</button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="reportMenu">
                    <a class="dropdown-item" href="{{ route('sales.report') }}">商品彙總（型號/名稱）</a>
                    <a class="dropdown-item active" href="{{ route('sales.report.product_monthly') }}">產品-月份彙總</a>
                    <a class="dropdown-item" href="{{ route('sales.report.monthly') }}">每月銷售額彙總</a>
                    <a class="dropdown-item" href="{{ route('sales.report.tickets') }}">票券彙總</a>
                    
                </div>
            </div>
        </div>
    </div>

    <form method="get" action="{{ route('sales.report.product_monthly') }}" class="mb-4">
        <div class="form-row">
            <div class="col-md-4 mb-2">
                <label>型號/產品名稱</label>
                <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="輸入型號或產品名稱">
            </div>
            <div class="col-md-3 mb-2">
                <label>開始日期</label>
                <input type="date" name="start_date" value="{{ $start }}" class="form-control">
            </div>
            <div class="col-md-3 mb-2">
                <label>結束日期</label>
                <input type="date" name="end_date" value="{{ $end }}" class="form-control">
            </div>
            <div class="col-md-2 mb-2 align-self-end">
                <button type="submit" class="btn btn-primary btn-block">查詢</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>產品編號</th>
                    <th>產品名稱</th>
                    <th>月份</th>
                    <th class="text-right">銷售數量</th>
                    <th class="text-right">銷售額</th>
                    <th class="text-right">總PV值</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>
                    <td>{{ $row->ym }}</td>
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($row->total_pv, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


