<?php
// Shared top of every sign-in / registration / password / error page. Expects $pageTitle.
// Pages close with layouts/auth_foot.php.
?>
<!doctype html>
<html lang="en">
<head>
<?php require VIEW_PATH . '/layouts/head_assets.php'; ?>
<title><?= e($pageTitle) ?> · <?= e(APP_NAME_SHORT) ?></title>
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="auth-topbar"><?php require VIEW_PATH . '/layouts/text_size.php'; ?></div>
<main id="main" class="auth-wrap" tabindex="-1">
