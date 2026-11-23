<?php
/**
 * GodAnime Proxy & Mirror Engine
 * 
 * Completely mirrors the streaming platform with custom GodAnime branding,
 * sleek Black + Orange color theme, direct home page routing,
 * 100% private local user & comment system (replaces stranger community/comments),
 * and COMPLETE STRIPPING OF REANIME'S MONETAG/ADCASH/ADMAVEN POPUNDERS & ADS.
 */

require_once __DIR__ . '/domain_manager.php';
require_once dirname(__DIR__, 2) . '/_config.php';

function strip_popunder_script(string $html): string
{
    $markers = ['AD_NETWORK', 'monetag', 'admaven', 'adcash', 'llvpn.com', 'acscdn.com'];
    foreach ($markers as $marker) {
        while (($pos = strpos($html, $marker)) !== false) {
            $tagStart = strrpos(substr($html, 0, $pos), '<script');
            if ($tagStart === false) break;
            $tagEnd = strpos($html, '</script>', $pos);
            if ($tagEnd === false) break;
            $html = substr($html, 0, $tagStart) . '<script>window.__project_r_ads=function(){};</script>' . substr($html, $tagEnd + 9);
        }
    }
    return $html;
}

function reanime_proxy_serve(): void
{
    global $conn;

    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : dirname(__DIR__, 2);
    $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
    $uriPath = parse_url($rawUri, PHP_URL_PATH) ?? '/';
    $queryString = $_SERVER['QUERY_STRING'] ?? '';

    // Handle OPTIONS preflight
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(204);
        exit;
    }

    // Resolve logged in GodAnime user
    $userId = isset($_COOKIE['userID']) ? (int)$_COOKIE['userID'] : (isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0);
    $currentUser = null;
    if ($userId > 0 && isset($conn) && $conn) {
        $stmt = $conn->prepare('SELECT id, username, email, image FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $currentUser = [
                    'id' => (string)$row['id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'pfp' => !empty($row['image']) ? $row['image'] : '/public/images/no-avatar.jpeg',
                    'role' => 'user',
                    'displayName' => $row['username'],
                    'bio' => '',
                    'website' => '',
                    'avatarFrame' => null,
                    'nameplate' => null,
                    'banner' => null,
                    'emailVerified' => true,
                ];
            }
        }
    }

    if (!function_exists('reanime_devalue_encode')) {
        function reanime_devalue_encode($root) {
            $flat = [];
            $pool = [];

            $serialize = function($val) use (&$flat, &$pool, &$serialize) {
                if ($val === null) {
                    if (isset($pool['__null__'])) return $pool['__null__'];
                    $idx = count($flat);
                    $flat[$idx] = null;
                    $pool['__null__'] = $idx;
                    return $idx;
                }
                if ($val === true) {
                    if (isset($pool['__true__'])) return $pool['__true__'];
                    $idx = count($flat);
                    $flat[$idx] = true;
                    $pool['__true__'] = $idx;
                    return $idx;
                }
                if ($val === false) {
                    if (isset($pool['__false__'])) return $pool['__false__'];
                    $idx = count($flat);
                    $flat[$idx] = false;
                    $pool['__false__'] = $idx;
                    return $idx;
                }
                if (is_int($val) || is_float($val)) {
                    $k = 'num_' . $val;
                    if (isset($pool[$k])) return $pool[$k];
                    $idx = count($flat);
                    $flat[$idx] = $val;
                    $pool[$k] = $idx;
                    return $idx;
                }
                if (is_string($val)) {
                    $k = 'str_' . $val;
                    if (isset($pool[$k])) return $pool[$k];
                    $idx = count($flat);
                    $flat[$idx] = $val;
                    $pool[$k] = $idx;
                    return $idx;
                }
                if (is_array($val)) {
                    $isSeq = array_keys($val) === range(0, count($val) - 1);
                    $idx = count($flat);
                    $flat[$idx] = [];
                    if ($isSeq) {
                        $arrRefs = [];
                        foreach ($val as $item) {
                            $arrRefs[] = $serialize($item);
                        }
                        $flat[$idx] = $arrRefs;
                    } else {
                        $objRefs = [];
                        foreach ($val as $k => $v) {
                            $objRefs[$k] = $serialize($v);
                        }
                        $flat[$idx] = $objRefs;
                    }
                    return $idx;
                }
                return null;
            };

            $serialize($root);
            return $flat;
        }
    }

    // 0. Direct Home Redirection & Leaderboard Removal
    if ($uriPath === '/' || $uriPath === '' || $uriPath === '/leaderboard' || strpos($uriPath, '/leaderboard') === 0) {
        header('Location: /home', true, 302);
        exit;
    }

    // 1. Local GodAnime Logo (Black + Orange Signature Theme)
    if (in_array($uriPath, ['/logo-480.png', '/logo-480.webp', '/logo-256.webp', '/godanime-logo.svg', '/og.png'], true)) {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=31536000');
        echo file_get_contents($docRoot . '/public/logo/godanime_logo.svg');
        exit;
    }

    // 2. Local Favicons & Icons
    if (in_array($uriPath, ['/favicon.ico', '/favicon-32x32.png', '/favicon-16x16.png', '/apple-touch-icon.png'], true)) {
        $favPath = $docRoot . '/public/logo' . $uriPath;
        if (file_exists($favPath)) {
            $ext = strtolower(pathinfo($favPath, PATHINFO_EXTENSION));
            $mime = ($ext === 'ico') ? 'image/x-icon' : 'image/png';
            header("Content-Type: $mime");
            header('Cache-Control: public, max-age=31536000');
            readfile($favPath);
            exit;
        }
    }

    // Analytics dummy response
    if ($uriPath === '/pv.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        echo '// pv ok';
        exit;
    }

    // 3. YOUR OWN LOCAL USER & AUTH API (Replaces ReAnime users with GodAnime users)
    if ($uriPath === '/api/v1/auth/login') {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $identifier = trim($input['identifier'] ?? ($input['login'] ?? ($input['username'] ?? ($input['email'] ?? ''))));
        $password = (string)($input['password'] ?? '');

        if ($identifier === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide both username/email and password.']);
            exit;
        }

        if (isset($conn) && $conn) {
            $stmt = $conn->prepare('SELECT id, username, email, password, image FROM users WHERE username = ? OR email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $identifier, $identifier);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if (password_verify($password, $row['password'])) {
                        $uid = (int)$row['id'];
                        setcookie('userID', (string)$uid, time() + 31536000, '/', '', false, false);
                        if (session_status() !== PHP_SESSION_ACTIVE) {
                            @session_start();
                        }
                        $_SESSION['userID'] = $uid;

                        echo json_encode([
                            'success' => true,
                            'user_id' => $uid,
                            'username' => $row['username'],
                            'pfp' => !empty($row['image']) ? $row['image'] : '/public/images/no-avatar.jpeg',
                            'role' => 'user',
                            'private_key' => null,
                        ]);
                        exit;
                    }
                }
            }
        }

        http_response_code(401);
        echo json_encode(['error' => 'Incorrect username/email or password.']);
        exit;
    }

    if ($uriPath === '/api/v1/auth/signup') {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $username = trim($input['username'] ?? ($input['name'] ?? ''));
        $email = trim($input['email'] ?? '');
        $password = (string)($input['password'] ?? '');

        if ($username === '' || $email === '' || strlen($password) < 4) {
            http_response_code(400);
            echo json_encode(['error' => 'Please provide a valid username, email, and password (at least 4 chars).']);
            exit;
        }

        if (isset($conn) && $conn) {
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            if ($checkStmt) {
                $checkStmt->bind_param('ss', $username, $email);
                $checkStmt->execute();
                if ($checkStmt->get_result()->fetch_assoc()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'A user with that username or email already exists.']);
                    exit;
                }
            }

            $maxRes = $conn->query('SELECT MAX(id) as max_id FROM users');
            $maxRow = $maxRes ? $maxRes->fetch_assoc() : null;
            $newId = ($maxRow && isset($maxRow['max_id']) ? (int)$maxRow['max_id'] : 0) + 1;
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pfp = '/public/images/no-avatar.jpeg';

            $insStmt = $conn->prepare("INSERT INTO users (id, username, email, password, image, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
            if ($insStmt) {
                $insStmt->bind_param('issss', $newId, $username, $email, $hash, $pfp);
                if ($insStmt->execute()) {
                    setcookie('userID', (string)$newId, time() + 31536000, '/', '', false, false);
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        @session_start();
                    }
                    $_SESSION['userID'] = $newId;

                    echo json_encode([
                        'success' => true,
                        'user_id' => $newId,
                        'username' => $username,
                        'pfp' => $pfp,
                        'role' => 'user',
                        'private_key' => null,
                    ]);
                    exit;
                }
            }
        }

        http_response_code(500);
        echo json_encode(['error' => 'Registration failed, please try again.']);
        exit;
    }

    if (in_array($uriPath, ['/api/v1/auth/forgot-password', '/api/v1/auth/reset-password'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Password reset is disabled for local accounts. Contact administrator if needed.']);
        exit;
    }

    if ($uriPath === '/api/v1/user') {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-cache, no-store');

        $userId = isset($_COOKIE['userID']) ? (int)$_COOKIE['userID'] : (isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0);
        if ($userId > 0 && isset($conn) && $conn) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'PATCH' || $method === 'POST') {
                $rawInput = file_get_contents('php://input');
                $input = json_decode($rawInput, true);
                if (is_array($input)) {
                    if (isset($input['pfp'])) {
                        $pfpVal = $conn->real_escape_string($input['pfp']);
                        $conn->query("UPDATE users SET image = '$pfpVal' WHERE id = $userId");
                    }
                    if (isset($input['username']) && !empty($input['username'])) {
                        $unameVal = $conn->real_escape_string($input['username']);
                        $conn->query("UPDATE users SET username = '$unameVal' WHERE id = $userId");
                    }
                }
            }

            $stmt = $conn->prepare('SELECT id, username, email, image FROM users WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    echo json_encode([
                        'success' => true,
                        'user' => [
                            'id' => (string)$row['id'],
                            'username' => $row['username'],
                            'email' => $row['email'],
                            'pfp' => !empty($row['image']) ? $row['image'] : '/public/images/no-avatar.jpeg',
                            'role' => 'user',
                            'avatarFrame' => null,
                            'nameplate' => null,
                            'banner' => null,
                            'bio' => '',
                            'website' => '',
                            'displayName' => $row['username'],
                            'emailVerified' => true,
                        ]
                    ]);
                    exit;
                }
            }
        }

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated', 'user' => null]);
        exit;
    }

    if ($uriPath === '/api/v1/user/settings') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'settings' => [
                'defaultLang' => false,
                'skipIntro' => false,
                'skipOutro' => false,
                'autoNext' => false,
                'autoPlay' => false,
                'showComments' => true,
                'publicWatchlist' => true,
                'syncPercentage' => 80,
                'notifLang' => 'all',
                'notifFolders' => ['WATCHING', 'REWATCHING', 'PLANNING', 'PAUSED'],
                'watchlistVisibility' => 'public',
                'watchingVisibility' => 'public',
                'notifFriends' => true,
                'friendActivity' => false,
                'notifNews' => true,
            ]
        ]);
        exit;
    }

    if ($uriPath === '/api/v1/user/password') {
        header('Content-Type: application/json; charset=utf-8');
        $userId = isset($_COOKIE['userID']) ? (int)$_COOKIE['userID'] : (isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0);
        if ($userId > 0 && isset($conn) && $conn) {
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: $_POST;
            $currentPass = $input['currentPassword'] ?? '';
            $newPass = $input['newPassword'] ?? '';
            if (empty($newPass)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'New password cannot be empty']);
                exit;
            }
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && password_verify($currentPass, $row['password'])) {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $upStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upStmt->bind_param("si", $newHash, $userId);
                $upStmt->execute();
                echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
                exit;
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Incorrect current password']);
                exit;
            }
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    if ($uriPath === '/api/v1/user/email') {
        header('Content-Type: application/json; charset=utf-8');
        $userId = isset($_COOKIE['userID']) ? (int)$_COOKIE['userID'] : (isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0);
        if ($userId > 0 && isset($conn) && $conn) {
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: $_POST;
            $newEmail = $input['newEmail'] ?? '';
            if (!empty($newEmail)) {
                $upStmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $upStmt->bind_param("si", $newEmail, $userId);
                $upStmt->execute();
                echo json_encode(['success' => true, 'message' => 'Email updated successfully!']);
                exit;
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to update email']);
        exit;
    }

    if (in_array($uriPath, ['/api/v1/frames', '/api/v1/nameplates', '/api/v1/profile-effects'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'categories' => [], 'nameplates' => [], 'effects' => []]);
        exit;
    }

    if ($uriPath === '/api/v1/shop') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'wallet' => ['available' => 1000], 'owned' => [], 'seasonal' => []]);
        exit;
    }

    if ($uriPath === '/api/v1/user/pfp-token') {
        header('Content-Type: application/json; charset=utf-8');
        $userId = isset($_COOKIE['userID']) ? (int)$_COOKIE['userID'] : (isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0);
        echo json_encode(['success' => true, 'token' => 'ga_' . $userId, 'username' => ($currentUser['username'] ?? 'user')]);
        exit;
    }

    if (in_array($uriPath, ['/api/v1/user/anilist/info', '/api/v1/user/mal/info'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false]);
        exit;
    }

    if (strpos($uriPath, '/api/v1/notifications') === 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'count' => 0, 'unread' => 0, 'notifications' => []]);
        exit;
    }

    // Local Logout endpoint
    if ($uriPath === '/api/v1/auth/logout') {
        setcookie('userID', '', time() - 3600, '/');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }

    // 4. Block Leaderboard, Community, Stranger Chat, and Public Stranger Comments
    if (preg_match('#^/(?:api/(?:v1/)?(?:community|chat|comments?|social|friends?|feed|notifications|leaderboard|ranking|levels?)|community|leaderboard)#i', $uriPath)) {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'items' => [], 'data' => [], 'comments' => [], 'messages' => [], 'notifications' => [], 'ranking' => []]);
        exit;
    }

    // 5. Local static files in public/
    if (strpos($uriPath, '/public/') === 0) {
        $localPath = $docRoot . $uriPath;
        if (file_exists($localPath) && !is_dir($localPath)) {
            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $mimes = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'css' => 'text/css',
                'js' => 'application/javascript',
            ];
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Cache-Control: public, max-age=31536000');
            readfile($localPath);
            exit;
        }
    }

    $activeDomain = reanime_get_domain(false);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                ? "https" : "http";
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost:8000'));
    $localOrigin = "{$protocol}://{$host}";

    // Asset path normalization (handles nested relative asset links like /watch/assets/... or /home/assets/...)
    $normalizedAssetPath = $uriPath;
    if (preg_match('#/(assets/.*)$#', $uriPath, $m)) {
        $normalizedAssetPath = '/' . $m[1];
    } elseif (preg_match('#/(res/.*)$#', $uriPath, $m)) {
        $normalizedAssetPath = '/' . $m[1];
    }

    // Clean versioned chunk paths to real upstream asset paths (e.g. DqVyHEVv.v2.js -> DqVyHEVv.js)
    $cleanUpstreamAssetPath = preg_replace('/\.v\d+\.js$/i', '.js', $normalizedAssetPath);

    // 6. Static Asset Proxy & Cache (Transforms CSS to Black + Orange AND JS chunks to 100% GodAnime)
    $isAsset = preg_match('/\.(?:js|css|woff2?|ttf|eot|png|jpe?g|webp|svg|ico|wasm|map)$/i', $normalizedAssetPath) ||
               strpos($normalizedAssetPath, '/assets/') === 0 ||
               strpos($normalizedAssetPath, '/res/') === 0;

    if ($isAsset) {
        $targetAssetUrl = rtrim($activeDomain, '/') . $cleanUpstreamAssetPath;
        $cacheDir = $docRoot . '/cache/reanime_assets';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir . '/' . md5($cleanUpstreamAssetPath) . '.' . strtolower(pathinfo($cleanUpstreamAssetPath, PATHINFO_EXTENSION) ?: 'asset');

        $mimeMap = [
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'json' => 'application/json',
            'wasm' => 'application/wasm',
        ];
        $ext = strtolower(pathinfo($cleanUpstreamAssetPath, PATHINFO_EXTENSION));
        $resolvedMime = $mimeMap[$ext] ?? 'application/octet-stream';

        // Check cache file
        if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
            $cachedData = file_get_contents($cacheFile);
            if (stripos($cachedData, 'Re:ANIME') === false && stripos($cachedData, 'reanime.cz') === false) {
                header('Content-Type: ' . $resolvedMime);
                header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
                header('Access-Control-Allow-Origin: *');
                echo $cachedData;
                exit;
            }
        }

        $ch = curl_init($targetAssetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            CURLOPT_HTTPHEADER => [
                'Referer: ' . rtrim($activeDomain, '/') . '/',
                'Accept: */*'
            ],
        ]);
        $assetData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode === 200 && $assetData !== false) {
            // Transform CSS: replace green OKLCH and green hexes with Black + Orange palette
            if ($ext === 'css') {
                $assetData = str_replace(
                    [
                        '--primary:oklch(84.1% .238 128.85)',
                        '--primary:oklch(76.8% .233 130.85)',
                        '--primary-foreground:oklch(40.5% .101 131.063)',
                        '#a3e635',
                        '#10b981',
                        '#22c55e',
                        '#84cc16',
                        '--tw-ring-color:var(--primary)',
                    ],
                    [
                        '--primary:#FF6B00',
                        '--primary:#FF7A1A',
                        '--primary-foreground:#FFFFFF',
                        '#FF6B00',
                        '#FF7A1A',
                        '#FF6B00',
                        '#EA580C',
                        '--tw-ring-color:#FF6B00',
                    ],
                    $assetData
                );
            }

            // Transform JS chunks: replace Re:ANIME, footer copyright, titles, domains, external links
            if ($ext === 'js') {
                $parsedActive = parse_url($activeDomain);
                $activeHost = $parsedActive['host'] ?? 'reanime.cz';
                $assetData = str_replace(
                    [
                        'Re:ANIME. All rights reserved.',
                        'Re:ANIME',
                        'RE:ANIME',
                        're:anime',
                        'ReAnime',
                        'https://www.reddit.com/r/reanime',
                        'https://discord.com/invite/SMNYA4Q6vD',
                        'https://restatus.me',
                        'https://' . $activeHost,
                        'http://' . $activeHost,
                        '//' . $activeHost,
                        'https://reanime.cz',
                        'http://reanime.cz',
                        '//reanime.cz',
                        'https://reanime.to',
                        'http://reanime.to',
                        '//reanime.to',
                        'https://reanime.tv',
                        'http://reanime.tv',
                        '//reanime.tv',
                        'https://reanime.is',
                        'http://reanime.is',
                        '//reanime.is',
                        'https://reanime.cc',
                        'http://reanime.cc',
                        '//reanime.cc',
                        'https://reanime.app',
                        'http://reanime.app',
                        '//reanime.app',
                        // Bust browser disk cache for DqVyHEVv chunk
                        'DqVyHEVv.js',
                        'navigator.serviceWorker.register("/sw.js")',
                        'navigator.serviceWorker.register(\'/sw.js\')',
                    ],
                    [
                        'GodAnime. All rights reserved.',
                        'GodAnime',
                        'GodAnime',
                        'GodAnime',
                        'GodAnime',
                        '#',
                        '#',
                        '#',
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        $localOrigin,
                        $localOrigin,
                        '//' . $host,
                        'DqVyHEVv.v2.js',
                        '(Promise.resolve())',
                        '(Promise.resolve())',
                    ],
                    $assetData
                );
            }

            @file_put_contents($cacheFile, $assetData);
            header('Content-Type: ' . ($contentType ?: $resolvedMime));
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Access-Control-Allow-Origin: *');
            echo $assetData;
            exit;
        }
    }

    $upstreamPath = $uriPath;
    if ($uriPath === '/__data.json') {
        $upstreamPath = '/home/__data.json';
    }

    $targetUrl = rtrim($activeDomain, '/') . $upstreamPath . ($queryString !== '' ? '?' . $queryString : '');

    // 7. API and SvelteKit Data Routes
    $isApiOrData = strpos($uriPath, '/api/') === 0 ||
                   substr($uriPath, -11) === '__data.json' ||
                   strpos($uriPath, '__data.json') !== false;

    if ($isApiOrData) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $ch = curl_init($targetUrl);
        
        $reqHeaders = [
            'Accept: ' . ($_SERVER['HTTP_ACCEPT'] ?? 'application/json, text/plain, */*'),
            'Referer: ' . rtrim($activeDomain, '/') . '/',
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
        ];
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $reqHeaders[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $reqHeaders[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => $reqHeaders,
        ]);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $input = file_get_contents('php://input');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
        }

        $apiRes = curl_exec($ch);
        $apiCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $apiType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $dataCacheDir = $docRoot . '/cache/reanime_data';
        if (!is_dir($dataCacheDir)) @mkdir($dataCacheDir, 0777, true);
        $dataCacheFile = $dataCacheDir . '/' . md5($upstreamPath) . '.json';

        $isData500 = (!$apiRes || $apiCode >= 500 || (strlen($apiRes) < 500 && stripos($apiRes, 'error') !== false));

        if (!$isData500 && $apiCode === 200 && strlen($apiRes) > 100) {
            @file_put_contents($dataCacheFile, $apiRes);
        } elseif ($isData500 && strpos($uriPath, '__data.json') !== false) {
            // Try fetching from backup mirror domain
            $backupDomain = (strpos($activeDomain, 'reanime.cz') !== false) ? 'https://reanime.to' : 'https://reanime.cz';
            $backupUrl = rtrim($backupDomain, '/') . $upstreamPath . ($queryString !== '' ? '?' . $queryString : '');
            $bch = curl_init($backupUrl);
            curl_setopt_array($bch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => $reqHeaders,
            ]);
            $bRes = curl_exec($bch);
            $bCode = curl_getinfo($bch, CURLINFO_HTTP_CODE);
            curl_close($bch);

            if ($bCode === 200 && $bRes && strlen($bRes) > 100) {
                $apiRes = $bRes;
                $apiCode = 200;
                @file_put_contents($dataCacheFile, $apiRes);
            } elseif (file_exists($dataCacheFile)) {
                $apiRes = file_get_contents($dataCacheFile);
                $apiCode = 200;
            }
        }

        http_response_code($apiCode ?: 200);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        if ($apiType) header('Content-Type: ' . $apiType);

        // Rewrite branding, disable community chat, and strip 3rd party ad links in JSON
        if ($apiRes && (strpos($apiType, 'json') !== false || strpos($apiType, 'text') !== false)) {
            $parsedActive = parse_url($activeDomain);
            $activeHost = $parsedActive['host'] ?? 'reanime.cz';
            $apiRes = str_replace(
                [
                    'Re:ANIME', 're:anime', 'RE:ANIME', 'ReAnime',
                    $activeDomain, 'https://' . $activeHost, 'http://' . $activeHost, '//' . $activeHost,
                    'globalChatOff:false', 'sidebarOff:false', 'sidebarHidden:false',
                    '"globalChatOff":false', '"sidebarOff":false', '"sidebarHidden":false',
                    'reanime.cz', 'reanime.to', 'reanime.tv', 'reanime.is', 'reanime.cc', 'reanime.app',
                    'reanime',
                    'https://restatus.me',
                    'http://www.crunchyroll.com',
                    'https://one-piece.com',
                    'http://www.hulu.com',
                    'https://www.netflix.com',
                    'https://twitter.com',
                    'https://www.instagram.com',
                    'https://www.youtube.com',
                    'https://www.tiktok.com',
                ],
                [
                    'GodAnime', 'GodAnime', 'GodAnime', 'GodAnime',
                    $localOrigin, $localOrigin, $localOrigin, '//' . $host,
                    'globalChatOff:true', 'sidebarOff:true', 'sidebarHidden:true',
                    '"globalChatOff":true', '"sidebarOff":true', '"sidebarHidden":true',
                    $host, $host, $host, $host, $host, $host,
                    'GodAnime',
                    '#',
                    '#',
                    '#',
                    '#',
                    '#',
                    '#',
                    '#',
                    '#',
                    '#',
                ],
                $apiRes
            );

            // Inject authenticated local user & privacy settings into SvelteKit SSR state in __data.json
            if (strpos($uriPath, '__data.json') !== false) {
                $jsonData = json_decode($apiRes, true);
                if ($jsonData && isset($jsonData['nodes']) && is_array($jsonData['nodes'])) {
                    // 1. Root layout node (nodes[0])
                    if (isset($jsonData['nodes'][0]['data']) && is_array($jsonData['nodes'][0]['data'])) {
                        $d = &$jsonData['nodes'][0]['data'];
                        if (isset($d[0]) && is_array($d[0])) {
                            $trueIdx = count($d);
                            $d[$trueIdx] = true;
                            $d[0]['globalChatOff'] = $trueIdx;
                            $d[0]['sidebarOff'] = $trueIdx;
                            $d[0]['sidebarHidden'] = $trueIdx;

                            if ($currentUser) {
                                $idIdx = count($d);
                                $d[$idIdx] = (string)$currentUser['id'];
                                $userIdx = count($d);
                                $d[$userIdx] = (string)$currentUser['username'];
                                $emailIdx = count($d);
                                $d[$emailIdx] = (string)$currentUser['email'];
                                $pfpIdx = count($d);
                                $d[$pfpIdx] = (string)$currentUser['pfp'];
                                $roleIdx = count($d);
                                $d[$roleIdx] = (string)$currentUser['role'];

                                $userMapIdx = count($d);
                                $d[$userMapIdx] = [
                                    'id' => $idIdx,
                                    'username' => $userIdx,
                                    'email' => $emailIdx,
                                    'pfp' => $pfpIdx,
                                    'role' => $roleIdx,
                                    'avatarFrame' => 2,
                                    'nameplate' => 2,
                                ];

                                $d[0]['isAuthed'] = $trueIdx;
                                $d[0]['sessionUser'] = $userMapIdx;
                            }
                        }
                    }

                    // 2. Profile / Settings node (nodes[1] or any node containing user & settings)
                    if ($currentUser) {
                        for ($ni = 1; $ni < count($jsonData['nodes']); $ni++) {
                            if (isset($jsonData['nodes'][$ni]['data']) && is_array($jsonData['nodes'][$ni]['data'])) {
                                $nData = $jsonData['nodes'][$ni]['data'];
                                if (isset($nData[0]) && is_array($nData[0]) && array_key_exists('user', $nData[0])) {
                                    $profilePayload = [
                                        'user' => [
                                            'id' => (string)$currentUser['id'],
                                            'username' => $currentUser['username'],
                                            'email' => $currentUser['email'],
                                            'pfp' => $currentUser['pfp'],
                                            'role' => 'user',
                                            'avatarFrame' => null,
                                            'nameplate' => null,
                                            'banner' => null,
                                            'bio' => '',
                                            'website' => '',
                                            'displayName' => $currentUser['username'],
                                            'emailVerified' => true,
                                        ],
                                        'settings' => [
                                            'defaultLang' => false,
                                            'skipIntro' => false,
                                            'skipOutro' => false,
                                            'autoNext' => false,
                                            'autoPlay' => false,
                                            'showComments' => true,
                                            'publicWatchlist' => true,
                                            'syncPercentage' => 80,
                                            'notifLang' => 'all',
                                            'notifFolders' => ['WATCHING', 'REWATCHING', 'PLANNING', 'PAUSED'],
                                            'watchlistVisibility' => 'public',
                                            'watchingVisibility' => 'public',
                                            'notifFriends' => true,
                                            'friendActivity' => false,
                                            'notifNews' => true,
                                        ]
                                    ];
                                    $jsonData['nodes'][$ni]['data'] = reanime_devalue_encode($profilePayload);
                                }
                            }
                        }
                    }

                    $apiRes = json_encode($jsonData);
                }
            }
        }

        echo $apiRes;
        exit;
    }

    // 8. Page Proxy (HTML)
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        CURLOPT_HTTPHEADER => [
            'Referer: ' . rtrim($activeDomain, '/') . '/',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html || $httpCode >= 500 || (strlen($html) < 5000 && stripos($html, 'Internal Error') !== false)) {
        $activeDomain = reanime_get_domain(true);
        $targetUrl = rtrim($activeDomain, '/') . $upstreamPath . ($queryString !== '' ? '?' . $queryString : '');
        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    $pageCacheDir = $docRoot . '/cache/reanime_pages';
    if (!is_dir($pageCacheDir)) @mkdir($pageCacheDir, 0777, true);
    $pageCacheFile = $pageCacheDir . '/' . md5($upstreamPath) . '.html';

    $isPageBroken = (!$html || $httpCode >= 500 || (strlen($html) < 5000 && stripos($html, 'Internal Error') !== false));

    if (!$isPageBroken && $httpCode === 200 && strlen($html) > 5000) {
        @file_put_contents($pageCacheFile, $html);
    } elseif ($isPageBroken) {
        if (file_exists($pageCacheFile)) {
            $html = file_get_contents($pageCacheFile);
            $httpCode = 200;
        } elseif (file_exists($docRoot . '/cache/reanime_home_sample.html')) {
            $html = file_get_contents($docRoot . '/cache/reanime_home_sample.html');
            $httpCode = 200;
        }
    }

    http_response_code($httpCode ?: 200);
    // Explicitly prevent browser caching of HTML (no Clear-Site-Data to prevent phone Chrome subresource aborts)
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: text/html; charset=utf-8');

    if ($html) {
        // Strip out ReAnime's Monetag, AdMaven, and Adcash popunder script blocks immediately
        $html = strip_popunder_script($html);

        $parsedActive = parse_url($activeDomain);
        $activeHost = $parsedActive['host'] ?? 'reanime.cz';

        // 1. Comprehensive Re:ANIME -> GodAnime Rebranding across HTML
        $html = str_replace(
            [
                'Re:ANIME — Stream Anime Free Online',
                'Watch Latest Anime Episodes Free in HD | Re:ANIME',
                'Watch ONE PIECE Episode 1 Online Free | Re:ANIME',
                'Watch the latest anime episodes free on Re:ANIME',
                'Stream in HD with English subs and dubs on reanime.to.',
                'New anime episodes every day on Re:ANIME.',
                '© 2026 Re:ANIME. All rights reserved.',
                'Re:ANIME does not store any files',
                'About Re:ANIME',
                'Why Re:ANIME?',
                'Re:ANIME',
                'RE:ANIME',
                're:anime',
                'ReAnime',
                'stats.reanime.to',
                'https://www.reddit.com/r/reanime',
                'https://discord.com/invite/SMNYA4Q6vD',
                'https://restatus.me',
                $activeDomain,
                'https://' . $activeHost,
                'http://' . $activeHost,
                '//' . $activeHost,
                'https://reanime.cz',
                'https://reanime.to',
                'https://reanime.tv',
                'https://reanime.is',
                'https://reanime.cc',
                'https://reanime.app',
                '//reanime.cz',
                '//reanime.to',
                'reanime.cz',
                'reanime.to',
                'reanime.tv',
                'reanime.is',
                'reanime.cc',
                'reanime.app',
                'reanime',
            ],
            [
                'GodAnime — Stream Anime Free Online in HD',
                'Watch Latest Anime Episodes Free in HD | GodAnime',
                'Watch ONE PIECE Episode 1 Online Free | GodAnime',
                'Watch the latest anime episodes free on GodAnime',
                'Stream in HD with English subs and dubs on GodAnime.',
                'New anime episodes every day on GodAnime.',
                '© 2026 GodAnime. All rights reserved.',
                'GodAnime does not store any files',
                'About GodAnime',
                'Why GodAnime?',
                'GodAnime',
                'GodAnime',
                'GodAnime',
                'GodAnime',
                '127.0.0.1',
                '#',
                '#',
                '#',
                $localOrigin,
                $localOrigin,
                $localOrigin,
                '//' . $host,
                $localOrigin,
                $localOrigin,
                $localOrigin,
                $localOrigin,
                $localOrigin,
                $localOrigin,
                '//' . $host,
                '//' . $host,
                $host,
                $host,
                $host,
                $host,
                $host,
                $host,
                'GodAnime',
            ],
            $html
        );

        // 2. Disable Community / Stranger Global Chat in SvelteKit SSR state & Sync Local Auth
        $html = str_replace(
            [
                'globalChatOff:false',
                'sidebarOff:false',
                'sidebarHidden:false',
                '"globalChatOff":false',
                '"sidebarOff":false',
                '"sidebarHidden":false',
            ],
            [
                'globalChatOff:true',
                'sidebarOff:true',
                'sidebarHidden:true',
                '"globalChatOff":true',
                '"sidebarOff":true',
                '"sidebarHidden":true',
            ],
            $html
        );

        if ($currentUser) {
            $isProfileRoute = strpos($uriPath, 'profile') !== false;
            if ($isProfileRoute) {
                $profileUserJson = json_encode([
                    'id' => (string)$currentUser['id'],
                    'username' => $currentUser['username'],
                    'email' => $currentUser['email'],
                    'pfp' => $currentUser['pfp'],
                    'role' => 'user',
                    'displayName' => $currentUser['username'],
                    'bio' => '',
                    'website' => '',
                    'avatarFrame' => null,
                    'nameplate' => null,
                    'banner' => null,
                    'emailVerified' => true,
                ]);
                $profileSettingsJson = json_encode([
                    'defaultLang' => false,
                    'skipIntro' => false,
                    'skipOutro' => false,
                    'autoNext' => false,
                    'autoPlay' => false,
                    'showComments' => true,
                    'publicWatchlist' => true,
                    'syncPercentage' => 80,
                    'notifLang' => 'all',
                    'notifFolders' => ['WATCHING', 'REWATCHING', 'PLANNING', 'PAUSED'],
                    'watchlistVisibility' => 'public',
                    'watchingVisibility' => 'public',
                    'notifFriends' => true,
                    'friendActivity' => false,
                    'notifNews' => true,
                ]);

                $html = str_replace(
                    [
                        'user:null,settings:null',
                        '"user":null,"settings":null',
                        'data:{user:null,settings:null}',
                        'data:{"user":null,"settings":null}',
                    ],
                    [
                        'user:' . $profileUserJson . ',settings:' . $profileSettingsJson,
                        '"user":' . $profileUserJson . ',"settings":' . $profileSettingsJson,
                        'data:{user:' . $profileUserJson . ',settings:' . $profileSettingsJson . '}',
                        'data:{"user":' . $profileUserJson . ',"settings":' . $profileSettingsJson . '}',
                    ],
                    $html
                );

                // Strip all SSR lock overlays & unblur content for authenticated GodAnime users
                $html = preg_replace('/<div class="login-overlay-wrap[^"]*".*?<\/button>\s*<\/div>\s*<\/div>/is', '', $html);
                $html = preg_replace('/<div class="lock-overlay[^"]*".*?<\/div>\s*<\/div>/is', '', $html);
                $html = str_replace(['blur-content', 'opacity-30'], '', $html);
            }
        }

        // 3. Complete Discord Banner & Redirection Notice Removal
        $html = preg_replace('/<div class="notice[^"]*"[^>]*>.*?<\/div>\s*<\/div>/is', '', $html);
        $html = preg_replace('/<div class="[^"]*notice[^"]*"[^>]*>.*?<\/div>/is', '', $html);

        // 4. Complete Leaderboard Removal
        $html = preg_replace('/<a[^>]*href=["\']\/leaderboard["\'][^>]*>.*?<\/a>/is', '', $html);

        // 4. Rewrite Root Links to /home (prevents SvelteKit client-router from hitting splash page)
        $html = preg_replace('/href=["\']\/["\']/', 'href="/home"', $html);

        // 4. Replace Logo sources with Black + Orange GodAnime Logo
        $html = str_replace(
            ['/logo-480.webp', '/logo-256.webp', '/logo-480.png', '/og.png'],
            ['/public/logo/godanime_logo.svg', '/public/logo/godanime_logo.svg', '/public/logo/godanime_logo.svg', '/public/logo/godanime_logo.svg'],
            $html
        );

        // Replace Favicon
        $html = preg_replace(
            '/<link[^>]*rel="icon"[^>]*>/i',
            '<link rel="icon" href="/public/logo/godanime_logo.svg" type="image/svg+xml"/>',
            $html
        );

        // Clean up cloudflare insights and analytics beacon
        $html = preg_replace('/<script[^>]*src="https:\/\/static\.cloudflareinsights\.com[^"]*"[^>]*><\/script>/is', '', $html);

        // 4b. Force cache-busting of entry bootstrap chunks in HTML so browser disk cache is bypassed
        $html = str_replace(
            [
                'entry_start.CyBhYG6o.js',
                'entry_app.DJYfHtfD.js',
                'DqVyHEVv.js',
            ],
            [
                'entry_start.CyBhYG6o.v2.js',
                'entry_app.DJYfHtfD.v2.js',
                'DqVyHEVv.v2.js',
            ],
            $html
        );

        // 5. Check if user is logged into GodAnime
        $isLoggedIn = !empty($_COOKIE['userID']) || !empty($_SESSION['userID']);
        $currentUsername = '';
        $currentUserAvatar = '/public/images/no-avatar.jpeg';
        if ($isLoggedIn && isset($conn) && $conn) {
            $uid = (int)($_COOKIE['userID'] ?? ($_SESSION['userID'] ?? 0));
            $uStmt = $conn->prepare('SELECT username, image FROM users WHERE id = ? LIMIT 1');
            if ($uStmt) {
                $uStmt->bind_param('i', $uid);
                $uStmt->execute();
                $uRow = $uStmt->get_result()->fetch_assoc();
                if ($uRow) {
                    $currentUsername = $uRow['username'];
                    $currentUserAvatar = !empty($uRow['image']) ? $uRow['image'] : '/public/images/no-avatar.jpeg';
                }
            }
        }

        // 6. Inject Client-side Guard + Black + Orange Theme & Distinct UI Styles + Real-time MutationObserver & Anti-Ad Shield + Local Comment System
        $customHead = '
<script id="godanime-core-guard">
// Immediately disable ads in localStorage & provide dummy functions
try {
    localStorage.setItem("disable_ads", "true");
    localStorage.setItem("project_r_ad_net", "none");
} catch(e) {}
window.__project_r_ads = function() {};
window.onClickExcludes = [];

window.__GODANIME_USER__ = ' . json_encode([
    'isLoggedIn' => $isLoggedIn,
    'userId' => $currentUser ? (int)$currentUser['id'] : 0,
    'username' => $currentUsername,
    'avatar' => $currentUserAvatar,
]) . ';

