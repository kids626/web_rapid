<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>票券彙總查詢</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .container { max-width: 1080px; }
        .table thead th { white-space: nowrap; }
        /* 放大已用票券清單對話框寬度 */
        #usedModal .modal-dialog { max-width: 95%; }
        /* 放大未用票券清單對話框寬度 */
        #notUsedModal .modal-dialog { max-width: 95%; }
        .table-hover tbody tr:hover { background-color: #fffbe6; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">票券彙總查詢</h3>
        @include('sales.partials.menu')
    </div>

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" action="{{ route('sales.report.tickets') }}">
                <div class="form-row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label>型號/產品名稱</label>
                        <input type="text" name="q" value="{{ $keyword ?? '' }}" class="form-control" placeholder="輸入型號或產品名稱">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label>店家</label>
                        <select name="fac_no" class="form-control">
                            <option value="">全部店家</option>
                            @foreach(($stores ?? []) as $s)
                                <option value="{{ $s->fac_no }}" {{ (!empty($facNo) && $facNo==$s->fac_no) ? 'selected' : '' }}>{{ $s->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label>日期欄位</label>
                        <select name="date_field" class="form-control">                    
                            <option value="create_time" {{ $dateField==='create_time' ? 'selected' : '' }}>票卷建立時間(create_time)</option>
                            <option value="use_date" {{ $dateField==='use_date' ? 'selected' : '' }}>票券使用時間(use_date)</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>開始日期</label>
                        <input type="date" name="start_date" value="{{ $start }}" class="form-control">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>結束日期</label>
                        <input type="date" name="end_date" value="{{ $end }}" class="form-control">
                    </div>
                    <div class="col-md-6 mb-2 text-right">
                        <button type="submit" class="btn btn-primary">查詢</button>
                        <a href="{{ route('sales.report.tickets') }}" class="btn btn-light">清除</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>月份</th>
                    <th>型號</th>
                    <th>產品名稱</th>
                    <th class="text-right">票券數</th>
                    <th class="text-right">已用</th>
                    <th class="text-right">未用</th>
                   
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->ym }}</td>
                    <td>{{ $row->prod_no }}</td>
                    <td>{{ $row->prod_name }}</td>
                    <td class="text-right">{{ number_format($row->ticket_count) }}</td>
                    <td class="text-right">
                        <a href="#" class="used-link" data-ym="{{ $row->ym }}" data-prodno="{{ $row->prod_no }}">{{ number_format($row->used_count) }}</a>
                    </td>
                    <td class="text-right">
                        <a href="#" class="notused-link" data-ym="{{ $row->ym }}" data-prodno="{{ $row->prod_no }}">{{ number_format($row->not_used_count) }}</a>
                    </td>
                   
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">查無資料</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
<!-- Modal -->
<div class="modal fade" id="usedModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">已用票券店家清單</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th>編號</th>
                <th>店家名</th>
                <th>店家編號</th>
                <th>訂單號</th>
                <th>會員編號</th>
                <th>型號</th>
                <th>產品名稱</th>
                <th>update_user</th>
                <th>會員姓名</th>
                <th>create_time</th>
                <th>use_date</th>
              </tr>
            </thead>
            <tbody id="usedListBody">
              <tr><td colspan="11" class="text-center text-muted">載入中...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">關閉</button>
      </div>
    </div>
  </div>
</div>
<!-- Not Used Modal -->
<div class="modal fade" id="notUsedModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">未用票券清單</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th>編號</th>
                <th>店家名</th>
                <th>店家編號</th>
                <th>訂單號</th>
                <th>會員編號</th>
                <th>型號</th>
                <th>產品名稱</th>
                <th>update_user</th>
                <th>會員姓名</th>
                <th>create_time</th>
                <th>use_date</th>
              </tr>
            </thead>
            <tbody id="notUsedListBody">
              <tr><td colspan="11" class="text-center text-muted">載入中...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">關閉</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
  $(document).on('click', '.used-link', function(e){
    e.preventDefault();
    var ym = $(this).data('ym');
    var prodNo = $(this).data('prodno');
    var $tbody = $('#usedListBody');
    $tbody.html('<tr><td colspan="11" class="text-center text-muted">載入中...</td></tr>');
    $('#usedModal').modal('show');
    $.get("{{ route('sales.report.tickets.used_list') }}", {
      ym: ym,
      prod_no: prodNo,
      fac_no: $('select[name=\'fac_no\']').val(),
      date_field: $('select[name=\'date_field\']').val(),
      start_date: $('input[name=\'start_date\']').val(),
      end_date: $('input[name=\'end_date\']').val()
    }, function(resp){
      var rows = resp.data || [];
      if (!rows.length) {
        $tbody.html('<tr><td colspan="11" class="text-center text-muted">查無資料</td></tr>');
        return;
      }
      var html = rows.map(function(r, idx){
        return '<tr>'+
               '<td>'+(idx+1)+'</td>'+
               '<td>'+(r.store_name||'')+'</td>'+
               '<td>'+(r.fac_no||'')+'</td>'+
               '<td>'+(r.ord_no||'')+'</td>'+
               '<td>'+(r.mb_no||'')+'</td>'+
               '<td>'+(r.prod_no||'')+'</td>'+
               '<td>'+(r.prod_name||'')+'</td>'+
               '<td>'+(r.update_user||'')+'</td>'+
               '<td>'+(r.mb_name||'')+'</td>'+
               '<td>'+(r.create_time||'')+'</td>'+
               '<td>'+(r.use_date||'')+'</td>'+
               '</tr>';
      }).join('');
      $tbody.html(html);
      // 依目前日期欄位決定是否將 use_date 欄位標紅
      var isUseDate = $('select[name=\'date_field\']').val() === 'use_date';
      var $modal = $('#usedModal');
      // 表頭第 11 欄（0-based index 10）
      $modal.find('thead th').eq(10).toggleClass('text-danger', isUseDate);
      // 每列第 11 欄
      $modal.find('tbody tr').each(function(){
        $(this).find('td').eq(10).toggleClass('text-danger', isUseDate);
      });
    });
  });

  $(document).on('click', '.notused-link', function(e){
    e.preventDefault();
    var ym = $(this).data('ym');
    var prodNo = $(this).data('prodno');
    var $tbody = $('#notUsedListBody');
    $tbody.html('<tr><td colspan="11" class="text-center text-muted">載入中...</td></tr>');
    $('#notUsedModal').modal('show');
    $.get("{{ route('sales.report.tickets.not_used_list') }}", {
      ym: ym,
      prod_no: prodNo,
      fac_no: $('select[name=\'fac_no\']').val(),
      date_field: $('select[name=\'date_field\']').val(),
      start_date: $('input[name=\'start_date\']').val(),
      end_date: $('input[name=\'end_date\']').val()
    }, function(resp){
      var rows = resp.data || [];
      if (!rows.length) {
        $tbody.html('<tr><td colspan="11" class="text-center text-muted">查無資料</td></tr>');
        return;
      }
      var html = rows.map(function(r, idx){
        return '<tr>'+
               '<td>'+(idx+1)+'</td>'+
               '<td>'+(r.store_name||'')+'</td>'+
               '<td>'+(r.fac_no||'')+'</td>'+
               '<td>'+(r.ord_no||'')+'</td>'+
               '<td>'+(r.mb_no||'')+'</td>'+
               '<td>'+(r.prod_no||'')+'</td>'+
               '<td>'+(r.prod_name||'')+'</td>'+
               '<td>'+(r.update_user||'')+'</td>'+
               '<td>'+(r.mb_name||'')+'</td>'+
               '<td>'+(r.create_time||'')+'</td>'+
               '<td>'+(r.use_date||'')+'</td>'+
               '</tr>';
      }).join('');
      $tbody.html(html);
    });
  });
});
</script>
</body>
</html>


