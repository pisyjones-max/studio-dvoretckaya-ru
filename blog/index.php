<?php
require __DIR__ . '/_lib.php';
$b = '<h1>Блог студии</h1>';
$all = posts();
if (!$all) $b .= '<p>Скоро здесь появятся первые записи.</p>';
$b .= '<div class="grid">';
foreach ($all as $p) {
    $b .= '<a class="card" href="/blog/' . h($p['slug']) . '">' . ($p['img'] ? '<img loading="lazy" src="/blog/uploads/' . h($p['img']) . '" alt="' . h($p['alt']) . '">' : '') .
        '<div><h2>' . h($p['title']) . '</h2><span class="d">' . date('d.m.Y', $p['ts']) . '</span><p>' . h(cut($p['text'], 110)) . '</p></div></a>';
}
echo layout('Блог — Студия творчества и вдохновения Ольги Дворецкой', 'Новости, идеи и истории студии рисования, эбру и песочной анимации в Раменском.', SITE . '/blog/', $b . '</div>');
