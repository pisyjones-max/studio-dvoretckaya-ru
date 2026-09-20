<?php
// Выдаёт форме подписанную метку времени. Настоящий посетитель получает её
// при загрузке страницы; бот, который шлёт запрос прямо в send.php, — нет.
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ((string)cfg('form_secret', '') === '') {
    http_response_code(500);
    echo '{}';
    exit;
}

$t = time();
echo json_encode(['t' => $t, 'sig' => form_sig($t)]);
