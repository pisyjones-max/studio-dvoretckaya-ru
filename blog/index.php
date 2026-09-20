<?php
require __DIR__ . '/_lib.php';
$all = posts(); $per = 9; $pages = max(1, (int)ceil(count($all) / $per));
$pg = min($pages, max(1, (int)($_GET['p'] ?? 1)));
$b = '<nav class="bc"><a href="/">Главная</a> › Блог</nav><h1>Блог студии</h1>';
if (!$all) $b .= '<p>Скоро здесь появятся первые записи.</p>';
$b .= '<div class="grid">' . cards(array_slice($all, ($pg - 1) * $per, $per)) . '</div>';
if ($pages > 1) $b .= '<div class="pg"><span>' . ($pg > 1 ? '<a href="/blog/' . ($pg > 2 ? '?p=' . ($pg - 1) : '') . '">← Новее</a>' : '') . '</span><span>' . ($pg < $pages ? '<a href="/blog/?p=' . ($pg + 1) . '">Старее →</a>' : '') . '</span></div>';
echo layout('Блог — Студия творчества и вдохновения Ольги Дворецкой' . ($pg > 1 ? ' — страница ' . $pg : ''), 'Новости, идеи и истории студии рисования, эбру и песочной анимации в Раменском.', SITE . '/blog/' . ($pg > 1 ? '?p=' . $pg : ''), $b);
