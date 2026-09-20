<?php
require __DIR__ . '/_lib.php';
@set_time_limit(300); ignore_user_abort(true);
header('X-Robots-Tag: noindex');

function fetch_img($url) {
    for ($k = 0; $k < 4; $k++) {
        $u = parse_url($url);
        if (!$u || !in_array($u['scheme'] ?? '', ['http', 'https'], true) || empty($u['host'])) return '';
        $ip = gethostbyname($u['host']);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return '';
        $port = $u['port'] ?? ($u['scheme'] === 'https' ? 443 : 80);
        $loc = ''; $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => 0, CURLOPT_MAXFILESIZE => 15000000, CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_RESOLVE => [$u['host'] . ':' . $port . ':' . $ip],
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$loc) { if (stripos($h, 'location:') === 0) $loc = trim(substr($h, 9)); return strlen($h); }]);
        $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code >= 300 && $code < 400 && $loc !== '') {
            if (preg_match('~^https?://~i', $loc)) $url = $loc;
            elseif ($loc[0] === '/') $url = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '') . $loc;
            else return '';
            continue;
        }
        if ($code !== 200 || !is_string($body) || $body === '') return '';
        $tmp = tempnam(sys_get_temp_dir(), 'bi'); file_put_contents($tmp, $body);
        $n = save_photo($tmp); @unlink($tmp);
        return $n;
    }
    return '';
}

function do_import($raw, $dry) {
    $items = json_decode($raw, true);
    if (is_array($items) && isset($items['posts'])) $items = $items['posts'];
    if (is_array($items) && isset($items['title'])) $items = [$items];
    if (!is_array($items) || !$items || array_keys($items) !== range(0, count($items) - 1)) return ['error' => 'Ожидается массив постов или {"posts":[...]}'];
    if (count($items) > 200) return ['error' => 'Не больше 200 постов за раз'];
    $all = store_read('blog_posts', []);
    $exists = function ($s) use (&$all) { foreach ($all as $p) if ($p['slug'] === $s) return true; return false; };
    $res = []; $okc = 0; $errc = 0; $changed = false;
    foreach ($items as $n => $it) {
        $it = is_array($it) ? $it : [];
        $title = trim((string)($it['title'] ?? '')); $text = trim(str_replace("\r", '', (string)($it['text'] ?? '')));
        if ($title === '' || $text === '') { $res[] = ['i' => $n, 'status' => 'error', 'error' => 'нужны title и text']; $errc++; continue; }
        $ext = trim((string)($it['id'] ?? ''));
        $slug = preg_match('~^[a-z0-9-]{1,80}$~', (string)($it['slug'] ?? '')) ? $it['slug'] : '';
        $idx = null;
        foreach ($all as $i => $p) if (($ext !== '' && ($p['ext'] ?? '') === $ext) || ($slug !== '' && $p['slug'] === $slug)) { $idx = $i; break; }
        if ($dry) { $res[] = ['i' => $n, 'status' => $idx === null ? 'would_create' : 'would_update', 'title' => $title]; $okc++; continue; }
        $o = $idx !== null ? $all[$idx] : [];
        $ts = null;
        if (isset($it['date'])) { $ts = is_numeric($it['date']) ? (int)$it['date'] : strtotime((string)$it['date']); if (!$ts) $ts = null; }
        $warn = [];
        $text = preg_replace_callback('~!\[([^\]]*)\]\((https?://[^)\s]+)\)~u', function ($m) use ($title, &$warn) {
            $f = fetch_img($m[2]);
            if ($f === '') { $warn[] = 'не скачано: ' . $m[2]; return ''; }
            $a = trim(str_replace(['[', ']', '(', ')'], '', $m[1]));
            return "\n\n![" . ($a !== '' ? $a : $title) . '](' . $f . ")\n\n";
        }, $text);
        $text = trim(preg_replace('~\n{3,}~', "\n\n", $text));
        $img = $o['img'] ?? ''; $src = $o['src'] ?? '';
        $cu = trim((string)($it['image'] ?? ''));
        if ($cu !== '' && $cu !== $src) { $f = fetch_img($cu); if ($f !== '') { $img = $f; $src = $cu; } else $warn[] = 'обложка не скачана'; }
        $alt = trim((string)($it['alt'] ?? '')); if ($alt === '') $alt = $o['alt'] ?? $title;
        $desc = isset($it['desc']) ? mb_substr(trim((string)$it['desc']), 0, 160) : ($o['desc'] ?? '');
        $new = ['title' => $title, 'text' => $text, 'img' => $img, 'src' => $src, 'alt' => $alt, 'desc' => $desc];
        if ($idx === null) {
            $t = $ts ?: time();
            $base = $slug !== '' ? $slug : slugify($title) . '-' . date('ymd', $t); $slug = $base; $k = 2;
            while ($exists($slug)) $slug = $base . '-' . $k++;
            $new += ['slug' => $slug, 'ext' => $ext, 'ts' => $t, 'vk' => 0];
            $new['vk'] = (!empty($it['vk']) && vk_post($new)) ? 1 : 0;
            $all[] = $new; $st = 'created';
        } else {
            foreach (array_diff(post_imgs($o), post_imgs($new)) as $x) del_img($x);
            $new['ext'] = $ext !== '' ? $ext : ($o['ext'] ?? '');
            $all[$idx] = array_merge($o, $new, ['ts' => $ts ?: $o['ts'], 'upd' => time()]); $slug = $o['slug']; $st = 'updated';
        }
        $changed = true; $okc++;
        $r = ['i' => $n, 'status' => $st, 'slug' => $slug, 'url' => SITE . '/blog/' . $slug];
        if ($warn) $r['warnings'] = $warn;
        $res[] = $r;
    }
    if ($changed) store_write('blog_posts', array_values($all));
    return ['ok' => $okc, 'errors' => $errc, 'dry' => $dry, 'results' => $res];
}

