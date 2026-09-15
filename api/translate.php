<?php
// api/translate.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$itemType   = trim($_POST['item_type'] ?? '');
$itemId     = (int)($_POST['item_id'] ?? 0);
$targetLang = preg_replace('/[^a-z0-9_-]/i', '', strtolower(trim($_POST['target_lang'] ?? '')));
$code       = trim($_POST['code'] ?? '');
$token      = trim($_POST['token'] ?? '');

if (!$itemId || !in_array($itemType, ['ticket_message', 'reply_message'], true) || empty($targetLang)) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters.']);
    exit;
}

// 1. Controllo Permessi / Accesso al Ticket
$userRole = $_SESSION['user_role'] ?? '';
$isStaff  = in_array($userRole, ['admin', 'agency', 'agent'], true);
$originalText = '';

if ($itemType === 'ticket_message') {
    $stmt = $pdo->prepare("SELECT id, message, access_token, tracking_code, user_id, guest_email FROM tickets WHERE id = ?");
    $stmt->execute([$itemId]);
    $t = $stmt->fetch();
    if ($t) {
        $originalText = $t['message'];
        $tokenMatch = (!empty($token) && hash_equals($t['access_token'], $token));
        $userMatch  = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$t['user_id']);
        if (!$isStaff && !$tokenMatch && !$userMatch) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            exit;
        }
    }
} else {
    $stmt = $pdo->prepare("SELECT r.id, r.message, t.access_token, t.user_id, t.guest_email 
                           FROM ticket_replies r 
                           JOIN tickets t ON r.ticket_id = t.id 
                           WHERE r.id = ?");
    $stmt->execute([$itemId]);
    $r = $stmt->fetch();
    if ($r) {
        $originalText = $r['message'];
        $tokenMatch = (!empty($token) && hash_equals($r['access_token'], $token));
        $userMatch  = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$r['user_id']);
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
        'success'    => true,
        'translated' => $cached,
        'cached'     => true
    ]);
    exit;
}

// 3. Normalizzazione URL LibreTranslate (come in translations.php)
$apiUrl = get_setting($pdo, 'libretranslate_url', 'https://libretranslate.com');
$apiUrl = rtrim($apiUrl, '/');
if (substr($apiUrl, -10) === '/translate') {
    $apiUrl = substr($apiUrl, 0, -10);
}
$translateEndpoint = $apiUrl . '/translate';

// Helper per interrogare LibreTranslate
function call_libretranslate($endpoint, $text, $source, $target, $format = 'html') {
    $postFields = [
        'q'      => $text,
        'source' => $source,
        'target' => $target,
        'format' => $format
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($postFields),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$httpCode, $response];
}

// Primo tentativo: 'auto'
list($httpCode, $response) = call_libretranslate($translateEndpoint, $originalText, 'auto', $targetLang, 'html');

// Se LibreTranslate risponde 404 su 'auto', usiamo il fallback identico a translations.php
if ($httpCode === 404 || $httpCode === 400) {
    $fallbackSource = ($targetLang === 'en') ? 'it' : 'en';
    list($httpCode, $response) = call_libretranslate($translateEndpoint, $originalText, $fallbackSource, $targetLang, 'html');
    
    // Se html non è supportato dal server, proviamo in formato text
    if ($httpCode === 404 || $httpCode === 400) {
        list($httpCode, $response) = call_libretranslate($translateEndpoint, $originalText, $fallbackSource, $targetLang, 'text');
    }
}

if ($httpCode !== 200 || empty($response)) {
    echo json_encode(['success' => false, 'error' => "Translation service error ($httpCode)."]);
    exit;
}

$resData = json_decode($response, true);
$translatedText = $resData['translatedText'] ?? null;

if (!$translatedText) {
    echo json_encode(['success' => false, 'error' => 'Empty translation received.']);
    exit;
}

// 4. Scrittura in Cache MySQL
$stmtInsert = $pdo->prepare("
    INSERT INTO translations_cache (item_type, item_id, source_lang, target_lang, translated_text) 
    VALUES (?, ?, 'auto', ?, ?)
    ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)
");
$stmtInsert->execute([$itemType, $itemId, $targetLang, $translatedText]);

echo json_encode([
    'success'    => true,
    'translated' => $translatedText,
    'cached'     => false
]);
