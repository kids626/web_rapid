<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('report.basic')->group(function () { //定義檔案（邏輯）: app/Http/Middleware/ReportBasicAuth.php
    Route::get('/sales-report', 'SalesReportController@index')->name('sales.report');
    Route::get('/sales-report/monthly', 'SalesReportController@monthly')->name('sales.report.monthly');
    Route::get('/sales-report/tickets', 'SalesReportController@tickets')->name('sales.report.tickets');
    Route::get('/sales-report/product-monthly', 'SalesReportController@productMonthly')->name('sales.report.product_monthly');
    Route::get('/sales-report/yearly', 'SalesReportController@yearly')->name('sales.report.yearly');
    Route::get('/sales-report/top-monthly', 'SalesReportController@topMonthly')->name('sales.report.top_monthly');
    Route::get('/sales-report/tickets/used-list', 'SalesReportController@ticketUsedList')->name('sales.report.tickets.used_list');
    Route::get('/sales-report/tickets/not-used-list', 'SalesReportController@ticketNotUsedList')->name('sales.report.tickets.not_used_list');
    Route::get('/sales-report/subtype-breakdown', 'SalesReportController@subtypeBreakdown')->name('sales.report.subtype_breakdown');
});
