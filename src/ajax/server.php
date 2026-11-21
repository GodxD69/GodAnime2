<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/_config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/src/api/reanime_client.php');

$ep = max(1, (int)($_GET['ep'] ?? $_GET['episodeId'] ?? 1));
$malId = (int)preg_replace('/[^0-9]/', '', (string)($_GET['mal'] ?? $_GET['id'] ?? '0'));
$anilistId = (int)preg_replace('/[^0-9]/', '', (string)($_GET['anilist'] ?? '0'));

// If anilistId is not provided, try to resolve it from malId
if ($anilistId <= 0 && $malId > 0) {
    $anilistId = (int)(reanime_resolve_anilist($malId) ?? 0);
}

$response = reanime_get_all_servers($malId, $anilistId ?: null, $ep);

echo json_encode($response);
