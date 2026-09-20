<?php
require_once __DIR__ . '/../lib.php';
const SITE = 'https://studio.dvoretckaya.ru';
const CSS = "body{margin:0;font-family:Montserrat,sans-serif;background:#f7f3f2;color:#333;line-height:1.7}header{background:#fff;box-shadow:0 2px 6px #0001;display:flex;justify-content:space-between;align-items:center;padding:8px 20px;position:sticky;top:0}header img{height:60px}nav a{margin-left:18px;color:#333;text-decoration:none}nav a:hover{color:#C2828F}main{max-width:860px;margin:0 auto;padding:32px 16px}h1,h2{font-family:'Playfair Display',serif;color:#A56A75;line-height:1.25}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}.card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px #0001;text-decoration:none;color:inherit}.card img{width:100%;height:180px;object-fit:cover;display:block}.card div{padding:14px}.card h2{font-size:20px;margin:0 0 6px}.d{color:#999;font-size:13px}article img{max-width:100%;height:auto;border-radius:12px}article a{color:#C2828F}footer{background:#C2828F;color:#fff;text-align:center;padding:22px;font-size:14px}footer a{color:#fff}figure{margin:26px 0;text-align:center}figure img{max-width:100%;height:auto;border-radius:12px;box-shadow:0 2px 8px #0001}article h2{margin:32px 0 8px}.bc{font-size:13px;color:#999;margin-bottom:12px}.bc a{color:#999}.n2{display:flex;justify-content:space-between;gap:14px;margin:34px 0;font-size:14px}.n2 a{color:#A56A75;text-decoration:none}.n2 span{max-width:48%}.cta{background:#fff;border-radius:12px;padding:22px;text-align:center;margin:34px 0;box-shadow:0 2px 8px #0001}.btn{display:inline-block;background:#C2828F;color:#fff!important;padding:10px 22px;border-radius:8px;text-decoration:none}.pg{display:flex;justify-content:space-between;margin:28px 0}.pg a{color:#A56A75}.ab{position:fixed;right:14px;bottom:14px;background:#333;border-radius:20px;padding:8px 16px;font-size:14px}.ab a{color:#fff;text-decoration:none}";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function cut($s, $n) { $s = preg_replace('~\s+~u', ' ', trim($s)); return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; }
function posts() { $p = store_read('blog_posts', []); usort($p, function ($a, $b) { return $b['ts'] <=> $a['ts']; }); return $p; }
function post_get($slug) { foreach (posts() as $p) if ($p['slug'] === $slug) return $p; return null; }
function slugify($t) {
    $m = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = strtr(mb_strtolower($t, 'UTF-8'), $m);
    $s = trim(substr(trim(preg_replace('~[^a-z0-9]+~', '-', $s), '-'), 0, 50), '-');
    return $s !== '' ? $s : 'post';
}
function inline_md($t) {
    $e = h($t);
    $e = preg_replace('~\*\*(.+?)\*\*~us', '<strong>$1</strong>', $e);
    $e = preg_replace('~\*(.+?)\*~us', '<em>$1</em>', $e);
    $e = preg_replace('~\[([^\]]+)\]\((https?://[^)\s]+)\)~u', '<a href="$2" rel="nofollow noopener" target="_blank">$1</a>', $e);
    return preg_replace('~(?<!["=>])(https?://[^\s<]+)~u', '<a href="$1" rel="nofollow noopener" target="_blank">$1</a>', $e);
}
function text_html($t) {
    $o = '';
    foreach (preg_split('~\R{2,}~', trim($t)) as $para) {
        $para = trim($para);
        if (preg_match('~^!\[([^\]]*)\]\(([a-f0-9]{12}\.[a-z]+)\)$~u', $para, $m)) { $o .= '<figure>' . img_tag($m[2], $m[1]) . "</figure>\n"; continue; }
        $lines = preg_split('~\R~', $para);
        if (strpos($para, '## ') === 0) { $o .= '<h2>' . inline_md(substr($para, 3)) . "</h2>\n"; continue; }
        if (count(array_filter($lines, function ($l) { return strpos($l, '- ') !== 0; })) === 0) {
            $o .= '<ul>' . implode('', array_map(function ($l) { return '<li>' . inline_md(substr($l, 2)) . '</li>'; }, $lines)) . "</ul>\n"; continue;
        }
        $o .= '<p>' . nl2br(inline_md($para), false) . "</p>\n";
    }
    return $o;
}
function layout($title, $desc, $canon, $body, $img = '', $ld = '') {
    $og = $img ? '<meta property="og:image" content="' . h($img) . '">' : '';
    $ab = auth_ok() ? '<div class="ab"><a href="/blog/admin.php' . (isset($GLOBALS['edit_slug']) ? '?e=' . h($GLOBALS['edit_slug']) . '">✎ Править запись' : '">✎ Админка') . '</a></div>' : '';
    return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title><meta name="description" content="' . h($desc) . '"><link rel="canonical" href="' . h($canon) . '"><meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '"><meta property="og:type" content="article">' . $og . '<link rel="icon" href="/img/favicon.png"><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet"><style>' . CSS . '</style>' . $ld . '</head><body><header><a href="/"><img src="/img/Elegant-Art-Studio-Logo.webp" alt="Студия творчества и вдохновения Ольги Дворецкой"></a><nav><a href="/">Главная</a><a href="/blog/">Блог</a><a href="/#contact">Контакты</a></nav></header><main>' . $body . '</main><footer>© Студия творчества и вдохновения · <a href="tel:+79264281144">+7 (926) 428-11-44</a></footer>' . $ab . '</body></html>';
}
function save_photo($tmp) {
    $i = @getimagesize($tmp);
    if (!$i || !in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return '';
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $n = bin2hex(random_bytes(6));
    if (!function_exists('imagecreatefromstring')) {
        $x = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$i[2]];
        return move_uploaded_file($tmp, "$dir/$n.$x") ? "$n.$x" : '';
    }
    $im = @imagecreatefromstring(file_get_contents($tmp));
    if (!$im) return '';
    if ($i[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $rot = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 1] ?? 0;
        if ($rot) $im = imagerotate($im, $rot, 0);
    }
    if (imagesx($im) > 1600) { $s = imagescale($im, 1600); if ($s) $im = $s; }
    $w = imagesx($im); $hh = imagesy($im);
    $c = imagecreatetruecolor($w, $hh);
    imagefill($c, 0, 0, 0xFFFFFF);
    imagecopy($c, $im, 0, 0, 0, 0, $w, $hh);
    if (!imagejpeg($c, "$dir/$n.jpg", 85)) return '';
    if ($w > 600) { $sm = imagescale($c, 600); if ($sm) imagejpeg($sm, "$dir/$n-s.jpg", 82); }
    return "$n.jpg";
}
function vk_post($p) {
    $t = cfg('vk_token'); $g = cfg('vk_group_id');
    if (!$t || !$g) return false;
    $r = @file_get_contents('https://api.vk.com/method/wall.post', false, stream_context_create(['http' => [
        'method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'timeout' => 15,
        'content' => http_build_query(['owner_id' => '-' . ltrim($g, '-'), 'from_group' => 1,
            'message' => $p['title'] . "\n\n" . mb_substr(trim($p['text']), 0, 600),
            'attachments' => SITE . '/blog/' . $p['slug'], 'access_token' => $t, 'v' => '5.199'])]]));
    $j = json_decode((string)$r, true);
    if (!isset($j['response'])) { log_error('VK: ' . $r); return false; }
    return true;
}

