<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>年度銷售額彙總</title>
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

    <form method="get" action="{{ route('sales.report.yearly') }}" class="mb-4">
        <div class="form-row">
            <div class="col-md-3 mb-2">
                <label>起始年度</label>
                <input type="number" name="start_year" value="{{ $startYear }}" class="form-control">
            </div>
            <div class="col-md-3 mb-2">
                <label>結束年度</label>
                <input type="number" name="end_year" value="{{ $endYear }}" class="form-control">
            </div>
            <div class="col-md-3 mb-2 align-self-end">
                <button type="submit" class="btn btn-primary">查詢</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>年度</th>
                    <th class="text-right">銷售額</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->y }}</td>
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <h5>年度銷售額長條圖</h5>
        <canvas id="yearlyChart" height="120"></canvas>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
(function(){
  var labels = @json($chartLabels ?? []);
  var data = @json($chartData ?? []);
  if (!labels.length) return;
  var ctx = document.getElementById('yearlyChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: '銷售額', backgroundColor: 'rgba(54, 162, 235, 0.6)', data: data }
      ]
    },
    options: {
      responsive: true,
      scales: { yAxes: [{ ticks: { beginAtZero: true } }] },
      tooltips: { mode: 'index', intersect: false },
      legend: { display: false }
    }
  });
})();
</script>
</body>
</html>


