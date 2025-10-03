<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>產品銷售數量-每月彙總查詢</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .container { max-width: 1080px; }
        .table thead th { white-space: nowrap; }
        .table-hover tbody tr:hover { background-color: #fffbe6; }
    </style>
</head>
<body>
<div class="container mt-4" id="top">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">產品-月份彙總查詢</h3>
        @include('sales.partials.menu')
    </div>

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" action="{{ route('sales.report.product_monthly') }}">
                <div class="form-row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label>型號/產品名稱</label>
                        <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="輸入型號或產品名稱">
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="custom-control custom-switch mt-4">
                            <input type="checkbox" class="custom-control-input" id="byDetailSwitch" name="by_detail" value="1" {{ !empty($byDetail) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="byDetailSwitch">依細項商品</label>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>開始日期</label>
                        <input type="date" name="start_date" value="{{ $start }}" class="form-control">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>結束日期</label>
                        <input type="date" name="end_date" value="{{ $end }}" class="form-control">
                    </div>
                    
                    <div class="col-md-12 mb-2 text-left">
                        <button type="submit" class="btn btn-primary">查詢</button>
                        <a href="{{ route('sales.report.product_monthly') }}" class="btn btn-light">清除</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-2">
        <a href="#pmChart" class="btn btn-link p-0">移至圖表區</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>月份</th>
                    <th>{{ !empty($byDetail) ? '細項編號' : '產品編號' }}</th>
                    <th>{{ !empty($byDetail) ? '細項名稱' : '產品名稱' }}</th>                    
                    <th class="text-right">銷售數量</th>
                    @if(empty($byDetail))
                    <th class="text-right">銷售額</th>
                    <th class="text-right">總PV值</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td class="text-danger">{{ $row->ym }}</td>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>                    
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    @if(empty($byDetail))
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($row->total_pv, 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ !empty($byDetail) ? 4 : 6 }}" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <h5>目前區間內TOP20產品銷售（數量）</h5>
        <div id="pmChartData" data-labels='@json($chartProdLabels ?? [])' data-series='@json($chartProdData ?? [])' style="display:none"></div>
        <canvas id="pmChart" height="160"></canvas>
    </div>

    <div class="text-center my-3">
        <a href="#top" class="btn btn-link p-0">回最頂端</a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js"></script>
<script>
(function(){
  var el = document.getElementById('pmChartData');
  if (!el) return;
  var labels = [], data = [];
  try { labels = JSON.parse(el.dataset.labels || '[]'); } catch(e) { labels = []; }
  try { data = JSON.parse(el.dataset.series || '[]'); } catch(e) { data = []; }
  if (!labels.length) return;
  var ctx = document.getElementById('pmChart').getContext('2d');
  // 名次解析與顏色
  function getRankFromLabel(lbl, fallbackIndex) {
    var r = parseInt((String(lbl)).split('.')[0], 10);
    return (!r || isNaN(r)) ? (fallbackIndex + 1) : r;
  }
  function colorForRank(rank) {
    if (rank === 1) return 'rgba(252, 15, 66, 0.9)';     // 紅
    if (rank === 2) return 'rgba(238, 11, 200, 0.9)';     // 橘
    if (rank === 3) return 'rgba(170, 1, 248, 0.9)';     // 黃
    if (rank <= 10) return 'rgba(9, 111, 245, 0.96)';     // 青
    return 'rgba(40, 4, 245, 0.88)';                     // 藍
  }
  var colors = labels.map(function(lbl, idx){
    return colorForRank(getRankFromLabel(lbl, idx));
  });

  // 自訂插件：在橫條末端標示數量
  var valueLabelPlugin = {
    id: 'valueLabel',
    afterDatasetsDraw: function(chart) {
      var ctx2 = chart.ctx;
      ctx2.save();
      (chart.data.datasets || []).forEach(function(ds, di){
        var meta = chart.getDatasetMeta(di);
        (meta.data || []).forEach(function(barEl, idx){
          var val = ds.data && ds.data[idx];
          if (val == null) return;
          var p = barEl.tooltipPosition();
          var txt = String(Number(val).toLocaleString());
          ctx2.font = 'bold 12px sans-serif';
          ctx2.fillStyle = '#333';
          ctx2.textBaseline = 'middle';
          var pad = 6;
          var x = p.x + pad;
          var y = p.y;
          var area = chart.chartArea;
          var tw = ctx2.measureText(txt).width;
          if (x + tw > area.right) { x = p.x - tw - pad; }
          ctx2.fillText(txt, x, y);
        });
      });
      ctx2.restore();
    }
  };
  new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ label: '數量', data: data, backgroundColor: colors }] },
    options: {
      indexAxis: 'y',
      responsive: true,
      scales: {
        x: { beginAtZero: true },
        y: {
          ticks: {
            color: function(ctx){
              var lbl = ctx.tick && ctx.tick.label ? ctx.tick.label : '';
              var rank = getRankFromLabel(lbl, ctx.index || 0);
              return colorForRank(rank);
            },
            font: { weight: 'bold' }
          }
        }
      },
      plugins: { legend: { display: true } }
    },
    plugins: [valueLabelPlugin]
  });
})();
</script>
</body>
</html>


