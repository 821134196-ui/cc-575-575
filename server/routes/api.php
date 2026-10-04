<?php

use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\MockPrinterController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\ScrapController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\VersionController;
use Illuminate\Support\Facades\Route;

/* 基础数据 */
Route::get('/productions', [ProductionController::class, 'index']);
Route::post('/productions', [ProductionController::class, 'store']);
Route::get('/productions/{production}', [ProductionController::class, 'show']);
Route::get('/entrances', [ProductionController::class, 'entrances']);
Route::post('/entrances', [ProductionController::class, 'storeEntrance']);

/* 节目单版本 */
Route::post('/productions/{production}/versions', [VersionController::class, 'store']);
Route::post('/versions/{version}/file', [VersionController::class, 'updateFile']);
Route::post('/versions/{version}/lock', [VersionController::class, 'lock']);
Route::get('/versions/{version}/inventory', [VersionController::class, 'inventory']);
Route::get('/versions/{version}/movements', [VersionController::class, 'movements']);

/* 印刷批次：订货、印厂交货回执、前台分配 */
Route::post('/versions/{version}/print-orders', [PrintController::class, 'order']);
Route::post('/batches/{batch}/deliver', [PrintController::class, 'deliver']);
Route::post('/batches/{batch}/allocations', [PrintController::class, 'allocate']);

/* 场次 */
Route::get('/performances', [PerformanceController::class, 'index']);
Route::post('/performances', [PerformanceController::class, 'store']);
Route::get('/performances/{performance}', [PerformanceController::class, 'show']);
Route::post('/performances/{performance}/confirm', [PerformanceController::class, 'confirm']);
Route::post('/performances/{performance}/issues', [PerformanceController::class, 'issue']);
Route::post('/performances/{performance}/counts', [PerformanceController::class, 'count']);
Route::post('/performances/{performance}/close', [PerformanceController::class, 'close']);

/* 入口调拨（调出/调入同时记账） */
Route::post('/versions/{version}/transfers', [TransferController::class, 'store']);

/* 错版报废审批 */
Route::get('/scraps', [ScrapController::class, 'index']);
Route::post('/versions/{version}/scraps', [ScrapController::class, 'store']);
Route::post('/scraps/{scrap}/review', [ScrapController::class, 'review']);

/* 印厂本地模拟接口 */
Route::get('/mock-printer/receipts', [MockPrinterController::class, 'receipts']);

/* 入口看板与库存核对 */
Route::get('/board', [BoardController::class, 'index']);
Route::get('/reconciliation', [ReconciliationController::class, 'index']);
