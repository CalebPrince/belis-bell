<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Belis Bell') ?></title>
<meta name="description" content="Cleaning supplies and more for homes, businesses and institutions in Ghana.">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" type="image/webp" href="<?= asset('brand/logo-mark.webp') ?>">
<script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body>
<a href="#main" class="skip-link">Skip to main content</a>
<?php include __DIR__ . '/partials/header.php'; ?>
<main id="main">
<?= raw($content) ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
