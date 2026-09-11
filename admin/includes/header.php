<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $page_title ?></title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<link rel="stylesheet" href="/ONLINE_EXAMINATION/admin/assets/css/admin-dashboard.css">
<link rel="stylesheet" href="/ONLINE_EXAMINATION/assets/css/accessibility.css">
<?php if (!empty($page_css)): ?>
<link rel="stylesheet" href="/ONLINE_EXAMINATION/admin/assets/css/<?= htmlspecialchars($page_css, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>
