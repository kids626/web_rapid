<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>每月Top10產品</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .container { max-width: 1080px; }
        .table thead th { white-space: nowrap; }
        .table-hover tbody tr:hover { background-color: #fffbe6; }
        .table tbody tr.group-alt { background-color: #f6f9ff; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">每月Top10產品</h3>
        @include('sales.partials.menu')
    </div>

    <form method="get" action="{{ route('sales.report.top_monthly') }}" class="mb-4">
        <div class="form-row">
            <div class="col-md-4 mb-2">
                <label>開始日期</label>
                <input type="date" name="start_date" value="{{ $start }}" class="form-control">
            </div>
            <div class="col-md-4 mb-2">
                <label>結束日期</label>
                <input type="date" name="end_date" value="{{ $end }}" class="form-control">
            </div>
            <div class="col-md-4 mb-2 align-self-end">
                <button type="submit" class="btn btn-primary">查詢</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>月份</th>
                    <th class="text-right">名次</th>
                    <th>型號</th>
                    <th>產品名稱</th>
                    <th class="text-right">銷售數量</th>
                    <th class="text-right">銷售額</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="{{ intdiv($loop->iteration - 1, 10) % 2 ? 'group-alt' : '' }}">
                    <td>{{ $row->ym }}</td>
                    <td class="text-right">{{ $row->rank }}</td>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
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