// Keep local auth synchronized with SvelteKit frontend store
try {
    if (window.__GODANIME_USER__.isLoggedIn) {
        var currentAuth = null;
        try { currentAuth = JSON.parse(localStorage.getItem("project_r_auth")); } catch(e){}
        if (!currentAuth || currentAuth.userId !== window.__GODANIME_USER__.userId || currentAuth.username !== window.__GODANIME_USER__.username) {
            localStorage.setItem("project_r_auth", JSON.stringify({
                token: "httpOnly",
                userId: window.__GODANIME_USER__.userId,
                username: window.__GODANIME_USER__.username,
                pfp: window.__GODANIME_USER__.avatar,
                frame: null,
                plate: null,
                role: "user",
                privateKey: null
            }));
        }
    } else {
        localStorage.removeItem("project_r_auth");
    }
} catch(e) {}

(function() {
    "use strict";

    // Auto-unregister any stale service workers and purge legacy cache storage
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(regs) {
            for (var i = 0; i < regs.length; i++) {
                regs[i].unregister();
            }
        });
    }
    if ("caches" in window) {
        caches.keys().then(function(keys) {
            for (var i = 0; i < keys.length; i++) {
                caches.delete(keys[i]);
            }
        });
    }

    // Immediate URL Guard: / and /leaderboard -> /home
    if (window.location.pathname === "/" || window.location.pathname === "" || window.location.pathname === "/leaderboard" || window.location.pathname.indexOf("/leaderboard") === 0) {
        window.location.replace("/home");
    }

    var brandRegex = new RegExp("r" + "e:?a" + "nime", "gi");

    // =========================================================
    // 1. BULLETPROOF 3RD-PARTY REDIRECT & AD POPUP SHIELD
    // =========================================================

    function isDownloadTarget(el, url) {
        var s = (url || "").toLowerCase();
        if (s.indexOf("/download") !== -1 || s.indexOf("download=") !== -1 || /\.(?:mp4|mkv|zip|rar|7z|torrent|apk)(?:\?|$)/i.test(s) || /(?:gogo|mediafire|mega\.nz|streamwish|filemoon|dood|mp4upload|vidhide|gofile|krakenfiles|pixeldrain)/i.test(s)) {
            return true;
        }
        if (el) {
            if (el.hasAttribute && (el.hasAttribute("download") || (el.getAttribute("href") && isDownloadTarget(null, el.getAttribute("href"))))) return true;
            var t = (el.textContent || "").toLowerCase();
            if (t.indexOf("download") !== -1) return true;
            var aria = (el.getAttribute && (el.getAttribute("aria-label") || el.getAttribute("title")) || "").toLowerCase();
            if (aria.indexOf("download") !== -1) return true;
            if (el.closest) {
                var closestA = el.closest("a");
                if (closestA && (closestA.hasAttribute("download") || isDownloadTarget(null, closestA.href || closestA.getAttribute("href")) || (closestA.textContent || "").toLowerCase().indexOf("download") !== -1)) return true;
                var closestBtn = el.closest("button");
                if (closestBtn && ((closestBtn.textContent || "").toLowerCase().indexOf("download") !== -1 || (closestBtn.getAttribute("aria-label") || "").toLowerCase().indexOf("download") !== -1)) return true;
            }
        }
        return false;
    }

    // Complete lock on window.open: blocks all unauthorized popups & ad tabs, whitelists legitimate downloads
    var _realOpen = window.open;
    window.open = function(url, target, features) {
        if (!url || url === "about:blank") return { focus: function(){}, close: function(){}, closed: true };
        var sUrl = String(url);
        var isLocal = sUrl.indexOf(window.location.host) !== -1 || sUrl.indexOf("localhost") !== -1 || sUrl.startsWith("/") || sUrl.startsWith("#");
        var isDl = isDownloadTarget(null, sUrl);
        if (!isLocal && !isDl) {
            console.warn("[GodAnime Shield] Blocked unauthorized ad popup / 3rd-party window to:", sUrl);
            return { focus: function(){}, close: function(){}, closed: true };
        }
        return _realOpen.call(window, url, target, features);
    };

    // Block any attempt to dynamically inject ad scripts (Monetag, Adcash, AdMaven, etc.)
    var _origCreateElement = document.createElement;
    document.createElement = function(tag) {
        var el = _origCreateElement.apply(document, arguments);
        if (String(tag).toLowerCase() === "script") {
            var _origSetAttribute = el.setAttribute;
            el.setAttribute = function(name, val) {
                if (name === "src" && /llvpn|acscdn|monetag|adcash|admaven|cloudfront\.net\/\?rzqfd|tag\.min\.js/i.test(val)) {
                    console.warn("[GodAnime Shield] Blocked dynamic ad script injection:", val);
                    return;
                }
                return _origSetAttribute.apply(el, arguments);
            };
        }
        return el;
    };

    // Global Click Interceptor: Runs in CAPTURING PHASE (Priority 1 before any other listener)
    window.addEventListener("click", function(e) {
        var el = e.target;
        var anchor = el && el.closest ? el.closest("a") : null;
        if (anchor) {
            var href = anchor.getAttribute("href") || "";
            // Always whitelist legitimate download links
            if (isDownloadTarget(anchor, href)) {
                return;
            }
            if (href === "/") {
                e.preventDefault();
                window.location.href = "/home";
                return false;
            }

            // Check if href is an external 3rd-party domain
            if (/^(?:https?:)?\/\//i.test(href)) {
                var isLocal = href.indexOf(window.location.host) !== -1 || href.indexOf("localhost") !== -1;
                if (!isLocal) {
                    // BLOCK ALL 3RD PARTY REDIRECTS (Crunchyroll, Hulu, Netflix, Discord, Reddit, Ads, Trackers, etc.)
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    console.warn("[GodAnime Shield] Blocked 3rd-party redirect to:", href);
                    return false;
                }
            }

            // Block target="_blank" redirects to external domains
            if (anchor.target === "_blank") {
                var fullHref = anchor.href || "";
                if (fullHref && fullHref.indexOf(window.location.host) === -1 && fullHref.indexOf("localhost") === -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    console.warn("[GodAnime Shield] Blocked target=_blank ad popup:", fullHref);
                    return false;
                }
            }

            // Handle Login button clicks: route to GodAnime local login
            if (anchor.getAttribute("href") === "/login" || (anchor.textContent && anchor.textContent.trim().toLowerCase() === "login")) {
                if (!window.__GODANIME_USER__.isLoggedIn) {
                    e.preventDefault();
                    window.location.href = "/login";
                    return false;
                }
            }
        }

        // Intercept buttons that trigger login dialog
        var btn = el && el.closest ? el.closest("button") : null;
        if (btn) {
            // Always whitelist legitimate download buttons
            if (isDownloadTarget(btn, "")) {
                return;
            }
            var btnText = (btn.textContent || "").trim().toLowerCase();
            if (btnText === "login" || btn.getAttribute("aria-label") === "Login") {
                if (!window.__GODANIME_USER__.isLoggedIn) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.location.href = "/login";
                    return false;
                }
            }
        }
    }, true);

    // =========================================================
    // 2. REAL-TIME BRAND REPLACER & IFRAME SECURITY SANDBOX
    // =========================================================
    function sanitizeElement(node) {
        if (!node) return;
        if (node.nodeType === Node.TEXT_NODE) {
            var val = node.nodeValue;
            if (val && brandRegex.test(val)) {
                node.nodeValue = val.replace(brandRegex, "GodAnime");
            }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            // Check text attributes
            var attrs = ["title", "alt", "placeholder", "aria-label"];
            for (var a = 0; a < attrs.length; a++) {
                var attrName = attrs[a];
                var attrVal = node.getAttribute && node.getAttribute(attrName);
                if (attrVal && brandRegex.test(attrVal)) {
                    node.setAttribute(attrName, attrVal.replace(brandRegex, "GodAnime"));
                }
            }

            // Eradicate Discord announcement banners & redirection notices
            if (node.classList && (node.classList.contains("notice") || node.classList.contains("notice-cta") || node.classList.contains("notice-hatch") || node.classList.contains("notice-arc"))) {
                var nb = node.closest(".notice") || node;
                nb.remove();
                return;
            }
            if (node.getAttribute && node.getAttribute("aria-label") === "Dismiss announcement") {
                var nb = node.closest(".notice") || node;
                nb.remove();
                return;
            }
            if (window.__GODANIME_USER__.isLoggedIn && node.classList && (node.classList.contains("login-overlay-wrap") || node.classList.contains("login-overlay"))) {
                node.remove();
                return;
            }

            // Secure Links: neutralise external 3rd party links & strip leaderboard
            if (node.tagName === "A") {
                var href = node.getAttribute("href") || "";
                if (href.indexOf("/leaderboard") !== -1) {
                    node.remove();
                    return;
                }
                if (href === "/") {
                    node.setAttribute("href", "/home");
                }
                // Whitelist legitimate download links
                if (isDownloadTarget(node, href)) {
                    return;
                }
                if (/^(?:https?:)?\/\//i.test(href) && href.indexOf(window.location.host) === -1 && href.indexOf("localhost") === -1) {
                    node.setAttribute("href", "#");
                    node.removeAttribute("target");
                }
            }

            // Secure Video Player iframes against ad popups and top navigation redirects (allowing downloads)
            if (node.tagName === "IFRAME") {
                node.setAttribute("sandbox", "allow-scripts allow-same-origin allow-forms allow-presentation allow-orientation-lock allow-downloads");
                node.setAttribute("allow", "autoplay; fullscreen; encrypted-media; picture-in-picture");
            }

            var child = node.firstChild;
            while (child) {
                sanitizeElement(child);
                child = child.nextSibling;
            }
        }
    }

    function sanitizeTitle() {
        if (document.title && brandRegex.test(document.title)) {
            document.title = document.title.replace(brandRegex, "GodAnime");
        }
    }

    // Auto-remove any click-hijacking overlay divs created by rogue scripts
    setInterval(function() {
        var overlays = document.querySelectorAll("div[style*=\'z-index: 2147483647\'], div[style*=\'z-index:2147483647\'], div[class*=\'ad-overlay\']");
        for (var i = 0; i < overlays.length; i++) {
            overlays[i].remove();
        }
        var notices = document.querySelectorAll(".notice, .notice-cta, .notice-hatch, [aria-label=\'Dismiss announcement\']");
        for (var j = 0; j < notices.length; j++) {
            var n = notices[j].closest(".notice") || notices[j];
            n.remove();
        }
        // Hide community navigation button in mobile / bottom nav
        try {
            var commLinks = document.querySelectorAll("nav a[href*=\'/community\'], div.fixed a[href*=\'/community\'], a[href=\'/community\'], a[href*=\'/community\']");
            for (var ci = 0; ci < commLinks.length; ci++) {
                commLinks[ci].style.setProperty("display", "none", "important");
            }
        } catch(e) {}
        // Hide weird protruding corner tabs on WATCH NOW hero button
        try {
            var cornerTabs = document.querySelectorAll("button.-skew-x-12 div[class*=\'-top-1.5\'], button.-skew-x-12 div[class*=\'-bottom-1.5\']");
            for (var cti = 0; cti < cornerTabs.length; cti++) {
                cornerTabs[cti].style.setProperty("display", "none", "important");
            }
        } catch(e) {}
        if (window.__GODANIME_USER__.isLoggedIn) {
            var loginOverlays = document.querySelectorAll(".login-overlay-wrap, .login-overlay");
            for (var k = 0; k < loginOverlays.length; k++) {
                loginOverlays[k].remove();
            }
        }
    }, 250);

    // Real-time MutationObserver
    var observer = new MutationObserver(function(mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var m = mutations[i];
            if (m.type === "childList") {
                for (var j = 0; j < m.addedNodes.length; j++) {
                    sanitizeElement(m.addedNodes[j]);
                }
            } else if (m.type === "characterData") {
                sanitizeElement(m.target);
            }
        }
        sanitizeTitle();
        mountGodAnimeComments();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true
    });

    // =========================================================
    // 3. GODANIME NATIVE LOCAL COMMENT SYSTEM
    // =========================================================
    function mountGodAnimeComments() {
        if (window.location.pathname.indexOf("/watch/") === -1) return;
        var container = document.querySelector("[data-comments-section]");
        if (!container) return;

        var m = window.location.pathname.match(/\/watch\/([a-zA-Z0-9_\-]+)/);
        var animeSlug = m ? m[1] : "";
        var params = new URLSearchParams(window.location.search);
        var epNum = parseInt(params.get("ep") || "1", 10);
        var currentKey = animeSlug + ":" + epNum;

        if (container.getAttribute("data-ga-mounted") === currentKey) return;
        container.setAttribute("data-ga-mounted", currentKey);

        var user = window.__GODANIME_USER__;
        var html = `
<div id="godanime-comments-wrap" class="w-full my-6 font-sans">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-orange-500/20">
        <div class="flex items-center gap-2">
            <span class="text-xl font-bold text-white tracking-wide flex items-center gap-2">
                <span style="color:#FF6B00;">💬</span> Comments
            </span>
            <span id="ga-cm-badge" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-orange-500/10 text-orange-400 border border-orange-500/30">Episode ${epNum}</span>
        </div>
        <div class="text-xs text-zinc-400">
            GodAnime Local Community
        </div>
    </div>

    <!-- Post Comment Box -->
    <div class="p-4 mb-6 rounded-xl bg-[#0c0c10] border border-zinc-800/80 shadow-lg shadow-black/40">
        ${user.isLoggedIn ? `
        <div class="flex gap-3">
            <img src="${user.avatar}" alt="${user.username}" class="w-10 h-10 rounded-full object-cover border border-orange-500/40 shrink-0"/>
            <div class="flex-1">
                <div class="text-xs font-semibold text-zinc-300 mb-1">Comment as <span style="color:#FF6B00;">${user.username}</span></div>
                <textarea id="ga-cm-input" rows="3" placeholder="Share your thoughts on episode ${epNum}..." class="w-full p-3 rounded-lg bg-[#14141a] border border-zinc-700/60 text-white text-sm focus:outline-none focus:border-orange-500 transition-all resize-y"></textarea>
                <div class="flex items-center justify-between mt-3">
                    <label class="flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none hover:text-zinc-300">
                        <input type="checkbox" id="ga-cm-spoil" class="rounded accent-orange-500 cursor-pointer"/>
                        <span>Contains Spoilers</span>
                    </label>
                    <button id="ga-cm-submit" class="px-5 py-2 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-[#FF6B00] to-[#EA580C] hover:from-[#FF7A1A] hover:to-[#FF5500] shadow-md shadow-orange-500/25 transition-all transform active:scale-95">
                        Post Comment
                    </button>
                </div>
            </div>
        </div>
        ` : `
        <div class="flex items-center justify-between py-2 text-sm text-zinc-300">
            <div class="flex items-center gap-2">
                <span class="text-base" style="color:#FF6B00;">🔒</span>
                <span>Join the discussion! Log in to share your thoughts with fellow fans.</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="/login" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-gradient-to-r from-[#FF6B00] to-[#EA580C] hover:from-[#FF7A1A] hover:to-[#FF5500] transition-all">Log In</a>
                <a href="/register" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 transition-all">Register</a>
            </div>
        </div>
        `}
    </div>

    <!-- Comments List -->
    <div id="ga-comments-list" class="space-y-3">
        <div class="py-8 text-center text-zinc-500 text-sm animate-pulse">Loading comments...</div>
    </div>
</div>
`;
        container.innerHTML = html;

        // Fetch comments from local SQLite database
        fetchComments(animeSlug, epNum);

        // Submit comment listener
        var submitBtn = document.getElementById("ga-cm-submit");
        if (submitBtn) {
            submitBtn.addEventListener("click", function() {
                var input = document.getElementById("ga-cm-input");
                var spoilCheck = document.getElementById("ga-cm-spoil");
                var text = (input && input.value || "").trim();
                if (!text) return;

                submitBtn.disabled = true;
                submitBtn.textContent = "Posting...";

                fetch("/ajax/comment/add", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        movie_id: animeSlug,
                        episode_id: epNum,
                        content: text,
                        is_spoil: spoilCheck && spoilCheck.checked ? 1 : 0
                    })
                })
                .then(function(res){ return res.json(); })
                .then(function(data){
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Post Comment";
                    if (data.status) {
                        input.value = "";
                        if (spoilCheck) spoilCheck.checked = false;
                        fetchComments(animeSlug, epNum);
                    } else {
                        alert(data.msg || "Failed to post comment");
                    }
                })
                .catch(function(){
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Post Comment";
                    alert("Network error, please try again.");
                });
            });
        }
    }

    function fetchComments(animeSlug, epNum) {
        var listContainer = document.getElementById("ga-comments-list");
        if (!listContainer) return;

        fetch("/ajax/comment/list/" + encodeURIComponent(animeSlug) + "?episodeId=" + epNum + "&sort=newest")
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.status) {
                listContainer.innerHTML = \'<div class="py-6 text-center text-zinc-500 text-sm">Failed to load comments.</div>\';
                return;
            }

            var comments = data.comments || [];
            var badge = document.getElementById("ga-cm-badge");
            if (badge) badge.textContent = "Episode " + epNum + " (" + comments.length + ")";

            if (comments.length === 0) {
                listContainer.innerHTML = \'<div class="py-8 text-center text-zinc-500 text-sm">No comments yet on this episode. Be the first to start the conversation!</div>\';
                return;
            }

            var out = "";
            for (var i = 0; i < comments.length; i++) {
                var c = comments[i];
                var isSpoil = parseInt(c.is_spoiler, 10) === 1;
                var safeContent = (c.content || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                out += `
<div class="p-3.5 rounded-xl bg-[#0c0c10] border border-zinc-800/60 transition-all hover:border-zinc-700">
    <div class="flex items-start gap-3">
        <img src="${c.user_avatar || "/public/images/no-avatar.jpeg"}" alt="${c.username}" class="w-9 h-9 rounded-full object-cover border border-orange-500/30 shrink-0"/>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold text-white">${c.username || "Anonymous"}</span>
                <span class="text-[10px] text-zinc-500">${c.created_at || ""}</span>
                ${isSpoil ? \'<span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-red-500/20 text-red-400 border border-red-500/30">Spoiler</span>\' : ""}
            </div>
            <div class="text-sm text-zinc-200 leading-relaxed ${isSpoil ? "ga-spoil-blur cursor-pointer select-none" : ""}" ${isSpoil ? \'title="Click to reveal spoiler" onclick="this.classList.toggle(\\\'ga-spoil-blur\\\')\"\' : ""}>
                ${safeContent}
            </div>
            <div class="flex items-center gap-4 mt-2 text-xs text-zinc-500">
                <button class="flex items-center gap-1 hover:text-orange-400 transition-colors" onclick="voteComment(${c.id}, 1, this)">
                    <span>▲</span> <span class="like-cnt">${c.likes || 0}</span>
                </button>
                <button class="flex items-center gap-1 hover:text-zinc-300 transition-colors" onclick="voteComment(${c.id}, 0, this)">
                    <span>▼</span>
                </button>
            </div>
        </div>
    </div>
</div>
`;
            }
            listContainer.innerHTML = out;
        })
        .catch(function(){
            listContainer.innerHTML = \'<div class="py-6 text-center text-zinc-500 text-sm">Error loading comments.</div>\';
        });
    }

    window.voteComment = function(commentId, type, btn) {
        if (!window.__GODANIME_USER__.isLoggedIn) {
            window.location.href = "/login";
            return;
        }
        fetch("/ajax/comment/vote", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: commentId, type: type })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status && typeof d.likes !== "undefined") {
                var p = btn.closest(".p-3\\.5");
                var cnt = p && p.querySelector(".like-cnt");
                if (cnt) cnt.textContent = d.likes;
            }
        });
    };

    // Initial DOM pass
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() {
            sanitizeElement(document.body);
            sanitizeTitle();
            mountGodAnimeComments();
        });
    } else {
        sanitizeElement(document.body);
        sanitizeTitle();
        mountGodAnimeComments();
    }
    window.addEventListener("load", function() {
        sanitizeElement(document.body);
        sanitizeTitle();
        mountGodAnimeComments();
    });
})();
</script>
<style id="godanime-theme">
/* =========================================================
   GodAnime Black + Orange Premium Signature Theme
   ========================================================= */
