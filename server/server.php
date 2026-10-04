<?php

/**
 * PHP 内置服务器路由：静态文件直接返回，其余交给 Laravel。
 * 用法：php -S 127.0.0.1:8000 server.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($file)) {
    return false; // 让内置服务器直接输出静态文件（含正确 MIME）
}

require __DIR__ . '/public/index.php';