function ld($a) { return '<script type="application/ld+json">' . json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>'; }
function plain($t) {
    $t = preg_replace('~^!\[[^\]]*\]\([^)]*\)$~mu', '', $t);
    $t = preg_replace('~\[([^\]]+)\]\([^)]*\)~u', '$1', $t);
    $t = preg_replace('~^(## |- )~mu', '', $t);
    return trim(str_replace('*', '', $t));
}
function img_tag($n, $alt, $sizes = '(max-width:860px) 100vw, 860px', $lazy = true) {
    $d = __DIR__ . '/uploads/';
    $i = @getimagesize($d . $n);
    $sm = preg_replace('~(\.\w+)$~', '-s$1', $n);
    $set = is_file($d . $sm) ? ' srcset="/blog/uploads/' . h($sm) . ' 600w, /blog/uploads/' . h($n) . ' ' . ($i ? $i[0] : 1600) . 'w" sizes="' . $sizes . '"' : '';
    return '<img src="/blog/uploads/' . h($n) . '"' . $set . ' alt="' . h($alt) . '"' . ($i ? ' width="' . $i[0] . '" height="' . $i[1] . '"' : '') . ($lazy ? ' loading="lazy"' : '') . '>';
}
function del_img($n) { foreach ([$n, preg_replace('~(\.\w+)$~', '-s$1', $n)] as $f) @unlink(__DIR__ . '/uploads/' . basename($f)); }
function del_post_imgs($p) {
    if (!empty($p['img'])) del_img($p['img']);
    if (preg_match_all('~\(([a-f0-9]{12}\.[a-z]+)\)~', $p['text'], $m)) foreach ($m[1] as $n) del_img($n);
}
function cards($list) {
    $b = '';
    foreach ($list as $p) $b .= '<a class="card" href="/blog/' . h($p['slug']) . '">' . ($p['img'] ? img_tag($p['img'], $p['alt'], '(max-width:600px) 100vw, 280px') : '') . '<div><h2>' . h($p['title']) . '</h2><span class="d">' . date('d.m.Y', $p['ts']) . '</span><p>' . h(cut(plain($p['text']), 110)) . '</p></div></a>';
    return $b;
}

function auth_key() { return hash('sha256', 'blog|' . cfg('blog_password', '') . '|' . cfg('form_secret', '')); }
function auth_ok() {
    $p = explode('.', (string)($_COOKIE['blog_auth'] ?? ''));
    return cfg('blog_password', '') !== '' && count($p) === 2 && ctype_digit($p[0]) && time() - (int)$p[0] < 86400 * 180 && hash_equals(hash_hmac('sha256', $p[0], auth_key()), $p[1]);
}
function auth_set() { $t = (string)time(); setcookie('blog_auth', $t . '.' . hash_hmac('sha256', $t, auth_key()), ['expires' => time() + 86400 * 180, 'path' => '/blog/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]); }
function auth_clear() { setcookie('blog_auth', '', ['expires' => time() - 3600, 'path' => '/blog/']); }
