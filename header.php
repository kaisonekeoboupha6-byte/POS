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
    ['dashboard',  'dashboard.php',  'layout-dashboard', 'ໜ້າຫຼັກ (Dashboard)', $is_manager],
    ['pos',        'pos.php',        'shopping-cart',    'ໜ້າຂາຍ (POS)',        true],
    ['products',   'products.php',   'package',          'ສິນຄ້າ',               $is_manager],
    ['categories', 'categories.php', 'tag',              'ໝວດໝູ່ສິນຄ້າ',        $is_manager],
    ['barcode',    'barcode.php',    'barcode',          'ພິມບາໂຄດ',            $is_manager],
    ['purchases',  'purchases.php',  'arrow-down-left',  'ຮັບສິນຄ້າເຂົ້າສາງ',    $is_manager],
    ['suppliers',  'suppliers.php',  'truck',            'ຜູ້ສະໜອງ',            $is_manager],
    ['customers',  'customers.php',  'users',            'ລູກຄ້າ / ສະມາຊິກ',     $is_manager],
    ['shifts',     'shifts.php',     'clock',            'ກະການເຮັດວຽກ',         true],
    ['reports',    'reports.php',    'bar-chart-3',      'ລາຍງານ',               $is_manager],
    ['users',      'users.php',      'user-cog',         'ພະນັກງານ / ຜູ້ໃຊ້',    $is_admin],
    ['settings',   'settings.php',   'settings',         'ຕັ້ງຄ່າຮ້ານ',          $is_admin],
];
?>
<!DOCTYPE html>
<html lang="lo">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'POS') ?> - <?= h(get_setting('store_name')) ?></title>
<link rel="stylesheet" href="style.css">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <div style="width:34px; height:34px; background:#fff; color:var(--text); border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px;">P</div>
      <div>
        <?= h(get_setting('store_name', 'POS Terminal')) ?>
        <small>ລະບົບຈັດການຮ້ານຄ້າ</small>
      </div>
    </div>
    <nav>
      <?php foreach ($menu as [$key, $url, $icon, $label, $allowed]): if (!$allowed) continue; ?>
        <a href="<?= $url ?>" class="<?= ($page ?? '') === $key ? 'active' : '' ?>">
          <i data-lucide="<?= $icon ?>"></i>
          <span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <i data-lucide="user" style="width:16px; height:16px;"></i>
        <div>
          <div><?= h($u['fullname']) ?></div>
          <small><?= strtoupper(h($u['role'])) ?></small>
        </div>
      </div>
      <a href="logout.php" class="btn btn-secondary btn-sm btn-block" style="background:rgba(255,255,255,0.1); color:#fff; border-color:transparent;">
        <i data-lucide="log-out" style="width:14px; height:14px;"></i>
        <span>ອອກຈາກລະບົບ</span>
      </a>
    </div>
  </aside>
  <main class="content">
    <h1 class="page-title"><?= h($title ?? '') ?></h1>
    <?php flash_show(); ?>
