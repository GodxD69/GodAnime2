<?php
/**
 * ReAnime client for anime video streaming servers.
 * Scrapes & interfaces with ReAnime's streaming backend (https://reanime.cz)
 */

require_once __DIR__ . '/domain_manager.php';

function reanime_base_url(): string
{
    return reanime_get_domain();
}
if (!defined('REANIME_CACHE_DIR')) {
    define('REANIME_CACHE_DIR', rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2), '/\\') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'reanime');
}
if (!defined('REANIME_CACHE_TTL')) {
    define('REANIME_CACHE_TTL', 86400); // 24 hours
}

function reanime_cache_dir(): string
{
    $dir = REANIME_CACHE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'anipaca_cache' . DIRECTORY_SEPARATOR . 'reanime';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
    return $dir;
}

function reanime_resolve_anilist(int $malId): ?int
{
    if ($malId <= 0) return null;

    $cacheFile = reanime_cache_dir() . DIRECTORY_SEPARATOR . "mal_al_{$malId}.json";
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 604800) { // 7 days
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (isset($cached['anilistId'])) {
            return (int)$cached['anilistId'] ?: null;
        }
    }

    require_once __DIR__ . '/anilist_client.php';
    $media = anilist_media_by_mal($malId);
    $anilistId = !empty($media['id']) ? (int)$media['id'] : null;

    if ($anilistId) {
        @file_put_contents($cacheFile, json_encode(['malId' => $malId, 'anilistId' => $anilistId]));
    }
    return $anilistId;
}

function reanime_flix_servers(int $anilistId, int $episode, ?int $malId = null): array
{
    if ($anilistId <= 0 && (!$malId || $malId <= 0)) {
        return [];
    }

    $cacheFile = reanime_cache_dir() . DIRECTORY_SEPARATOR . "flix_{$anilistId}_{$episode}.json";
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < REANIME_CACHE_TTL) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['servers'])) {
            return $cached['servers'];
        }
    }

    $base = reanime_base_url();
    $url = $base . "/api/flix/{$anilistId}/{$episode}" . ($malId ? "?mal={$malId}" : "");
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: ' . $base . '/'
        ],
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($res)) {
        $json = json_decode($res, true);
        if (!empty($json['success']) && !empty($json['servers']) && is_array($json['servers'])) {
            @file_put_contents($cacheFile, json_encode($json));
            return $json['servers'];
        }
    }
    return [];
}

/**
 * Returns merged sub and dub streaming servers from ReAnime with VidLink & VidHawk fallbacks.
 */
function reanime_get_all_servers(int $malId, ?int $anilistId, int $episode): array
{
    if ((!$anilistId || $anilistId <= 0) && $malId > 0) {
        $anilistId = reanime_resolve_anilist($malId);
    }
    $ep = max(1, $episode);

    $flixServers = reanime_flix_servers((int)$anilistId, $ep, $malId ?: null);

    $subServers = [];
    $dubServers = [];

    // Parse ReAnime flix servers
    foreach ($flixServers as $srv) {
        $rawLink = $srv['dataLink'] ?? '';
        if (!$rawLink) continue;

        $name = trim($srv['serverName'] ?? 'HD-1');
        $type = strtolower(trim($srv['dataType'] ?? 'sub'));

        // Prepare proper player query params
        $sep = strpos($rawLink, '?') !== false ? '&' : '?';
        $subLink = $rawLink . $sep . 'autoPlay=true&skI=true&skO=true';
        $dubLink = $rawLink . $sep . 'a=1&autoPlay=true&skI=true&skO=true';

        $serverSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));

        if (in_array($type, ['sub', 's-sub'], true)) {
            $subServers[] = [
                'serverName' => $name . ' (ReAnime)',
                'serverId' => 'reanime-' . $serverSlug,
                'dataLink' => $subLink,
                'embedUrl' => $subLink,
            ];
        }

        if (in_array($type, ['dub', 's-dub'], true)) {
            $dubServers[] = [
                'serverName' => $name . ' (ReAnime)',
                'serverId' => 'reanime-' . $serverSlug,
                'dataLink' => $dubLink,
                'embedUrl' => $dubLink,
            ];
        }
    }

    // Sort ReAnime servers: HD-1 first, then HD-2
    $sortFn = function ($a, $b) {
        if (strpos($a['serverName'], 'HD-1') !== false) return -1;
        if (strpos($b['serverName'], 'HD-1') !== false) return 1;
        if (strpos($a['serverName'], 'HD-2') !== false) return -1;
        if (strpos($b['serverName'], 'HD-2') !== false) return 1;
        return 0;
    };
    usort($subServers, $sortFn);
    usort($dubServers, $sortFn);

    // Fallback VidLink server
    if ($malId > 0 || ($anilistId && $anilistId > 0)) {
        $idParam = $malId > 0 ? $malId : $anilistId;
        $vidlinkSub = "https://vidlink.pro/anime/{$idParam}/{$ep}/sub?fallback=true&primaryColor=ff5c8a&secondaryColor=1f1f2b&icons=vid";
        $vidlinkDub = "https://vidlink.pro/anime/{$idParam}/{$ep}/dub?fallback=true&primaryColor=ff5c8a&secondaryColor=1f1f2b&icons=vid";

        $subServers[] = [
            'serverName' => 'VidLink',
            'serverId' => 'vidlink',
            'dataLink' => $vidlinkSub,
            'embedUrl' => $vidlinkSub,
        ];
        $dubServers[] = [
            'serverName' => 'VidLink',
            'serverId' => 'vidlink',
            'dataLink' => $vidlinkDub,
            'embedUrl' => $vidlinkDub,
        ];
    }

    // Fallback VidHawk cluster
    $vidhawkCluster = [
        ['serverName' => 'Kari', 'serverId' => 'kari'],
        ['serverName' => 'Flow', 'serverId' => 'flow'],
        ['serverName' => 'Zuri', 'serverId' => 'zuri'],
        ['serverName' => 'Gojo', 'serverId' => 'gojo'],
    ];

    foreach ($vidhawkCluster as $vh) {
        $sId = $vh['serverId'];
        $sName = $vh['serverName'];
        
        $vhSub = ($anilistId && in_array($sId, ['kari', 'zuri'], true))
            ? "https://vidhawk.buzz/embed/ani/{$anilistId}/{$ep}/sub?server={$sId}"
            : "https://vidhawk.buzz/embed/mal/{$malId}/{$ep}/sub?server={$sId}";

        $vhDub = ($anilistId && in_array($sId, ['kari', 'zuri'], true))
            ? "https://vidhawk.buzz/embed/ani/{$anilistId}/{$ep}/dub?server={$sId}"
            : "https://vidhawk.buzz/embed/mal/{$malId}/{$ep}/dub?server={$sId}";

        $subServers[] = [
            'serverName' => $sName,
            'serverId' => $sId,
            'dataLink' => $vhSub,
            'embedUrl' => $vhSub,
        ];
        $dubServers[] = [
            'serverName' => $sName,
            'serverId' => $sId,
            'dataLink' => $vhDub,
            'embedUrl' => $vhDub,
        ];
    }

    return [
        'success' => true,
        'sub' => $subServers,
        'dub' => $dubServers,
    ];
}

