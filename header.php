<?php
/**
 * header.php - layout ຫຼັງບ້ານ (sidebar + topbar)
 * ໜ້າທີ່ include ຕ້ອງກຳນົດ $page ແລະ $title ກ່ອນ
 */
require_once __DIR__ . '/config.php';
require_login();
$u = current_user();
$is_admin   = $u['role'] === 'admin';
$is_manager = in_array($u['role'], ['admin', 'manager']);

$menu = [
    ['dashboard',  'dashboard.php',  '📊 ໜ້າຫຼັກ (Dashboard)', $is_manager],
    ['pos',        'pos.php',        '🛒 ໜ້າຂາຍ (POS)',        true],
    ['products',   'products.php',   '📦 ສິນຄ້າ',               $is_manager],
    ['categories', 'categories.php', '🏷️ ໝວດໝູ່ສິນຄ້າ',        $is_manager],
    ['barcode',    'barcode.php',    '𝄃𝄃 ພິມບາໂຄດ',            $is_manager],
    ['purchases',  'purchases.php',  '📥 ຮັບສິນຄ້າເຂົ້າສາງ',    $is_manager],
    ['suppliers',  'suppliers.php',  '🚚 ຜູ້ສະໜອງ',            $is_manager],
    ['customers',  'customers.php',  '👥 ລູກຄ້າ / ສະມາຊິກ',     $is_manager],
    ['shifts',     'shifts.php',     '⏰ ກະການເຮັດວຽກ',         true],
    ['reports',    'reports.php',    '📈 ລາຍງານ',               $is_manager],
    ['users',      'users.php',      '👤 ພະນັກງານ / ຜູ້ໃຊ້',    $is_admin],
    ['settings',   'settings.php',   '⚙️ ຕັ້ງຄ່າຮ້ານ',          $is_admin],
];
?>
<!DOCTYPE html>
<html lang="lo">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'POS') ?> - <?= h(get_setting('store_name')) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">🛒 POS<br><small><?= h(get_setting('store_name')) ?></small></div>
    <nav>
      <?php foreach ($menu as [$key, $url, $label, $allowed]): if (!$allowed) continue; ?>
        <a href="<?= $url ?>" class="<?= ($page ?? '') === $key ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">👤 <?= h($u['fullname']) ?><br><small><?= h($u['role']) ?></small></div>
      <a href="logout.php" class="btn btn-danger btn-sm btn-block">ອອກຈາກລະບົບ</a>
    </div>
  </aside>
  <main class="content">
    <h1 class="page-title"><?= h($title ?? '') ?></h1>
    <?php flash_show(); ?>
