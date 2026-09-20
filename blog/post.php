<?php
require __DIR__ . '/_lib.php';
$s = (string)($_GET['s'] ?? '');
$all = posts(); $k = null;
foreach ($all as $i => $x) if ($x['slug'] === $s) $k = $i;
if ($k === null) { http_response_code(404); echo layout('Не найдено', '', SITE . '/blog/', '<h1>Запись не найдена</h1><p><a href="/blog/">← Все записи</a></p>'); exit; }
$p = $all[$k];
$GLOBALS['edit_slug'] = $p['slug'];
$url = SITE . '/blog/' . $p['slug'];
$desc = !empty($p['desc']) ? $p['desc'] : cut(plain($p['text']), 160);
$upd = $p['upd'] ?? $p['ts'];
$newer = $all[$k - 1] ?? null; $older = $all[$k + 1] ?? null;
$rel = array_slice(array_values(array_filter($all, function ($x) use ($s) { return $x['slug'] !== $s; })), 0, 3);
$b = '<article><nav class="bc"><a href="/">Главная</a> › <a href="/blog/">Блог</a> › ' . h(cut($p['title'], 50)) . '</nav><h1>' . h($p['title']) . '</h1><p class="d">' . date('d.m.Y', $p['ts']) . (date('Ymd', $upd) !== date('Ymd', $p['ts']) ? ' · обновлено ' . date('d.m.Y', $upd) : '') . '</p>' .
    ($p['img'] ? '<figure>' . img_tag($p['img'], $p['alt'], '(max-width:860px) 100vw, 860px', false) . '</figure>' : '') . text_html($p['text']) . '</article>' .
    '<div class="cta"><p>Понравилось? Приходите к нам в студию.</p><a class="btn" href="/#contact">Записаться</a><p><a href="/#directions">Направления</a> · <a href="/#teachers">Преподаватели</a> · <a href="/#gallery">Галерея</a></p></div>' .
    '<div class="n2"><span>' . ($newer ? '<a href="/blog/' . h($newer['slug']) . '">← ' . h($newer['title']) . '</a>' : '') . '</span><span style="text-align:right">' . ($older ? '<a href="/blog/' . h($older['slug']) . '">' . h($older['title']) . ' →</a>' : '') . '</span></div>';
if ($rel) $b .= '<h2>Читайте также</h2><div class="grid">' . cards($rel) . '</div>';
$org = ['@type' => 'Organization', 'name' => 'Студия творчества и вдохновения Ольги Дворецкой', 'url' => SITE . '/'];
$art = ['@type' => 'Article', 'headline' => $p['title'], 'datePublished' => date('c', $p['ts']), 'dateModified' => date('c', $upd), 'mainEntityOfPage' => $url, 'author' => $org, 'publisher' => $org];
if ($p['img']) $art['image'] = SITE . '/blog/uploads/' . $p['img'];
$bc = ['@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => SITE . '/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Блог', 'item' => SITE . '/blog/'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title'], 'item' => $url]]];
echo layout($p['title'] . ' — Студия Ольги Дворецкой', $desc, $url, $b, $p['img'] ? SITE . '/blog/uploads/' . $p['img'] : '', ld(['@context' => 'https://schema.org', '@graph' => [$art, $bc]]));
