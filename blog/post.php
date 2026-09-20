<?php
require __DIR__ . '/_lib.php';
$s = (string)($_GET['s'] ?? '');
$p = preg_match('~^[a-z0-9-]+$~', $s) ? post_get($s) : null;
if (!$p) { http_response_code(404); echo layout('Не найдено', '', SITE . '/blog/', '<h1>Запись не найдена</h1><p><a href="/blog/">← Все записи</a></p>'); exit; }
$img = $p['img'] ? '<img src="/blog/uploads/' . h($p['img']) . '" alt="' . h($p['alt']) . '">' : '';
echo layout($p['title'] . ' — Студия Ольги Дворецкой', cut($p['text'], 160), SITE . '/blog/' . $p['slug'],
    '<article><p class="d">' . date('d.m.Y', $p['ts']) . '</p><h1>' . h($p['title']) . '</h1>' . $img . text_html($p['text']) . '<p><a href="/blog/">← Все записи</a></p></article>',
    $p['img'] ? SITE . '/blog/uploads/' . $p['img'] : '');
