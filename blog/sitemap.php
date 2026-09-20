<?php
require __DIR__ . '/_lib.php';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . SITE . '/blog/</loc></url>';
foreach (posts() as $p) echo '<url><loc>' . SITE . '/blog/' . h($p['slug']) . '</loc><lastmod>' . date('Y-m-d', $p['upd'] ?? $p['ts']) . '</lastmod></url>';
echo '</urlset>';
