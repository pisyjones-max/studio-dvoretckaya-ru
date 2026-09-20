<?php
require_once __DIR__ . '/../lib.php';
const SITE = 'https://studio.dvoretckaya.ru';
const CSS = "body{margin:0;font-family:Montserrat,sans-serif;background:#f7f3f2;color:#333;line-height:1.7}header{background:#fff;box-shadow:0 2px 6px #0001;display:flex;justify-content:space-between;align-items:center;padding:8px 20px;position:sticky;top:0}header img{height:60px}nav a{margin-left:18px;color:#333;text-decoration:none}nav a:hover{color:#C2828F}main{max-width:860px;margin:0 auto;padding:32px 16px}h1,h2{font-family:'Playfair Display',serif;color:#A56A75;line-height:1.25}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}.card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px #0001;text-decoration:none;color:inherit}.card img{width:100%;height:180px;object-fit:cover;display:block}.card div{padding:14px}.card h2{font-size:20px;margin:0 0 6px}.d{color:#999;font-size:13px}article img{max-width:100%;height:auto;border-radius:12px}article a{color:#C2828F}footer{background:#C2828F;color:#fff;text-align:center;padding:22px;font-size:14px}footer a{color:#fff}";

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
function text_html($t) {
    $o = '';
    foreach (preg_split('~\R{2,}~', trim($t)) as $para) {
        $e = nl2br(h(trim($para)), false);
        $e = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" rel="nofollow noopener" target="_blank">$1</a>', $e);
        $o .= "<p>$e</p>\n";
    }
    return $o;
}
function layout($title, $desc, $canon, $body, $img = '') {
    $og = $img ? '<meta property="og:image" content="' . h($img) . '">' : '';
    return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title><meta name="description" content="' . h($desc) . '"><link rel="canonical" href="' . h($canon) . '"><meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '"><meta property="og:type" content="article">' . $og . '<link rel="icon" href="/img/favicon.png"><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet"><style>' . CSS . '</style></head><body><header><a href="/"><img src="/img/Elegant-Art-Studio-Logo.webp" alt="Студия творчества и вдохновения Ольги Дворецкой"></a><nav><a href="/">Главная</a><a href="/blog/">Блог</a><a href="/#contact">Контакты</a></nav></header><main>' . $body . '</main><footer>© Студия творчества и вдохновения · <a href="tel:+79264281144">+7 (926) 428-11-44</a></footer></body></html>';
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
    return imagejpeg($c, "$dir/$n.jpg", 85) ? "$n.jpg" : '';
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
