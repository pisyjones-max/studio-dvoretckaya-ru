<?php
// ============================================================
// Приём заявки с сайта → Telegram, с защитой от спама.
// Подозрительные заявки НЕ теряются: они складываются в data/spam.php,
// но в Telegram не отправляются.
// Настройки — в config.php (его нет в репозитории).
// ============================================================
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($status, $message = '', $code = 200) {
    http_response_code($code);
    $r = ['status' => $status];
    if ($message !== '') $r['message'] = $message;
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
    respond('error', 'Метод не поддерживается', 405);
}
if ((string)cfg('form_secret', '') === '' || ((string)cfg('bot_token', '') === '' && !cfg('dry_run', false))) {
    log_error('config.php не заполнен: нужны bot_token и form_secret');
    respond('error', 'Сервис временно недоступен', 500);
}

// ---------- Разбор запроса ----------
$raw = file_get_contents('php://input');
if ($raw !== false && strlen($raw) > 20000) respond('error', 'Слишком большой запрос', 413);
$input = json_decode((string)$raw, true);
if (!is_array($input)) $input = $_POST;

function field($input, $key, $max) {
    $v = (isset($input[$key]) && is_scalar($input[$key])) ? (string)$input[$key] : '';
    $v = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', '', $v);
    return u_cut(trim($v), $max);
}

$name      = field($input, 'name', 100);
$phone_raw = field($input, 'phone', 40);
$message   = field($input, 'message', 1500);

if ($name === '' || $phone_raw === '') respond('error', 'Имя и телефон обязательны');

// Ошибки ввода: их видит человек и может исправить (как и в прежней версии send.php)
if (!preg_match('~^[\p{L}\p{M}\s.\'’\-]{2,60}$~u', $name)) respond('error', 'Введите корректное имя');
$phone = normalize_phone($phone_raw);
if (!phone_valid($phone)) respond('error', 'Введите корректный телефон');
if (u_len($message) > 1000) respond('error', 'Слишком длинное сообщение');

// ---------- Лимиты ----------
$now = time();
$lim = array_merge([
    'per_ip_hour' => 3, 'per_ip_day' => 8, 'global_hour' => 40,
    'min_fill_seconds' => 4, 'max_token_age' => 43200,
], (array)cfg('limits', []));

$ip = client_ip();
$ip_key = substr(hash('sha256', $ip . '|' . cfg('form_secret')), 0, 16);
$rate = rate_load($now);

$ip_times = isset($rate['ip'][$ip_key]) ? $rate['ip'][$ip_key] : [];
$ip_hour = count(array_filter($ip_times, function ($t) use ($now) { return $t > $now - 3600; }));
$all_hour = count(array_filter($rate['all'], function ($t) use ($now) { return $t > $now - 3600; }));
$call_us = (string)cfg('studio_phone', '') !== '' ? ' Позвоните нам: ' . cfg('studio_phone') : '';

if ($ip_hour >= $lim['per_ip_hour'] || count($ip_times) >= $lim['per_ip_day']) {
    respond('error', 'Слишком много заявок с вашего адреса.' . $call_us, 429);
}
if ($all_hour >= $lim['global_hour']) {
    respond('error', 'Сейчас очень много обращений, попробуйте позже.' . $call_us, 429);
}

// ---------- Проверка на спам ----------
$reasons = [];

// Скрытое поле-ловушка: в форме на сайте оно называется website (плюс hp — на всякий случай).
// Заполнено — скорее всего бот (но иногда это автозаполнение браузера, поэтому заявка
// не выбрасывается, а уходит в карантин data/spam.php).
if (field($input, 'website', 200) !== '' || field($input, 'hp', 200) !== '') {
    $reasons[] = 'заполнено скрытое поле (honeypot)';
}

$t = (int)(isset($input['t']) && is_scalar($input['t']) ? $input['t'] : 0);
$sig = field($input, 'sig', 100);
if ($t <= 0 || $sig === '' || !hash_equals(form_sig($t), $sig)) {
    $reasons[] = 'нет или неверный токен формы (запрос не со страницы сайта)';
} else {
    $age = $now - $t;
    if ($age < $lim['min_fill_seconds']) $reasons[] = "форма отправлена слишком быстро ($age с)";
    if ($age > $lim['max_token_age'])    $reasons[] = 'токен формы устарел';
}

$hosts = (array)cfg('allowed_hosts', []);
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($hosts && $origin !== '') {
    $oh = parse_url($origin, PHP_URL_HOST);
    if (!$oh || !in_array(strtolower($oh), array_map('strtolower', $hosts), true)) {
        $reasons[] = 'запрос с чужого сайта (Origin)';
    }
}

if (preg_match('~(https?:|www\.|t\.me|tg:|bit\.ly)~iu', $name . ' ' . $message)) $reasons[] = 'ссылка в тексте';
foreach (spam_patterns() as $rx) {
    if (@preg_match($rx, $name . "\n" . $message)) { $reasons[] = 'шаблон спама: ' . $rx; break; }
}

$suspicious = !empty($reasons);
$monitor = (cfg('mode', 'block') === 'monitor');

// Считаем попытку в лимитах
$rate['ip'][$ip_key][] = $now;
$rate['all'][] = $now;

// ---------- Спам: в карантин ----------
if ($suspicious && !$monitor) {
    log_append('spam', [
        'time' => date('Y-m-d H:i:s'), 'ip' => $ip, 'reasons' => $reasons,
        'name' => $name, 'phone' => $phone_raw, 'message' => $message,
    ]);
    store_write('rate', $rate);
    respond('ok');            // боту сообщаем «успех» — пусть не догадывается
}

// ---------- Дубликат (тот же телефон за сутки) ----------
$phone_key = substr(hash('sha256', $phone), 0, 16);
if (!$suspicious && isset($rate['phones'][$phone_key])) {
    store_write('rate', $rate);
    respond('ok');            // клиент уже оставил заявку, повторно не шлём
}
if (!$suspicious) $rate['phones'][$phone_key] = $now;
store_write('rate', $rate);

// ---------- Сохраняем и отправляем ----------
log_append('leads', [
    'time' => date('Y-m-d H:i:s'), 'name' => $name, 'phone' => $phone_raw, 'message' => $message,
    'suspicious' => $suspicious ? $reasons : null,
]);

$text  = $suspicious ? "⚠️ ПОХОЖЕ НА СПАМ (" . implode('; ', $reasons) . ")\n\n" : '';
$text .= "📩 Новая заявка с сайта\n";
$text .= "👤 Имя: $name\n";
$text .= "📞 Телефон: " . phone_pretty($phone) . "\n";
if ($message !== '') $text .= "💬 Сообщение: $message";

$to = recipients();
if (!$to) {
    log_error("Заявка получена, но получателей нет: добавьте chat_ids в config.php или подпишитесь через /start <пароль>.");
}
foreach ($to as $chat_id) {
    $res = tg_send($chat_id, $text);
    if (!$res['ok']) log_error("chat_id $chat_id: " . $res['error']);
}

respond('ok');
