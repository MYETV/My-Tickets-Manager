<?php
// api/translate.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$itemType   = trim($_POST['item_type'] ?? ''); // 'ticket_message' o 'reply_message'
$itemId     = (int)($_POST['item_id'] ?? 0);
$targetLang = strtolower(trim($_POST['target_lang'] ?? ''));
$code       = trim($_POST['code'] ?? '');
$token      = trim($_POST['token'] ?? '');

if (!$itemId || !in_array($itemType, ['ticket_message', 'reply_message'], true) || empty($targetLang)) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters.']);
    exit;
}

// 1. Controllo Autorizzazione / Accesso al Ticket
$userRole = $_SESSION['user_role'] ?? '';
$isStaff  = in_array($userRole, ['admin', 'agency', 'agent'], true);

$ticketId = 0;
$originalText = '';

if ($itemType === 'ticket_message') {
    $stmt = $pdo->prepare("SELECT id, message, access_token, tracking_code, user_id, guest_email FROM tickets WHERE id = ?");
    $stmt->execute([$itemId]);
    $t = $stmt->fetch();
    if ($t) {
        $ticketId = (int)$t['id'];
        $originalText = $t['message'];
        $tokenMatch = (!empty($token) && hash_equals($t['access_token'], $token));
        $userMatch = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$t['user_id']);
        if (!$isStaff && !$tokenMatch && !$userMatch) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }
    }
} else {
    $stmt = $pdo->prepare("SELECT r.id, r.ticket_id, r.message, t.access_token, t.user_id, t.guest_email 
                           FROM ticket_replies r 
                           JOIN tickets t ON r.ticket_id = t.id 
                           WHERE r.id = ?");
    $stmt->execute([$itemId]);
    $r = $stmt->fetch();
    if ($r) {
        $ticketId = (int)$r['ticket_id'];
        $originalText = $r['message'];
        $tokenMatch = (!empty($token) && hash_equals($r['access_token'], $token));
        $userMatch = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$r['user_id']);
        if (!$isStaff && !$tokenMatch && !$userMatch) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }
    }
}

if (empty($originalText)) {
    echo json_encode(['success' => false, 'error' => 'Content not found.']);
    exit;
}

// 2. Controllo Cache MySQL
$stmtCache = $pdo->prepare("SELECT translated_text FROM translations_cache WHERE item_type = ? AND item_id = ? AND target_lang = ?");
$stmtCache->execute([$itemType, $itemId, $targetLang]);
$cached = $stmtCache->fetchColumn();

if ($cached !== false) {
    echo json_encode([
        'success' => true,
        'translated' => $cached,
        'cached' => true
    ]);
    exit;
}

// 3. Richiesta a LibreTranslate se non in cache
$libreUrl = rtrim(get_setting($pdo, 'libretranslate_url', 'https://libretranslate.com'), '/');
$apiKey   = get_setting($pdo, 'libretranslate_api_key', '');

if (empty($libreUrl)) {
    echo json_encode(['success' => false, 'error' => 'LibreTranslate URL is not configured.']);
    exit;
}

$payload = [
    'q'      => $originalText,
    'source' => 'auto',
    'target' => $targetLang,
    'format' => 'html'
];
if (!empty($apiKey)) {
    $payload['api_key'] = $apiKey;
}

$ch = curl_init($libreUrl . '/translate');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Translation service unreachable or error (' . $httpCode . ').']);
    exit;
}

$data = json_decode($response, true);
$translatedText = $data['translatedText'] ?? null;

if (!$translatedText) {
    echo json_encode(['success' => false, 'error' => $data['error'] ?? 'Empty translation received.']);
    exit;
}

// 4. Salvataggio nella Cache MySQL
$stmtInsert = $pdo->prepare("
    INSERT INTO translations_cache (item_type, item_id, source_lang, target_lang, translated_text) 
    VALUES (?, ?, 'auto', ?, ?)
    ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)
");
$stmtInsert->execute([$itemType, $itemId, $targetLang, $translatedText]);

echo json_encode([
    'success' => true,
    'translated' => $translatedText,
    'cached' => false
]);
