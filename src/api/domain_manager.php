<?php
/**
 * Domain Manager for ReAnime Backend & Mirror tracking.
 * Automatically follows domain changes, falls back across known mirrors,
 * and allows manual domain updates anytime via config/reanime_domain.json or the web UI.
 */

function reanime_domain_config_path(): string
{
    $base = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2), '/\\');
    $configDir = $base . DIRECTORY_SEPARATOR . 'config';
    if (!is_dir($configDir)) {
        @mkdir($configDir, 0777, true);
    }
    return $configDir . DIRECTORY_SEPARATOR . 'reanime_domain.json';
}

function reanime_domain_load_config(): array
{
    $path = reanime_domain_config_path();
    if (file_exists($path)) {
        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data) && !empty($data['current_domain'])) {
            return $data;
        }
    }

    return [
        'current_domain' => 'https://reanime.cz',
        'candidate_domains' => [
            'https://reanime.cz',
            'https://reanime.to',
        ],
        'auto_update' => true,
        'last_checked' => 0,
        'status' => 'online',
    ];
}

function reanime_domain_save_config(array $config): bool
{
    $path = reanime_domain_config_path();
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return @file_put_contents($path, $json) !== false;
}

/**
 * Checks a domain: returns [http_code, final_url, response_time_ms, ok]
 */
function reanime_domain_probe(string $url, int $timeout = 4): array
{
    $start = microtime(true);
    $ch = curl_init(rtrim($url, '/') . '/home/__data.json');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Referer: ' . rtrim($url, '/') . '/'],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $timeMs = round((microtime(true) - $start) * 1000);
    curl_close($ch);

    $json = json_decode($res, true);
    $ok = ($code >= 200 && $code < 400 && is_array($json) && !empty($json['nodes']));
    $parsed = parse_url($effectiveUrl);
    $baseEffective = !empty($parsed['scheme']) && !empty($parsed['host'])
        ? "{$parsed['scheme']}://{$parsed['host']}"
        : $url;

    return [
        'ok' => $ok,
        'code' => $code,
        'effective_domain' => rtrim($baseEffective, '/'),
        'latency_ms' => $timeMs,
    ];
}

/**
 * Get the active domain, automatically updating if the current domain moved or failed.
 */
function reanime_get_domain(bool $forceCheck = false): string
{
    static $resolvedDomain = null;
    if ($resolvedDomain !== null && !$forceCheck) {
        return $resolvedDomain;
    }

    $config = reanime_domain_load_config();
    $current = rtrim($config['current_domain'] ?? 'https://reanime.cz', '/');
    $autoUpdate = $config['auto_update'] ?? true;
    $lastChecked = (int)($config['last_checked'] ?? 0);
    $now = time();

    // Re-check periodically (every 10 minutes) or when forced
    if ($forceCheck || ($autoUpdate && ($now - $lastChecked > 600))) {
        $probe = reanime_domain_probe($current);

        if ($probe['ok']) {
            // Check if domain was redirected to a new domain
            if (!empty($probe['effective_domain']) && strcasecmp($probe['effective_domain'], $current) !== 0) {
                $config['current_domain'] = $probe['effective_domain'];
                $current = $probe['effective_domain'];
            }
            $config['last_checked'] = $now;
            $config['status'] = 'online';
            $config['latency_ms'] = $probe['latency_ms'];
            reanime_domain_save_config($config);
        } else {
            // Current domain is failing, scan candidates for a healthy domain
            $workingDomain = null;
            $candidates = $config['candidate_domains'] ?? [];
            foreach ($candidates as $cand) {
                $cand = rtrim($cand, '/');
                if (strcasecmp($cand, $current) === 0) continue;
                $candProbe = reanime_domain_probe($cand);
                if ($candProbe['ok']) {
                    $workingDomain = !empty($candProbe['effective_domain']) ? $candProbe['effective_domain'] : $cand;
                    break;
                }
            }

            if ($workingDomain) {
                $config['current_domain'] = $workingDomain;
                $current = $workingDomain;
                $config['status'] = 'online (auto-recovered)';
            } else {
                $config['status'] = 'offline / unreachable';
            }
            $config['last_checked'] = $now;
            reanime_domain_save_config($config);
        }
    }

    $resolvedDomain = $current;
    return $resolvedDomain;
}

/**
 * Manually update the current domain.
 */
function reanime_set_domain(string $newDomain): bool
{
    $newDomain = rtrim(trim($newDomain), '/');
    if (!preg_match('#^https?://[a-zA-Z0-9_\-\.]+#i', $newDomain)) {
        return false;
    }

    $config = reanime_domain_load_config();
    $config['current_domain'] = $newDomain;
    if (!in_array($newDomain, $config['candidate_domains'] ?? [], true)) {
        array_unshift($config['candidate_domains'], $newDomain);
    }
    $config['last_checked'] = time();
    $config['status'] = 'manually configured';
    return reanime_domain_save_config($config);
}

