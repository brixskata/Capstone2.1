<?php

$page_title = $page_title ?? 'MikeMadz - Frozen Product Store';
$page_description = $page_description ?? 'Fresh, quality frozen products delivered to your doorstep. Shop frozen products, frozen meat, frozen seafood, and more.';
$page_keywords = $page_keywords ?? 'frozen products, frozen meat, frozen seafood, online frozen product shop, MikeMadz';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= htmlspecialchars($page_description) ?>">
<meta name="keywords" content="<?= htmlspecialchars($page_keywords) ?>">
<meta name="author" content="MikeMadz">

<!-- Open Graph Meta Tags -->
<meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="https://mikemadz.com">
<meta property="og:image" content="https://mikemadz.com/favicon.png">

<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="favicon.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="favicon.png">
<link rel="shortcut icon" href="favicon.png">

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
