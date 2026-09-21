<?php
require __DIR__ . '/_lib.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Robots-Tag: noindex');
$out = function ($b) { echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Блог — админка</title><style>body{font:16px/1.5 sans-serif;max-width:720px;margin:20px auto;padding:0 14px}input,textarea{width:100%;box-sizing:border-box;padding:9px;margin:4px 0 12px;font:inherit;border:1px solid #bbb;border-radius:6px}button{background:#C2828F;color:#fff;border:0;padding:11px 20px;border-radius:6px;font:inherit;cursor:pointer}.e{color:#b00}.o{color:#080}li{margin:10px 0}.x{background:#999;padding:4px 10px;font-size:14px}form.i{display:inline}</style>' . $b; };
$pw = (string)cfg('blog_password', '');
if ($pw === '') { $out('<p>Задайте blog_password в config.php на хостинге.</p>'); exit; }
if (isset($_POST['pw'])) {
    if (hash_equals($pw, (string)$_POST['pw'])) { session_regenerate_id(true); $_SESSION['ok'] = 1; $_SESSION['csrf'] = bin2hex(random_bytes(16)); auth_set(); header('Location: admin.php'); exit; }
    sleep(1); $bad = 1;
}
if (isset($_GET['out'])) { auth_clear(); session_destroy(); header('Location: admin.php'); exit; }
if (empty($_SESSION['ok']) && auth_ok()) { $_SESSION['ok'] = 1; $_SESSION['csrf'] = bin2hex(random_bytes(16)); auth_set(); }
if (empty($_SESSION['ok'])) { $out('<h2>Вход</h2>' . (!empty($bad) ? '<p class="e">Неверный пароль</p>' : '') . '<form method="post"><input type="password" name="pw" placeholder="Пароль" autofocus><button>Войти</button></form>'); exit; }
$csrf = hash_hmac('sha256', 'csrf', auth_key()); // стабильный: не ломается, если сессия истекла при открытой форме
if (isset($_GET['up'])) { header('Content-Type: application/json'); $n = ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, (string)($_POST['csrf'] ?? '')) && isset($_FILES['f']) && $_FILES['f']['error'] === 0) ? save_photo($_FILES['f']['tmp_name']) : ''; echo json_encode(['n' => $n]); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $all = store_read('blog_posts', []);
    if (isset($_POST['del'])) {
        foreach ($all as $i => $p) if ($p['slug'] === $_POST['del']) { del_post_imgs($p); unset($all[$i]); }
        store_write('blog_posts', array_values($all)); header('Location: admin.php?ok=2'); exit;
    }
    if (isset($_POST['title'])) {
        $title = trim($_POST['title']); $text = trim($_POST['text'] ?? ''); $alt = trim($_POST['alt'] ?? ''); $edit = (string)($_POST['edit'] ?? ''); $desc = mb_substr(trim($_POST['desc'] ?? ''), 0, 160);
        $idx = null; foreach ($all as $i => $p) if ($p['slug'] === $edit) $idx = $i;
        $img = $idx !== null ? $all[$idx]['img'] : '';
        $old = $img;
        $up = $_FILES['photo'] ?? null;
        if ($up && $up['error'] === 0) { $r = save_photo($up['tmp_name']); if ($r) $img = $r; else $err = 'Фото не загрузилось (нужен JPG, PNG или WebP).'; }
        elseif ($up && in_array($up['error'], [1, 2], true)) $err = 'Фото слишком большое.';
        if (!$err && ($title === '' || $text === '')) $err = 'Заполните заголовок и текст.';
        if (!$err && $img !== '' && $alt === '') $err = 'Опишите фото одной фразой (для поиска и незрячих читателей).';
        if (!$err) {
            if ($img !== $old && $old) del_img($old);
            if ($idx === null) {
                $slug = slugify($title) . '-' . date('ymd'); $n = 2; $base = $slug;
                while (post_get($slug)) $slug = $base . '-' . $n++;
                $post = ['slug' => $slug, 'title' => $title, 'text' => $text, 'img' => $img, 'alt' => $alt, 'desc' => $desc, 'ts' => time(), 'vk' => 0];
                $post['vk'] = vk_post($post) ? 1 : 0;
                $all[] = $post;
            } else { $all[$idx] = array_merge($all[$idx], ['title' => $title, 'text' => $text, 'img' => $img, 'alt' => $alt, 'desc' => $desc, 'upd' => time()]); }
            store_write('blog_posts', array_values($all)); header('Location: admin.php?ok=1'); exit;
        }
    }
}
$e = isset($_GET['e']) ? post_get((string)$_GET['e']) : null;
$f = $e ?: ['title' => '', 'text' => '', 'alt' => '', 'slug' => '', 'img' => ''];
if ($err) $f = ['title' => $_POST['title'] ?? '', 'text' => $_POST['text'] ?? '', 'alt' => $_POST['alt'] ?? '', 'desc' => $_POST['desc'] ?? '', 'slug' => $_POST['edit'] ?? '', 'img' => ''];
$b = '<p><a href="/blog/">Блог</a> · <a href="import.php">Массовая загрузка (JSON)</a> · <a href="?out=1">Выйти</a></p><h2>' . ($f['slug'] ? 'Редактировать запись' : 'Новая запись') . '</h2>';
if ($err) $b .= '<p class="e">' . h($err) . '</p>';
if (isset($_GET['ok'])) $b .= '<p class="o">' . ($_GET['ok'] === '2' ? 'Удалено.' : 'Опубликовано.') . '</p>';
$b .= '<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="edit" value="' . h($f['slug']) . '">' .
    'Заголовок<input name="title" value="' . h($f['title']) . '" required>Текст (абзацы — через пустую строку)<div><button type="button" class="x" data-a="**" data-b="**"><b>Ж</b></button> <button type="button" class="x" data-a="*" data-b="*"><i>К</i></button> <button type="button" class="x" data-a="## " data-b="">Заголовок</button> <button type="button" class="x" data-a="- " data-b="">Список</button> <button type="button" class="x" data-l="1">Ссылка</button> <button type="button" class="x" id="ph">Фото в текст</button><input type="file" id="pf" accept="image/*" style="display:none"></div><textarea id="t" name="text" rows="14" required>' . h($f['text']) . '</textarea>' .
    'Фото' . ($f['img'] ? ' (уже есть, можно заменить)' : '') . '<input type="file" name="photo" accept="image/*">Описание фото<input name="alt" value="' . h($f['alt']) . '">Описание для поиска (необязательно, до 160 символов)<input name="desc" maxlength="160" value="' . h($f['desc'] ?? '') . '">' .
    '<button>' . ($f['slug'] ? 'Сохранить' : 'Опубликовать') . '</button></form><h2>Записи</h2><ul>';
