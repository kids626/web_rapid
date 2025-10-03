<div>
    <div class="dropdown d-inline-block">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="reportMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">切換報表</button>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="reportMenu">
            <a class="dropdown-item {{ request()->routeIs('sales.report') ? 'active' : '' }}" href="{{ route('sales.report') }}">目前至今銷售額/數量查詢(請搜尋型號或產品名稱)</a>
            <a class="dropdown-item {{ request()->routeIs('sales.report.product_monthly') ? 'active' : '' }}" href="{{ route('sales.report.product_monthly') }}">產品銷售數量-每月彙總查詢</a> 
            <a class="dropdown-item {{ request()->routeIs('sales.report.top_monthly') ? 'active' : '' }}" href="{{ route('sales.report.top_monthly') }}">每月Top10產品</a>           
            <a class="dropdown-item {{ request()->routeIs('sales.report.tickets') ? 'active' : '' }}" href="{{ route('sales.report.tickets') }}">票券彙總</a>
            <a class="dropdown-item {{ request()->routeIs('sales.report.monthly') ? 'active' : '' }}" href="{{ route('sales.report.monthly') }}">每月銷售額彙總</a>            
            <a class="dropdown-item {{ request()->routeIs('sales.report.yearly') ? 'active' : '' }}" href="{{ route('sales.report.yearly') }}">年度銷售額彙總</a>
            
            
        </div>
    </div>
</div>


