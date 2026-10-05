<?php // Shared <head> contents for every page (app layout, auth pages, error pages). All assets are local and versioned. ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#082B66">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="preload" href="<?= e(asset('fonts/atkinson-hyperlegible-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('fonts/montserrat-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
<script>/* Text size (A / A+ / A++) before first paint, so the page doesn't jump. See ui.js. */
try { var s = localStorage.getItem('sk_text_size'); if (s === '115' || s === '130') document.documentElement.setAttribute('data-text-size', s); } catch (e) {}</script>
