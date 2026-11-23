<?php
/**
 * GodAnime Main Router
 * 
 * Runs 100% on GodAnime's custom Black + Orange frontend & streaming engine,
 * direct home page routing, 0 mentions of ReAnime, anti-ad shield,
 * and private local user & comment system.
 */

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = __DIR__;
}

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/src/api/domain_manager.php';
require_once __DIR__ . '/src/api/reanime_proxy.php';

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = urldecode(parse_url($rawUri, PHP_URL_PATH) ?? '/');
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$requestedFile = $docRoot . '/' . ltrim($uriPath, '/\\');

if (!file_exists($requestedFile)) {
    $requestedFile = __DIR__ . '/' . ltrim($uriPath, '/\\');
}

// 0. Direct Service Worker & Manifest handling (essential for mobile Chrome / PWA)
if ($uriPath === '/sw.js' || $uriPath === '/service-worker.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile(__DIR__ . '/sw.js');
    exit;
}
if ($uriPath === '/manifest.json') {
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile(__DIR__ . '/manifest.json');
    exit;
}

// 0. Direct Home Redirection: Bypasses the splash search screen and leaderboard completely
if ($uriPath === '/' || $uriPath === '' || $uriPath === '/home.php' || $uriPath === '/index.php' || $uriPath === '/leaderboard' || strpos($uriPath, '/leaderboard') === 0) {
    header('Location: /home', true, 302);
    exit;
}

// 1. Static file serving (CSS, JS, Images, Fonts, Logos in public/)
if ($uriPath !== '/' && $uriPath !== '' && file_exists($requestedFile) && !is_dir($requestedFile)) {
    if (!preg_match('/\.php$/i', $requestedFile)) {
        if (php_sapi_name() === 'cli-server') {
            return false;
        }
        $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'xml'   => 'application/xml',
            'txt'   => 'text/plain',
        ];
        $contentType = $mimes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        header('Access-Control-Allow-Origin: *');
        readfile($requestedFile);
        exit;
    }
}

$uri = trim($uriPath, '/');

// 2. Local Comment & Ajax Endpoints (100% your own comments and voting)
if (preg_match('#^(?:src/)?ajax/comment(?:/(.*))?$#i', $uri, $m)) {
    $_GET['slug'] = $m[1] ?? '';
    $_GET['__path'] = $m[1] ?? '';
    include __DIR__ . '/src/ajax/comment/index.php';
    exit;
}
if (preg_match('#^(?:src/)?ajax/([a-zA-Z0-9_\-]+)(?:\.php)?$#i', $uri, $m)) {
    $file = __DIR__ . '/src/ajax/' . $m[1] . '.php';
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

// 3. Private Local User Pages (Your users only)
$userRoutes = [
    'login'             => 'src/user/login.php',
    'register'          => 'src/user/register.php',
    'logout'            => 'src/user/logout.php',
    'watchlist'         => 'src/user/watchlist.php',
    'changepass'        => 'src/user/changepass.php',
    'continue-watching' => 'src/user/continue-watching.php',
];

if (isset($userRoutes[$uri])) {
    $fullPath = __DIR__ . '/' . $userRoutes[$uri];
    if (file_exists($fullPath)) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        include $fullPath;
        exit;
    }
}

// 4. Delegate ALL streaming, pages, and assets to GodAnime's Rebranded Proxy Engine
reanime_proxy_serve();
