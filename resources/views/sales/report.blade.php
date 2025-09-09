<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>銷售查詢</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .container { max-width: 1080px; }
        .table thead th { white-space: nowrap; }
    </style>
    </head>
<body>
<div class="container mt-4">
    <h3 class="mb-3">銷售額/數量查詢</h3>

    <form method="get" action="{{ route('sales.report') }}" class="mb-4">
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
                    <th>型號</th>
                    <th>產品名稱</th>
                    <th class="text-right">銷售數量</th>
                    <th class="text-right">銷售額</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $rows->links() }}
    </div>
</div>
</body>
</html>


