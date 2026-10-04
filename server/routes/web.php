<?php

use Illuminate\Support\Facades\Route;

// SPA 入口：所有非 /api 路径交给 React Router。
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api/).*$');