foreach (posts() as $p) {
    $b .= '<li>' . date('d.m.Y', $p['ts']) . ' — <a href="/blog/' . h($p['slug']) . '">' . h($p['title']) . '</a> <a href="?e=' . h($p['slug']) . '">изменить</a> ' .
        '<form class="i" method="post" onsubmit="return confirm(\'Удалить?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="del" value="' . h($p['slug']) . '"><button class="x">удалить</button></form></li>';
}
$out($b . '</ul><script>function w(a,b){var t=document.getElementById("t"),s=t.selectionStart,e=t.selectionEnd,v=t.value;t.value=v.slice(0,s)+a+v.slice(s,e)+b+v.slice(e);t.focus();t.selectionStart=s+a.length;t.selectionEnd=e+a.length}document.querySelectorAll("[data-a],[data-l]").forEach(function(b){b.onclick=function(){if(b.dataset.l){var u=prompt("Адрес ссылки (https://...)");if(u)w("[","]("+u+")")}else w(b.dataset.a,b.dataset.b)}});var pf=document.getElementById("pf");document.getElementById("ph").onclick=function(){pf.click()};pf.onchange=function(){if(!pf.files[0])return;var fd=new FormData();fd.append("f",pf.files[0]);fd.append("csrf",document.querySelector("[name=csrf]").value);fetch("admin.php?up=1",{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){if(!j.n){alert("Фото не загрузилось");return}var a=(prompt("Опишите фото одной фразой (для поиска)")||"").replace(/[\[\]()]/g,"").trim()||document.querySelector("[name=title]").value;w("\n\n!["+a+"]("+j.n+")\n\n","");pf.value=""}).catch(function(){alert("Ошибка загрузки")})}</script>');
