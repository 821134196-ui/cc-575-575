<?php

use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MockPrinterController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PrintBatchController;
use App\Http\Controllers\Api\ReconcileController;
use App\Http\Controllers\Api\VersionController;
use Illuminate\Support\Facades\Route;

// 印厂回执 —— 本地模拟接口
Route::post('/mock/printer/receipt', [MockPrinterController::class, 'receipt']);

Route::get('/entrances', fn () => \App\Models\Entrance::orderBy('code')->get(['id', 'code', 'name']));

// 场次
Route::apiResource('performances', PerformanceController::class)->only(['index', 'show', 'store']);

// 版本：锁定 / 锁定后修改 / 新建版本
Route::patch('/versions/{version}', [VersionController::class, 'update']);
Route::post('/versions/{version}/lock', [VersionController::class, 'lock']);
Route::post('/versions/{version}/revision', [VersionController::class, 'revision']);

// 印刷批次：下单 / 交货（印厂回执）
Route::post('/versions/{version}/print-batches', [PrintBatchController::class, 'store']);
Route::post('/print-batches/{batch}/deliver', [PrintBatchController::class, 'deliver']);

// 库存动作
Route::post('/print-batches/{batch}/allocate', [InventoryController::class, 'allocate']);
Route::post('/entrances/{entrance}/issue', [InventoryController::class, 'issue']);
Route::post('/transfers', [InventoryController::class, 'transfer']);

// 错版报废审批
Route::get('/scraps', [InventoryController::class, 'scrapIndex']);
Route::post('/scraps', [InventoryController::class, 'requestScrap']);
Route::post('/scraps/{scrapRequest}/approve', [InventoryController::class, 'approveScrap']);
Route::post('/scraps/{scrapRequest}/reject', [InventoryController::class, 'rejectScrap']);

// 散场盘点
Route::post('/performances/{performance}/counts/snapshot', [InventoryController::class, 'countSnapshot']);
Route::patch('/counts/{stockCount}', [InventoryController::class, 'countSubmit']);

// 入口看板
Route::get('/performances/{performance}/entrances/{entrance}/board', [BoardController::class, 'show']);

// 核对与追溯
Route::get('/performances/{performance}/reconcile', [ReconcileController::class, 'performance']);
Route::get('/performances/{performance}/movements', [ReconcileController::class, 'movements']);
