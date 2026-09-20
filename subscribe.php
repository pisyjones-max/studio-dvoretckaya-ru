<?php
// ============================================================
// Webhook Telegram-бота: сохраняет chat_id, кто написал боту,
// и подтверждает подписку на уведомления о новых заявках
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/telegram_relay_config.php';

$log_file = __DIR__ . '/telegram_errors.log';
function log_error($message) {
    global $log_file;
    @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!isset($input['message']['chat']['id'])) {
    // Telegram должен получать HTTP 200 даже на апдейты, которые мы не обрабатываем,
    // иначе он будет считать webhook неисправным и слать повторно / отключит его
    http_response_code(200);
    echo json_encode(["status" => "ignored"]);
    exit;
}

$chat_id = $input['message']['chat']['id'];
$file = __DIR__ . '/subscribers.json';
$subscribers = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($subscribers)) $subscribers = [];

if (!in_array($chat_id, $subscribers)) {
    $subscribers[] = $chat_id;
    file_put_contents($file, json_encode($subscribers, JSON_PRETTY_PRINT), LOCK_EX);
}

// Ответ пользователю в Telegram
$token = "8288844028:AAHmXPVi3TmwyvTu_sfTrL2jfL15_lR2KOc";
$text = "Вы подписались на уведомления о новых заказах!";
$url = telegram_api_url($token, 'sendMessage');
$extra_headers = telegram_relay_headers();
$params = ['chat_id' => $chat_id, 'text' => $text];

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => $extra_headers,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        log_error("subscribe.php: cURL error для chat_id $chat_id: " . curl_error($ch));
    }
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $header_str = "Content-Type: application/x-www-form-urlencoded\r\n";
    foreach ($extra_headers as $h) { $header_str .= $h . "\r\n"; }
    $opts = ['http' => [
        'method' => 'POST',
        'header' => $header_str,
        'content' => http_build_query($params),
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    @file_get_contents($url, false, stream_context_create($opts));
} else {
    log_error("subscribe.php: недоступны ни cURL, ни allow_url_fopen — не удалось ответить chat_id $chat_id");
}

http_response_code(200);
echo json_encode(["status" => "ok"]);
