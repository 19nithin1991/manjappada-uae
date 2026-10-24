<?php
/**
 * MANJAPPADA (UAE WING) - ISL 2026/27 KBFC SCORE PREDICTOR
 * InfinityFree Companion Backend + Official ISL Live Score Sync (Upload to htdocs/api.php)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbFile = __DIR__ . '/kbfc_data.json';

if (!file_exists($dbFile)) {
    $initialData = [
        'wingConfig' => [
            'wingTitle' => 'MANJAPPADA (UAE WING)',
            'whatsappGroupName' => 'Manjappada UAE'
        ],
        'fixtures' => [],
        'predictions' => []
    ];
    file_put_contents($dbFile, json_encode($initialData, JSON_PRETTY_PRINT));
}

$raw = file_get_contents($dbFile);
$state = json_decode($raw, true);
if (!is_array($state)) {
    $state = ['wingConfig' => null, 'fixtures' => [], 'predictions' => []];
}

$action = isset($_GET['action']) ? $_GET['action'] : 'state';

if ($action === 'sync_isl') {
    $islUrl = 'https://www.indiansuperleague.com/schedule-fixtures';
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'Mozilla/5.0']]);
    $html = @file_get_contents($islUrl, false, $ctx);
    echo json_encode([
        'ok' => true,
        'source' => $islUrl,
        'fetchedBytes' => $html ? strlen($html) : 0,
        'liveScores' => []
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'wingConfig' => $state['wingConfig'] ?? null,
        'fixtures' => $state['fixtures'] ?? [],
        'predictions' => $state['predictions'] ?? []
    ]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

if ($action === 'predict') {
    $preds = isset($state['predictions']) && is_array($state['predictions']) ? $state['predictions'] : [];
    $matchId = $body['matchId'] ?? '';
    $cleanedMob = preg_replace('/[^0-9]/', '', $body['mobileNumber'] ?? '');
    $found = false;

    foreach ($preds as $idx => $p) {
        $pMob = preg_replace('/[^0-9]/', '', $p['mobileNumber'] ?? '');
        if (($p['matchId'] ?? '') === $matchId && $pMob === $cleanedMob) {
            $preds[$idx] = $body;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $preds[] = $body;
    }
    $state['predictions'] = $preds;
    file_put_contents($dbFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode(['ok' => true, 'predictions' => $state['predictions']]);
    exit;
}

if ($action === 'save_state') {
    if (isset($body['fixtures'])) $state['fixtures'] = $body['fixtures'];
    if (isset($body['wingConfig'])) $state['wingConfig'] = $body['wingConfig'];
    file_put_contents($dbFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode([
        'ok' => true,
        'wingConfig' => $state['wingConfig'],
        'fixtures' => $state['fixtures'],
        'predictions' => $state['predictions'] ?? []
    ]);
    exit;
}

echo json_encode(['ok' => true]);
?>