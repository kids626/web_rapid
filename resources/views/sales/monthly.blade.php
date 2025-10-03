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
        <div id="salesChartData" data-labels='@json($chartLabels ?? [])' data-current='@json($chartCurrent ?? [])' data-prev='@json($chartPrev ?? [])' style="display:none"></div>
        <canvas id="salesChart" height="120"></canvas>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js"></script>
<script>
(function(){
  var dataEl = document.getElementById('salesChartData');
  var labels = [];
  var current = [];
  var prev = [];
  if (dataEl) {
    try { labels = JSON.parse(dataEl.dataset.labels || '[]'); } catch(e) { labels = []; }
    try { current = JSON.parse(dataEl.dataset.current || '[]'); } catch(e) { current = []; }
    try { prev = JSON.parse(dataEl.dataset.prev || '[]'); } catch(e) { prev = []; }
  }
  if (!labels.length) return;
  var ctx = document.getElementById('salesChart').getContext('2d');
  // 自訂插件：在長條頂端標出數值
  var barValuePlugin = {
    id: 'barValue',
    afterDatasetsDraw: function(chart){
      var ctx2 = chart.ctx;
      ctx2.save();
      (chart.data.datasets || []).forEach(function(ds, di){
        var meta = chart.getDatasetMeta(di);
        (meta.data || []).forEach(function(el, idx){
          var v = ds.data && ds.data[idx];
          if (v == null) return;
          var p = el.tooltipPosition();
          var txt = String(Number(v).toLocaleString());
          ctx2.font = 'bold 11px sans-serif';
          ctx2.fillStyle = '#333';
          ctx2.textAlign = 'center';
          ctx2.textBaseline = 'bottom';
          var x = p.x, y = p.y - 4; // 上方略縮
          ctx2.fillText(txt, x, y);
        });
      });
      ctx2.restore();
    }
  };
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
        y: { beginAtZero: true }
      },
      plugins: {
        tooltip: { mode: 'index', intersect: false },
        legend: { position: 'top' }
      }
    },
    plugins: [barValuePlugin]
  });
})();
</script>
</body>
</html>


