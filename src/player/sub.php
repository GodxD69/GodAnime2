<?php
/**
 * Streaming player shim — SUB
 * Powered by ReAnime backend (https://reanime.cz) + VidLink & VidHawk fallbacks
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/_config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/src/api/reanime_client.php');

$malId = (int)preg_replace('/[^0-9]/', '', (string)($_GET['mal'] ?? $_GET['id'] ?? '0'));
$anilistId = (int)preg_replace('/[^0-9]/', '', (string)($_GET['anilist'] ?? '0'));
$ep = max(1, (int)($_GET['ep'] ?? 1));
$server = strtolower((string)($_GET['server'] ?? 'reanime-hd-1'));

// Resolve AniList ID if missing
if ($anilistId <= 0 && $malId > 0) {
    $anilistId = (int)(reanime_resolve_anilist($malId) ?? 0);
}

$embedUrl = '';

// ReAnime servers
$isReAnime = in_array($server, ['reanime-hd-1', 'reanime-hd-2', 'reanime-hd1', 'reanime-hd2', 'hd-1', 'hd-2', 'hd1', 'hd2', 'reanime'], true);

if ($isReAnime || empty($server)) {
    $flix = reanime_flix_servers($anilistId, $ep, $malId ?: null);
    $subFlix = array_values(array_filter($flix, function ($s) {
        return in_array(strtolower($s['dataType'] ?? ''), ['sub', 's-sub'], true);
    }));

    $targetName = (strpos($server, 'hd-2') !== false || strpos($server, 'hd2') !== false) ? 'HD-2' : 'HD-1';
    $matched = null;
    foreach ($subFlix as $sf) {
        if (stripos($sf['serverName'] ?? '', $targetName) !== false) {
            $matched = $sf;
            break;
        }
    }
    if (!$matched && !empty($subFlix)) {
        $matched = $subFlix[0];
    }

    if ($matched && !empty($matched['dataLink'])) {
        $raw = $matched['dataLink'];
        $sep = strpos($raw, '?') !== false ? '&' : '?';
        $embedUrl = $raw . $sep . 'autoPlay=true&skI=true&skO=true';
    }
}

// VidLink fallback
if ($embedUrl === '' && ($server === 'vidlink' || $isReAnime)) {
    $id = $malId > 0 ? $malId : $anilistId;
    if ($id > 0) {
        $embedUrl = "https://vidlink.pro/anime/{$id}/{$ep}/sub?fallback=true&primaryColor=ff5c8a&secondaryColor=1f1f2b&icons=vid";
    }
}

// VidHawk cluster fallback
if ($embedUrl === '' && in_array($server, ['flow', 'gojo'], true)) {
    if ($malId > 0) {
        $embedUrl = "https://vidhawk.buzz/embed/mal/{$malId}/{$ep}/sub?server={$server}";
    } elseif ($anilistId > 0) {
        $embedUrl = "https://vidhawk.buzz/embed/ani/{$anilistId}/{$ep}/sub?server={$server}";
    }
} elseif ($embedUrl === '' && in_array($server, ['kari', 'zuri'], true)) {
    if ($anilistId > 0) {
        $embedUrl = "https://vidhawk.buzz/embed/ani/{$anilistId}/{$ep}/sub?server={$server}";
    } elseif ($malId > 0) {
        $embedUrl = "https://vidhawk.buzz/embed/mal/{$malId}/{$ep}/sub?server={$server}";
    }
}

// Global fallback if still empty
if ($embedUrl === '') {
    if ($malId > 0) {
        $embedUrl = "https://vidlink.pro/anime/{$malId}/{$ep}/sub?fallback=true&primaryColor=ff5c8a";
    } elseif ($anilistId > 0) {
        $embedUrl = "https://vidhawk.buzz/embed/ani/{$anilistId}/{$ep}/sub?server=kari";
    } else {
        http_response_code(400);
        echo 'Missing anime id';
        exit;
    }
}

$embedUrl = htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Anti-Popup Shield for player shim (whitelisting download URLs)
        var _realOpen = window.open;
        window.open = function(url, target, features) {
            if (url && (String(url).indexOf('/download') !== -1 || /\.(?:mp4|mkv|zip|torrent)/i.test(url) || /(?:gogo|mediafire|mega|streamwish|filemoon|dood|mp4upload|vidhide|gofile|download)/i.test(url))) {
                return _realOpen ? _realOpen.apply(window, arguments) : null;
            }
            return null;
        };
    </script>
    <style>
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; }
        iframe { border: 0; width: 100%; height: 100%; }
    </style>
</head>
<body>
    <iframe src="<?= $embedUrl ?>" allowfullscreen allow="autoplay; fullscreen; picture-in-picture; screen-wake-lock" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-forms allow-presentation allow-orientation-lock allow-downloads"></iframe>
</body>
</html>
