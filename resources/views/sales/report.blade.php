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
        .table-hover tbody tr:hover { background-color: #fffbe6; }
        #page-loader {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.95);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
    </style>
    </head>
<body>
<div id="page-loader">
    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem"></div>
    <div class="mt-3 text-muted">頁面載入中...</div>
</div>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">目前至今銷售額/數量查詢(請搜尋型號或產品名稱)</h3>
        @include('sales.partials.menu')
    </div>

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
        <table class="table table-striped table-bordered table-hover">
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

</div>
<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var loader = document.getElementById('page-loader');
    // 視窗所有資源載入完成後隱藏
    window.addEventListener('load', function () {
        if (loader) loader.style.display = 'none';
    });
    // 送出查詢表單時顯示載入中
    var form = document.querySelector(`form[action="{{ route('sales.report') }}"]`) || document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () {
            if (loader) loader.style.display = 'flex';
        });
    }
});
</script>
</body>
</html>


