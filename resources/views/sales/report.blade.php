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
        .chart-scroll { height: 1000px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
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

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" action="{{ route('sales.report') }}">
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
                        <a href="{{ route('sales.report') }}" class="btn btn-light">清除</a>
                    </div>
                </div>
            </form>
            <!--@if(!empty($hotKeywords))
             <div class="mt-2">
                <div class="small text-muted mb-1">熱門關鍵字</div>
                <div>
                    @foreach($hotKeywords as $tag)   
                        <a href="{{ route('sales.report', array_merge(request()->except(['page']), ['q' => $tag['value'], 'by_detail' => !empty($byDetail) ? 1 : null, 'start_date' => $start, 'end_date' => $end])) }}" class="badge badge-pill badge-info mr-1 mb-1" title="{{ $tag['label'] }}">{{ $tag['label'] }}</a>                        
                    @endforeach
                </div>
            </div> 
            @endif-->
            @if(!empty($recentKeywords))
            <div class="mt-2">
                <div class="small text-muted mb-1">最近搜尋</div>
                <div>
                    @foreach($recentKeywords as $kw)
                        <a href="{{ route('sales.report', array_merge(request()->except(['page']), ['q' => $kw, 'by_detail' => !empty($byDetail) ? 1 : null, 'start_date' => $start, 'end_date' => $end])) }}" class="badge badge-pill badge-secondary mr-1 mb-1" title="{{ $kw }}">{{ $kw }}</a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>{{ !empty($byDetail) ? '細項編號' : '型號' }}</th>
                    <th>{{ !empty($byDetail) ? '細項名稱' : '產品名稱' }}</th>
                    <th class="text-right">銷售數量</th>
                    @if(!empty($byDetail))
                    <th class="text-right">贈品數量</th>
                    <th class="text-right">獎勵數量</th>
                    <th class="text-right">未領取獎勵數</th>
                    <th class="text-right">總計數量</th>
                    @else
                    <th class="text-right">銷售額</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>
                    <td class="text-right">
                        @if(!empty($row->has_subtype))
                        <a href="#" class="js-open-subtype" data-prod-no="{{ $row->prod_no }}" title="查看各類別數量">
                            {{ number_format($row->total_qty) }}
                        </a>
                        @else
                            {{ number_format($row->total_qty) }}
                        @endif
                    </td>
                    @if(!empty($byDetail))
                    <td class="text-right">{{ number_format($row->gift_qty ?? 0) }}</td>
                    <td class="text-right">{{ number_format($row->reward_qty ?? 0) }}</td>
                    {{-- 這一行會顯示「未領取獎勵數」這個欄位。如果 $row->unclaimed_qty 有值且大於 0，就會用千分位格式顯示數字；如果沒有或是 0，則顯示空白。 --}}
                    <td class="text-right">{{ ($row->unclaimed_qty ?? 0) > 0 ? number_format($row->unclaimed_qty) : 0 }}</td>
                    {{-- 這一行會顯示「總計數量」這個欄位。它的邏輯是：如果 $row->total_qty_with_gift 有值，就直接顯示這個值（格式化為千分位）；如果沒有這個值，則用「銷售數量 + 贈品數量 + 獎勵數量 + 未領取獎勵數」三個欄位的加總來顯示（同樣格式化為千分位）。如果這三個欄位有任何一個沒資料就當作 0。 --}}
                    <td class="text-right">{{ number_format(($row->total_qty_with_gift ?? (($row->total_qty ?? 0)+($row->gift_qty ?? 0)+($row->reward_qty ?? 0)+($row->unclaimed_qty ?? 0)))) }}</td>
                    @else
                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ !empty($byDetail) ? 7 : 4 }}" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        <div class="mb-4">
            <h5 class="mb-2">今天 產品銷售量（{{ $todayDate ?? '' }}）</h5>
            <div id="todayChartData" data-labels='@json($chartTodayLabels ?? [])' data-data='@json($chartTodayData ?? [])' data-date='{{ $todayDate ?? '' }}' style="display:none"></div>
            <div class="chart-scroll">
                <canvas id="todayChart" height="140"></canvas>
            </div>
        </div>
        <div class="mb-4">
            <h5 class="mb-2">昨天 產品銷售量（{{ $yesterdayDate ?? '' }}）</h5>
            <div id="yesterdayChartData" data-labels='@json($chartYesterdayLabels ?? [])' data-data='@json($chartYesterdayData ?? [])' data-date='{{ $yesterdayDate ?? '' }}' style="display:none"></div>
            <div class="chart-scroll">
                <canvas id="yesterdayChart" height="140"></canvas>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js"></script>
