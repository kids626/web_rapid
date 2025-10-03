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
        .table tbody tr.group-alt { background-color:rgb(254, 246, 255); }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">每月Top10產品</h3>
        @include('sales.partials.menu')
    </div>

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" action="{{ route('sales.report.top_monthly') }}">
                <div class="form-row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label>開始日期</label>
                        <input type="date" name="start_date" value="{{ $start }}" class="form-control">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label>結束日期</label>
                        <input type="date" name="end_date" value="{{ $end }}" class="form-control">
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="custom-control custom-switch mt-4">
                            <input type="checkbox" class="custom-control-input" id="byDetailSwitch" name="by_detail" value="1" {{ !empty($byDetail) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="byDetailSwitch">依細項商品</label>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2 text-left">
                        <button type="submit" class="btn btn-primary">查詢</button>
                        <a href="{{ route('sales.report.top_monthly') }}" class="btn btn-light">清除</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>月份</th>
                    <th class="text-right">名次</th>
                    <th>{{ !empty($byDetail) ? '細項編號' : '型號' }}</th>
                    <th>{{ !empty($byDetail) ? '細項名稱' : '產品名稱' }}</th>
                    <th class="text-right">銷售數量</th>
                    @if(empty($byDetail))
                    <th class="text-right">銷售額</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="{{ intdiv($loop->iteration - 1, 10) % 2 ? 'group-alt' : '' }}">
                    <td class="text-danger">{{ $row->ym }}</td>
                    <td class="text-right">{{ $row->rank }}</td>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    @if(empty($byDetail))
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ !empty($byDetail) ? 5 : 6 }}" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <h5>各月份 Top10 數量圖</h5>
        <div id="topMonthlyChartData" data-chart='@json($chartByMonth ?? [])' style="display:none"></div>
        <div class="form-inline mb-2">
            <label class="mr-2">選擇月份</label>
            <select id="topMonthlyYm" class="form-control">
                @php $ymKeys = array_keys($chartByMonth ?? []); @endphp
                @foreach($ymKeys as $ym)
                <option value="{{ $ym }}">{{ $ym }}</option>
                @endforeach
            </select>
        </div>
        <canvas id="topMonthlyChart" height="120"></canvas>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js"></script>
<script>
$(function(){
  var dataEl = document.getElementById('topMonthlyChartData');
  if (!dataEl) return;
  var all = {};
  try { all = JSON.parse(dataEl.dataset.chart || '{}'); } catch(e) { all = {}; }
  var ymSel = document.getElementById('topMonthlyYm');
  var ctx = document.getElementById('topMonthlyChart').getContext('2d');
  function buildConfig(ym){
    var labels = (all[ym] && all[ym].labels) || [];
    var series = (all[ym] && all[ym].data) || [];
    return {
      type: 'bar',
      data: { labels: labels, datasets: [{ label: ym + ' Top10 數量', backgroundColor: 'rgba(75, 192, 192, 0.6)', data: series }] },
      options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: true } } }
    };
  }
  var initialYm = ymSel && ymSel.value || Object.keys(all)[0] || '';
  if (!initialYm) return;
  var chart = new Chart(ctx, buildConfig(initialYm));
  if (ymSel) {
    ymSel.addEventListener('change', function(){
      var ym = ymSel.value;
      var cfg = buildConfig(ym);
      chart.data = cfg.data;
      chart.options = cfg.options;
      chart.update();
    });
  }
});
</script>
</body>
</html>


