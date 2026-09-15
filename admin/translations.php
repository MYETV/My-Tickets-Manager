<?php
// admin/translations.php
// Clean Language JSON generator using LibreTranslate with automatic duplicate-word deduplication
session_start();
require_once __DIR__ . '/../includes/config.php';

// Ensure user is authorized as Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

$message = '';
$error = '';

/**
 * Clean translation artifacts like duplicate words caused by MT subword tokenization
 * (e.g. "impostazioni impostazioni" -> "impostazioni")
 */
function clean_translation(string $text): string {
    return preg_replace('/\b(\p{L}+)\s+\1\b/ui', '$1', trim($text));
}

// Handle translation generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_lang'])) {
    set_time_limit(180);

    $targetLang = preg_replace('/[^a-z0-9_-]/i', '', strtolower(trim($_POST['target_lang'])));
    $sourceFilePath = __DIR__ . '/../translations/lang-en.json';
    $targetFilePath = __DIR__ . '/../translations/lang-' . $targetLang . '.json';

    // Retrieve settings and normalize endpoint URL
    $apiUrl = rtrim(get_setting($pdo, 'libretranslate_url', 'https://libretranslate.com'), '/');
    if (substr($apiUrl, -10) === '/translate') {
        $apiUrl = substr($apiUrl, 0, -10);
    }
    $translateEndpoint = $apiUrl . '/translate';
    $apiKey = get_setting($pdo, 'libretranslate_api_key', '');

    if (empty($targetLang)) {
        $error = 'Please specify a target language code.';
    } elseif (!file_exists($sourceFilePath)) {
        $error = 'Base source file (lang-en.json) is missing.';
    } else {
        $sourceData = json_decode(file_get_contents($sourceFilePath), true);
        if (!is_array($sourceData)) {
            $error = 'Failed to parse lang-en.json as valid JSON.';
        } else {
            $keys   = array_keys($sourceData);
            $values = array_values($sourceData);
            $translatedData = [];

            // Fast batch translation request
            $batchPayload = [
                'q'      => $values,
                'source' => 'en',
                'target' => $targetLang,
                'format' => 'text'
            ];
            if (!empty($apiKey)) {
                $batchPayload['api_key'] = $apiKey;
            }

            $ch = curl_init($translateEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($batchPayload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $batchSuccess = false;
            if ($httpCode === 200 && $response) {
                $res = json_decode($response, true);
                if (isset($res['translatedText']) && is_array($res['translatedText']) && count($res['translatedText']) === count($keys)) {
                    $cleanedValues = array_map('clean_translation', $res['translatedText']);
                    $translatedData = array_combine($keys, $cleanedValues);
                    $batchSuccess = true;
                }
            }

            // Sequential fallback if batch array is not supported
            if (!$batchSuccess) {
                $failedCount = 0;
                $lastErrorMsg = '';

                foreach ($sourceData as $key => $value) {
                    $itemPayload = [
                        'q'      => $value,
                        'source' => 'en',
                        'target' => $targetLang,
                        'format' => 'text'
                    ];
                    if (!empty($apiKey)) {
                        $itemPayload['api_key'] = $apiKey;
                    }

                    $ch = curl_init($translateEndpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($itemPayload),
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_SSL_VERIFYPEER => false
                    ]);

                    $itemResponse = curl_exec($ch);
                    $itemHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($itemHttpCode === 200 && $itemResponse) {
                        $res = json_decode($itemResponse, true);
                        $itemResult = $res['translatedText'] ?? $value;
                        $translatedData[$key] = clean_translation($itemResult);
                    } else {
                        $res = json_decode($itemResponse, true);
                        $lastErrorMsg = $res['error'] ?? "HTTP Status $itemHttpCode";
                        $translatedData[$key] = $value;
                        $failedCount++;
                    }
                }

                if ($failedCount === count($keys)) {
                    $error = "Translation server returned an error (" . ($lastErrorMsg ?: "HTTP $httpCode") . "). Please check your LibreTranslate configuration.";
                }
            }

            // Save translated JSON file
            if (empty($error)) {
                if (file_put_contents($targetFilePath, json_encode($translatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $message = "Translation file (lang-{$targetLang}.json) generated successfully!";
                } else {
                    $error = "Failed to write translation file to disk.";
                }
            }
        }
    }
}

$availableLangs = get_available_languages();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid" style="max-width: 800px;">
        <h2><i class="fa-solid fa-language me-2"></i> Language & i18n Generator</h2>
        <hr>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Active Languages List -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-dark text-white">Active Language Files</div>
            <div class="card-body">
                <ul class="list-group">
                    <?php foreach ($availableLangs as $langCode): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>lang-<?php echo htmlspecialchars($langCode); ?>.json</strong></span>
                            <span class="badge bg-primary rounded-pill"><?php echo strtoupper(htmlspecialchars($langCode)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Generate New Language Form -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">Auto-Generate Language File via LibreTranslate</div>
            <div class="card-body">
                <form method="POST" action="translations.php">
                    <div class="mb-3">
                        <label class="form-label">Target Language Code (e.g. it, es, fr, de)</label>
                        <input type="text" name="target_lang" class="form-control" placeholder="it" required>
                        <div class="form-text">This reads lang-en.json, translates all values using LibreTranslate, and saves lang-[code].json.</div>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate Translation JSON</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
