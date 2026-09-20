<?php
// ============================================================
// Webhook Telegram-бота. Подписаться на заявки можно только с паролем:
//   /start ВАШ_ПАРОЛЬ      — подписаться
//   /stop                  — отписаться
// Любые другие сообщения игнорируются (бот молчит).
// Пароль задаётся в config.php → subscribe_password.
// ============================================================
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);      // Telegram всегда должен получать 200

$input = json_decode((string)file_get_contents('php://input'), true);
$chat_id = isset($input['message']['chat']['id']) ? $input['message']['chat']['id'] : null;
$chat_type = isset($input['message']['chat']['type']) ? $input['message']['chat']['type'] : '';
$text = isset($input['message']['text']) && is_string($input['message']['text']) ? trim($input['message']['text']) : '';
$password = (string)cfg('subscribe_password', '');

if ($chat_id === null || $chat_type !== 'private' || $password === '') {
    echo json_encode(['status' => 'ignored']);
    exit;
}

$subs = store_read('subscribers', []);

if (preg_match('~^/start(?:@\w+)?\s+(\S+)$~u', $text, $m) && hash_equals($password, $m[1])) {
    if (!in_array($chat_id, $subs)) {
        $subs[] = $chat_id;
        store_write('subscribers', array_values($subs));
    }
    tg_send($chat_id, 'Вы подписаны на уведомления о новых заявках. Отписаться: /stop');
} elseif (preg_match('~^/stop(?:@\w+)?$~u', $text) && in_array($chat_id, $subs)) {
    $subs = array_values(array_diff($subs, [$chat_id]));
    store_write('subscribers', $subs);
    tg_send($chat_id, 'Вы отписаны от уведомлений.');
}
// всё остальное — молча игнорируем

echo json_encode(['status' => 'ok']);