$tok = (string)cfg('blog_api_token', '');
$hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$given = preg_match('~^Bearer\s+(.+)$~i', $hdr, $m) ? trim($m[1]) : (string)($_SERVER['HTTP_X_API_TOKEN'] ?? '');
$api = $given !== '' && $tok !== '' && hash_equals($tok, $given);
if ($given !== '' && !$api) { sleep(1); http_response_code(401); header('Content-Type: application/json'); echo '{"error":"bad token"}'; exit; }
if (!$api && !auth_ok()) { http_response_code(401); echo 'Нужен вход в админку: /blog/admin.php'; exit; }
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$dry = !empty($_GET['dry']) || !empty($_POST['dry']);
$raw = '';
if ($post) $raw = $api ? (string)file_get_contents('php://input') : (!empty($_FILES['json']['tmp_name']) ? (string)file_get_contents($_FILES['json']['tmp_name']) : (string)($_POST['json_text'] ?? ''));
$r = $post ? do_import($raw, $dry) : null;
if ($api) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($r ?? ['error' => 'POST expected'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
$ex = '[{"id":"order-1001","title":"Заголовок","text":"Абзац 1\n\nАбзац 2 с **жирным**\n\n![описание](https://site/img.jpg)","image":"https://site/cover.jpg","alt":"Описание обложки","date":"2026-09-01","desc":"Для поиска","vk":false}]';
echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Массовая загрузка</title><style>body{font:16px/1.5 sans-serif;max-width:820px;margin:20px auto;padding:0 14px}textarea{width:100%;height:220px;font:13px monospace;box-sizing:border-box}button{background:#C2828F;color:#fff;border:0;padding:11px 20px;border-radius:6px;cursor:pointer;font:inherit}td,th{border-bottom:1px solid #ddd;padding:4px 8px;text-align:left;font-size:14px}.e{color:#b00}</style>';
echo '<p><a href="admin.php">← Админка</a></p><h2>Массовая загрузка (JSON)</h2>';
if ($r) {
    if (isset($r['error'])) echo '<p class="e">' . h($r['error']) . '</p>';
    else {
        echo '<p>' . ($r['dry'] ? 'Проверка (ничего не сохранено). ' : '') . 'Успешно: ' . $r['ok'] . ', ошибок: ' . $r['errors'] . '</p><table><tr><th>#</th><th>Статус</th><th>Запись</th><th>Замечания</th></tr>';
        foreach ($r['results'] as $x) echo '<tr><td>' . $x['i'] . '</td><td>' . h($x['status']) . '</td><td>' . (isset($x['url']) ? '<a href="' . h($x['url']) . '">' . h($x['slug']) . '</a>' : h($x['title'] ?? '')) . '</td><td class="e">' . h(($x['error'] ?? '') . ' ' . implode('; ', $x['warnings'] ?? [])) . '</td></tr>';
        echo '</table>';
    }
}
echo '<form method="post" enctype="multipart/form-data"><p>Файл .json: <input type="file" name="json" accept=".json,application/json"></p><p>или вставьте JSON:</p><textarea name="json_text" placeholder="' . h($ex) . '"></textarea><p><label><input type="checkbox" name="dry" value="1"> только проверить, ничего не сохранять</label></p><button>Загрузить</button></form>';
echo '<p style="font-size:14px;color:#666">Поля поста: title, text (обязательны); id (ключ заказа/внешний id — повторная загрузка обновит пост, а не создаст дубль), slug, image (URL обложки), alt, date, desc, vk (true — ещё и в ВК). Картинки в тексте — <code>![описание](https://...)</code>, скачиваются на сайт автоматически. До 200 постов за раз.</p>';