:root, html, body {
  --primary: #FF6B00 !important;
  --primary-rgb: 255, 107, 0 !important;
  --primary-foreground: #FFFFFF !important;
  --accent: #FF6B00 !important;
  --color-primary: #FF6B00 !important;
  --tw-ring-color: rgba(255, 107, 0, 0.45) !important;
  --rn-glow: 40% !important;
}

body {
  background-color: #060608 !important;
  color: #F4F4F5 !important;
}

::selection {
  background: #FF6B00 !important;
  color: #FFFFFF !important;
}

/* Custom Sleek Scrollbar */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
::-webkit-scrollbar-track {
  background: #060608;
}
::-webkit-scrollbar-thumb {
  background: #202028;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #FF6B00;
}

/* Transparent Floating Navbar (Matches reference: no solid background, no covering border/shadow) */
header.hdr,
header,
nav.c-1xr3cp0,
.header-container {
  background: transparent !important;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  border: none !important;
  border-bottom: none !important;
  box-shadow: none !important;
}

/* Primary Electric Orange Elements (Replaces all lime/green) */
.bg-primary,
.bg-green-500,
button[class*="bg-green"],
a[class*="bg-green"],
.btn-primary,
button.is-primary {
  background-color: #FF6B00 !important;
  background: linear-gradient(135deg, #FF6B00 0%, #EA580C 100%) !important;
  color: #FFFFFF !important;
  border: none !important;
  box-shadow: 0 4px 18px rgba(255, 107, 0, 0.38) !important;
  transition: all 0.25s ease !important;
  font-weight: 600 !important;
}

.bg-primary:hover,
.bg-green-500:hover,
button[class*="bg-green"]:hover,
a[class*="bg-green"]:hover {
  background: linear-gradient(135deg, #FF7A1A 0%, #FF5500 100%) !important;
  box-shadow: 0 6px 26px rgba(255, 107, 0, 0.55) !important;
  transform: translateY(-1.5px) !important;
}

.text-primary {
  color: #FF7A1A !important;
}

.border-primary {
  border-color: #FF6B00 !important;
}

/* Anime Card Hover Effects with Warm Orange Ambience */
article, .anime-card, a.group {
  transition: transform 0.28s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.28s ease !important;
  border-radius: 10px !important;
}

article:hover,
.anime-card:hover,
a.group:hover {
  transform: translateY(-3px) !important;
  box-shadow: 0 12px 30px -6px rgba(255, 107, 0, 0.25) !important;
}

/* Active Episode / Server / Filter Pill Highlighting */
button.is-active,
.is-selected,
[aria-selected="true"],
.episode-item.active,
.server-tab.active {
  background: linear-gradient(135deg, #FF6B00 0%, #EA580C 100%) !important;
  color: #FFFFFF !important;
  border-color: #FF6B00 !important;
  box-shadow: 0 0 16px rgba(255, 107, 0, 0.45) !important;
}

/* Search Input Focus */
input:focus,
input:focus-visible,
input:focus-within {
  border-color: #FF6B00 !important;
  box-shadow: 0 0 0 2px rgba(255, 107, 0, 0.35) !important;
  outline: none !important;
}

/* Badges & Text Accents */
.text-amber-400,
span[class*="text-amber"] {
  color: #FF9248 !important;
}

/* Spoiler Blur Effect */
.ga-spoil-blur {
  filter: blur(7px);
  user-select: none;
  transition: filter 0.25s ease;
}
.ga-spoil-blur:hover {
  filter: blur(4px);
}

/* Completely Hide Stranger Comments, Global Chat, Community Feed, and Leaderboard */
.gc-live,
.gc-dock,
.gc-frame,
.gc-bar,
.gc-menu,
.gc-btn,
a[href="/community"],
a[href*="/community"],
a[href="/leaderboard"],
a[href*="/leaderboard"],
a[aria-label*="Leaderboard" i],
a[data-tip*="Leaderboard" i],
.lb-list,
.lb-tabs,
.leaderboard-container,
button[aria-label*="chat" i],
button[title*="chat" i],
button[title*="Chat" i],
a[href*="discord.com"],
a[href*="discord.gg"],
a[href*="discord"],
a[href*="reddit.com"],
.notice,
div.notice,
.notice-cta,
.notice-cta-text,
.notice-dismiss,
.notice-hatch,
.notice-arc,
.notice-link,
.notice-text,
.notice-eyebrow,
[aria-label="Dismiss announcement"],
div:has(> .notice-cta),
div:has(> .notice-dismiss),
div:has(> .notice-hatch),
div:has(> a[href*="discord"]) {
  display: none !important;
  visibility: hidden !important;
  pointer-events: none !important;
  height: 0 !important;
  max-height: 0 !important;
  overflow: hidden !important;
  margin: 0 !important;
  padding: 0 !important;
  border: none !important;
}

/* Fix weird corner tabs sticking out of WATCH NOW hero button on hover */
button.-skew-x-12 {
  overflow: hidden !important;
}
button.-skew-x-12 > div.bg-black\/20,
button.-skew-x-12 > div.bg-white\/20,
button.-skew-x-12 > div[class*="-top-1.5"],
button.-skew-x-12 > div[class*="-bottom-1.5"],
button[class*="-skew-x-12"] > div[class*="-top-1.5"],
button[class*="-skew-x-12"] > div[class*="-bottom-1.5"],
button[class*="-skew-x-12"] > div[class*="size-5"] {
  display: none !important;
  visibility: hidden !important;
  opacity: 0 !important;
  pointer-events: none !important;
  width: 0 !important;
  height: 0 !important;
  max-height: 0 !important;
  max-width: 0 !important;
}

/* Mobile bottom bar 4-column distribution when Community is removed */
nav.grid-cols-5,
nav[class*="grid-cols-5"],
div[class*="grid-cols-5"]:has(> a[href*="/home"]),
div[class*="grid-cols-5"]:has(> a[href*="/schedule"]) {
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
}

/* Hide Sign In Required overlay when user is logged in & unblur settings */
body.is-logged-in .login-overlay-wrap,
body.is-logged-in .login-overlay,
body[data-auth="true"] .login-overlay-wrap,
body[data-auth="true"] .login-overlay,
html.is-logged-in .login-overlay-wrap,
html.is-logged-in .login-overlay,
.login-overlay-wrap,
.login-overlay,
.lock-overlay,
.lock-overlay-content {
  display: none !important;
  visibility: hidden !important;
  pointer-events: none !important;
  height: 0 !important;
  max-height: 0 !important;
  overflow: hidden !important;
}

body.is-logged-in .blur-content,
body[data-auth="true"] .blur-content,
html.is-logged-in .blur-content,
.blur-content,
body.is-logged-in .opacity-30,
body[data-auth="true"] .opacity-30,
html.is-logged-in .opacity-30,
.opacity-30 {
  filter: none !important;
  opacity: 1 !important;
  pointer-events: auto !important;
}
</style>
';

        $html = str_replace('</head>', $customHead . '</head>', $html);
    }

    echo $html;
    exit;
}
