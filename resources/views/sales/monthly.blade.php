<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>月份彙總查詢</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .container { max-width: 1080px; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">每月銷售額彙總</h3>
        @include('sales.partials.menu')
    </div>

    <form method="get" action="{{ route('sales.report.monthly') }}" class="mb-4">
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
        <table class="table table-striped table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>月份</th>
                    <th class="text-right">銷售數量</th>
                    <th class="text-right">銷售額</th>
                    <th class="text-right">去年同月銷售額</th>
                    <th class="text-right">年成長差額</th>
                    <th class="text-right">總PV值</th>
                    <th class="text-right">訂單數</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->ym }}</td>
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                    @php
                        $prevYm = date('Y-m', strtotime('-1 year', strtotime($row->ym.'-01')));
                        $prevIndex = array_search($row->ym, $chartLabels ?? []);
                        $prevVal = 0;
                        if (isset($chartPrev[$prevIndex])) { $prevVal = $chartPrev[$prevIndex]; }
                        $diff = ($row->total_amount ?? 0) - $prevVal;
                    @endphp
                    <td class="text-right">{{ number_format($prevVal, 2) }}</td>
                    <td class="text-right">{{ number_format($diff, 2) }}</td>
                    <td class="text-right">{{ number_format($row->total_pv, 2) }}</td>
                    <td class="text-right">{{ number_format($row->order_count) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <h5>今年 vs 去年同月 銷售額比較</h5>
        <canvas id="salesChart" height="120"></canvas>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
(function(){
  var labels = @json($chartLabels ?? []);
  var current = @json($chartCurrent ?? []);
  var prev = @json($chartPrev ?? []);
  if (!labels.length) return;
  var ctx = document.getElementById('salesChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: '今年', backgroundColor: 'rgba(54, 162, 235, 0.6)', data: current },
        { label: '去年同月', backgroundColor: 'rgba(201, 203, 207, 0.6)', data: prev }
      ]
    },
    options: {
      responsive: true,
      scales: {
        yAxes: [{ ticks: { beginAtZero: true } }]
      },
      tooltips: { mode: 'index', intersect: false },
      legend: { position: 'top' }
    }
  });
})();
</script>
</body>
</html>