<!-- Subtype Modal -->
<div class="modal fade" id="subtypeModal" tabindex="-1" role="dialog" aria-labelledby="subtypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="subtypeModalLabel">各類別數量</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="text-muted mb-2" id="subtypeModalProd"></div>
        <div class="mb-2"><strong>總數量：</strong><span id="subtypeModalTotal">0</span></div>
        <div class="table-responsive">
          <table class="table table-striped table-bordered table-hover mb-0" id="subtypeTable">
            <thead class="thead-light">
              <tr>
                <!-- <th>子類別SN</th> -->
                <th>類別名稱</th>
                <th class="text-right">數量</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">關閉</button>
      </div>
    </div>
  </div>
  </div>
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
    // 畫出今天/昨天圖
    function renderBarChart(canvasId, dataElId, label){
        var el = document.getElementById(dataElId);
        if (!el) return;
        var labels = [], data = [];
        try { labels = JSON.parse(el.dataset.labels || '[]'); } catch(e) { labels = []; }
        try { data = JSON.parse(el.dataset.data || '[]'); } catch(e) { data = []; }
        if (!labels.length) return;
        // 產生每個長條的顏色（背景/邊框）
        function buildBarColors(count){
            var bg = [], bd = [];
            for (var i=0;i<count;i++){
                var hue = (i * 47) % 360; // 均勻分布色相
                bg.push('hsl(' + hue + ', 70%, 60%)');
                bd.push('hsl(' + hue + ', 70%, 40%)');
            }
            return { background: bg, border: bd };
        }
        var palette = buildBarColors(labels.length);
        // 讓 canvas 依資料量展開，高度 = 筆數 * 22px（最低 160px），外層容器固定 500px 可捲動
        var baseHeight = Math.max(labels.length * 22, 160);
        var canvas = document.getElementById(canvasId);
        canvas.height = baseHeight;
        var ctx = canvas.getContext('2d');
        var valuePlugin = { id: 'barValue', afterDatasetsDraw: function(chart){ var ctx2 = chart.ctx; ctx2.save(); (chart.getDatasetMeta(0).data||[]).forEach(function(el, idx){ var v = chart.data.datasets[0].data[idx]; if(v==null) return; var p = el.tooltipPosition(); var txt = String(Number(v).toLocaleString()); ctx2.font='bold 12px sans-serif'; ctx2.fillStyle='#333'; ctx2.textAlign='center'; ctx2.textBaseline='bottom'; ctx2.fillText(txt, p.x, p.y-4); }); ctx2.restore(); } };
        new Chart(ctx, { type: 'bar', data: { labels: labels, datasets: [{ label: label, backgroundColor: palette.background, borderColor: palette.border, borderWidth: 1, data: data, barPercentage: 0.85, categoryPercentage: 0.8, maxBarThickness: 22 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false, axis: 'y' }, events: ['mousemove','mouseout','click','touchstart','touchmove','touchend'], scales: { x: { beginAtZero: true, ticks: { font: { size: 12 } } }, y: { ticks: { font: { size: 12 } } } }, plugins: { legend: { display: true, labels: { font: { size: 12 } } }, tooltip: { position: 'nearest' } } }, plugins: [valuePlugin] });
    }
    renderBarChart('todayChart', 'todayChartData', '今天 數量');
    renderBarChart('yesterdayChart', 'yesterdayChartData', '昨天 數量');
    // 開啟子類別對話框
    var modalEl = document.getElementById('subtypeModal');
    var modal = null;
    if (typeof $ !== 'undefined' && $('#subtypeModal').modal) { /* bootstrap present */ }
    document.body.addEventListener('click', function(e){
        var a = e.target.closest && e.target.closest('.js-open-subtype');
        if (!a) return;
        e.preventDefault();
        var prodNo = a.getAttribute('data-prod-no');
        if (!prodNo) return;
        var sd = document.querySelector('input[name="start_date"]');
        var ed = document.querySelector('input[name="end_date"]');
        var url = "{{ route('sales.report.subtype_breakdown') }}" + '?format=json&prod_no=' + encodeURIComponent(prodNo)
            + (sd && sd.value ? ('&start_date=' + encodeURIComponent(sd.value)) : '')
            + (ed && ed.value ? ('&end_date=' + encodeURIComponent(ed.value)) : '');
        // 清空表格
        var tbody = document.querySelector('#subtypeTable tbody');
        while (tbody && tbody.firstChild) tbody.removeChild(tbody.firstChild);
        var sdLabel = (sd && sd.value) ? sd.value : '';
        var edLabel = (ed && ed.value) ? ed.value : '';
        var rangeLabel = (sdLabel || edLabel) ? ('　日期區間：' + (sdLabel || '不限') + ' ~ ' + (edLabel || '不限')) : '';
        document.getElementById('subtypeModalProd').textContent = '型號：' + prodNo + rangeLabel;
        var totalEl = document.getElementById('subtypeModalTotal');
        if (totalEl) totalEl.textContent = '0';
        // 取資料
        fetch(url, { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(resp){
            if (!resp || resp.ok === false) return;
            var rows = resp.rows || [];
            var total = 0;
            rows.forEach(function(r){
                var tr = document.createElement('tr');
                var td1 = document.createElement('td'); td1.textContent = r.sn;
                var td2 = document.createElement('td'); td2.textContent = r.type_name;
                var qty = Number(r.new_qty || 0);
                var td3 = document.createElement('td'); td3.className = 'text-right'; td3.textContent = qty.toLocaleString();
                //tr.appendChild(td1); tr.appendChild(td2); tr.appendChild(td3);
                tr.appendChild(td2); tr.appendChild(td3);
                tbody.appendChild(tr);
                total += qty;
            });
            if (totalEl) totalEl.textContent = Number(total).toLocaleString();
            // 顯示 modal
            if (typeof $ !== 'undefined' && $('#subtypeModal').modal) {
                $('#subtypeModal').modal('show');
            } else if (modalEl && modalEl.showModal) {
                modalEl.showModal();
            } else {
                // 簡易 fallback：開新視窗
                window.open("{{ route('sales.report.subtype_breakdown') }}" + '?prod_no=' + encodeURIComponent(prodNo), '_blank');
            }
        }).catch(function(){
            window.open("{{ route('sales.report.subtype_breakdown') }}" + '?prod_no=' + encodeURIComponent(prodNo), '_blank');
        });
    });
});
</script>
</body>
</html>


