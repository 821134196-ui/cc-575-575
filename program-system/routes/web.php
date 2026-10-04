<?php

use Illuminate\Support\Facades\Route;

// 所有非 API 页面交给 React SPA（含 /board、/scraps 等前端路由深链）
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api|up|build).*$');
