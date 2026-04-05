<?php
declare(strict_types=1);
/** @var string $content */
/** @var string $title */
$bodyClass = isset($bodyClass) && is_string($bodyClass) ? $bodyClass : '';
$customStyles = isset($customStyles) && is_string($customStyles) ? $customStyles : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($customStyles): ?>
    <style>
    <?= $customStyles ?>
    </style>
    <?php endif; ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<?= $content ?>
</body>
</html>
