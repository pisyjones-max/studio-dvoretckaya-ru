<?php
// ============================================================
// Общие функции для send.php / subscribe.php / token.php.
// Сам по себе этот файл ничего не выводит.
// Все секреты берутся из config.php (его НЕТ в репозитории).
// ============================================================

date_default_timezone_set('Europe/Moscow');

// ---------- Настройки ----------
function cfg($key = null, $default = null) {
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        $c = is_file($f) ? require $f : [];
        if (!is_array($c)) $c = [];
    }
    if ($key === null) return $c;
    return array_key_exists($key, $c) ? $c[$key] : $default;
}

// ---------- Хранилище (данные не читаются из браузера) ----------
// Файлы лежат в data/ и имеют расширение .php с первой строкой-«замком»:
// даже если веб-сервер не поддерживает .htaccess, при открытии такого
// файла в браузере выполняется exit и ничего не показывается.
define('DATA_GUARD', "<?php http_response_code(404); exit; ?>\n");

function data_dir() {
    static $d = null;
    if ($d !== null) return $d;
    $d = __DIR__ . '/data';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    return $d;
}

function store_path($name) {
    return data_dir() . '/' . preg_replace('~[^a-z0-9_-]~i', '', $name) . '.php';
}

function store_read($name, $default = []) {
    $f = store_path($name);
    if (!is_file($f)) return $default;
    $raw = @file_get_contents($f);
    if ($raw === false) return $default;
    if (strpos($raw, DATA_GUARD) === 0) $raw = substr($raw, strlen(DATA_GUARD));
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $default;
}

function store_write($name, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    return @file_put_contents(store_path($name), DATA_GUARD . $json, LOCK_EX) !== false;
}

// Журнал: одна JSON-строка на запись. Если файл вырос — оставляем последние 200 строк.
function log_append($name, $row, $max_bytes = 400000) {
    $f = store_path($name);
    if (!is_file($f)) @file_put_contents($f, DATA_GUARD, LOCK_EX);
    if (@filesize($f) > $max_bytes) {
        $lines = @file($f, FILE_IGNORE_NEW_LINES);
        if ($lines && count($lines) > 200) {
            $keep = array_merge([$lines[0]], array_slice($lines, -200));
            @file_put_contents($f, implode("\n", $keep) . "\n", LOCK_EX);
        }
    }
    @file_put_contents($f,
        json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "\n",
        FILE_APPEND | LOCK_EX);
}

function log_error($message) {
    $token = (string)cfg('bot_token', '');
    if ($token !== '') $message = str_replace($token, '***', $message);
    log_append('errors', ['time' => date('Y-m-d H:i:s'), 'error' => $message]);
}

// ---------- Шаблоны спама ----------
// По умолчанию — составлены по реальным спам-заявкам. Можно переопределить в config.php
// ключом 'spam_patterns' (но тогда config.php нужно сохранять в UTF-8).
function spam_patterns() {
    $p = cfg('spam_patterns', null);
    if (is_array($p)) return $p;
    return [
        '~(Пишу по поводу|Хочу уточнить|Вопрос по теме|Нужен расч[её]т|Интересует следующее)\s*:~ui',
        '~передайте.{0,60}руковод~ui',
        '~(коммерческ\w+ предложени|продвижени\w+ сайт|раскрутк|\bseo\b|рассылк|криптовалют|казино|займ)~ui',
    ];
}

// ---------- Строки / телефон ----------
function u_len($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}
function u_cut($s, $n) {
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
}

function normalize_phone($raw) {
    $d = preg_replace('~\D+~', '', $raw);
    if (strlen($d) === 10) $d = '7' . $d;
    if (strlen($d) === 11 && $d[0] === '8') $d[0] = '7';
    return $d;
}

function phone_valid($d) {
    if (!preg_match('~^7[3-9]\d{9}$~', $d)) return false;   // российские и казахстанские номера
    if (preg_match('~(\d)\1{6,}~', $d)) return false;        // 7+ одинаковых цифр подряд
    return true;
}

function phone_pretty($d) {
    return '+7 (' . substr($d, 1, 3) . ') ' . substr($d, 4, 3) . '-' . substr($d, 7, 2) . '-' . substr($d, 9, 2);
}

// ---------- Подпись токена формы ----------
function form_sig($t) {
    return hash_hmac('sha256', (string)$t, (string)cfg('form_secret', ''));
}

function client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

// ---------- Лимиты ----------
function rate_load($now) {
    $r = store_read('rate', []);
    foreach (['ip', 'all', 'phones'] as $k) if (!isset($r[$k]) || !is_array($r[$k])) $r[$k] = [];
    $cut = $now - 86400;
    foreach ($r['ip'] as $k => $times) {
        $times = array_values(array_filter((array)$times, function ($t) use ($cut) { return $t > $cut; }));
        if ($times) $r['ip'][$k] = $times; else unset($r['ip'][$k]);
    }
    $r['all'] = array_values(array_filter($r['all'], function ($t) use ($cut) { return $t > $cut; }));
    foreach ($r['phones'] as $k => $t) if ($t <= $cut) unset($r['phones'][$k]);
    return $r;
}

// ---------- Telegram (через Cloudflare-релей, как и раньше) ----------
function tg_send($chat_id, $text) {
    if (cfg('dry_run', false)) {               // режим проверки: ничего не отправляем
        log_append('outbox', ['chat_id' => $chat_id, 'text' => $text]);
        return ['ok' => true];
    }
    $token = (string)cfg('bot_token', '');
    if ($token === '') return ['ok' => false, 'error' => 'bot_token не задан'];

    $relay = rtrim((string)cfg('relay_url', ''), '/');
    $url = ($relay !== '')
        ? $relay . '/tg/bot' . $token . '/sendMessage'
        : 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $headers = [];
    if ($relay !== '' && (string)cfg('relay_secret', '') !== '') {
        $headers[] = 'X-Relay-Secret: ' . cfg('relay_secret');
    }
    $params = ['chat_id' => $chat_id, 'text' => $text];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) return ['ok' => false, 'error' => 'cURL: ' . $err];
    } elseif (ini_get('allow_url_fopen')) {
        $h = "Content-Type: application/x-www-form-urlencoded\r\n";
        foreach ($headers as $x) $h .= $x . "\r\n";
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => $h, 'content' => http_build_query($params),
            'timeout' => 10, 'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (!empty($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) $code = (int)$m[1];
        if ($response === false) return ['ok' => false, 'error' => 'file_get_contents вернул false'];
    } else {
        return ['ok' => false, 'error' => 'Нет ни cURL, ни allow_url_fopen'];
    }

    $decoded = json_decode($response, true);
    if (!isset($decoded['ok']) || $decoded['ok'] !== true) {
        $desc = isset($decoded['description']) ? $decoded['description'] : 'неизвестная ошибка Telegram API';
        return ['ok' => false, 'error' => "HTTP $code: $desc"];
    }
    return ['ok' => true];
}

// Кому отправлять: chat_ids из config.php + те, кто подписался по паролю
function recipients() {
    $ids = array_merge((array)cfg('chat_ids', []), store_read('subscribers', []));
    $out = [];
    foreach ($ids as $id) { if ($id !== '' && $id !== null) $out[(string)$id] = $id; }
    return array_values($out);
}
