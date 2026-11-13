<?php
/**
 * header.php - layout ຫຼັງບ້ານ (grouped sidebar + sticky topbar + theme)
 * ໜ້າທີ່ include ຕ້ອງກຳນົດ $page ແລະ $title ກ່ອນ
 */
require_once __DIR__ . '/config.php';
require_login();
$u = current_user();
$is_admin   = $u['role'] === 'admin';
$is_manager = in_array($u['role'], ['admin', 'manager']);
$open_shift = get_open_shift($u['id']);
$low_stock_count = $is_manager
    ? (isset($precomputed_low_stock_count)
        ? (int)$precomputed_low_stock_count
        : (int)$pdo->query('SELECT COUNT(*) FROM products WHERE status = 1 AND stock_qty <= min_stock')->fetchColumn())
    : 0;

$menu_groups = [
    'ພາບລວມ & ຂາຍ' => [
        ['dashboard', 'dashboard.php', 'layout-dashboard', 'ໜ້າຫຼັກ (Dashboard)', $is_manager],
        ['pos',       'pos.php',       'shopping-cart',    'ໜ້າຂາຍ (POS)',        true],
        ['shifts',    'shifts.php',    'clock',            'ກະການເຮັດວຽກ',         true],
    ],
    'ສິນຄ້າ & ສາງ' => [
        ['products',   'products.php',   'package',         'ສິນຄ້າ',            $is_manager],
        ['categories', 'categories.php', 'tag',             'ໝວດໝູ່ສິນຄ້າ',     $is_manager],
        ['barcode',    'barcode.php',    'barcode',         'ພິມບາໂຄດ',         $is_manager],
        ['purchases',  'purchases.php',  'arrow-down-left', 'ຮັບສິນຄ້າເຂົ້າສາງ', $is_manager],
        ['suppliers',  'suppliers.php',  'truck',           'ຜູ້ສະໜອງ',         $is_manager],
    ],
    'ລູກຄ້າ & ລາຍງານ' => [
        ['customers', 'customers.php', 'users',       'ລູກຄ້າ / ສະມາຊິກ', $is_manager],
        ['reports',   'reports.php',   'bar-chart-3', 'ລາຍງານ',           $is_manager],
    ],
    'ລະບົບ' => [
        ['users',    'users.php',    'user-cog', 'ພະນັກງານ / ຜູ້ໃຊ້', $is_admin],
        ['settings', 'settings.php', 'settings', 'ຕັ້ງຄ່າຮ້ານ',       $is_admin],
    ],
];

$initials = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $u['username'] ?? '') ?: 'U', 0, 2));
?>
<!DOCTYPE html>
<html lang="lo" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'POS') ?> - <?= h(get_setting('store_name')) ?></title>
<script>
  (function () {
    try {
      var t = localStorage.getItem('pos_theme');
      if (t === 'dark' || t === 'light') {
        document.documentElement.setAttribute('data-theme', t);
      }
    } catch (e) {}
  })();
</script>
<link rel="stylesheet" href="style.css?v=<?= asset_v('style.css') ?>">
<script src="assets/vendor/lucide.min.js?v=<?= asset_v('assets/vendor/lucide.min.js') ?>" defer></script>
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeMobileSidebar()"></div>
<div class="layout">
  <aside class="sidebar" id="appSidebar" aria-label="ເມນູຫຼັກ">
    <div class="brand">
      <div class="brand-mark" aria-hidden="true">P</div>
      <div class="brand-text">
        <span class="brand-title"><?= h(get_setting('store_name', 'POS Terminal')) ?></span>
        <small>ລະບົບຈັດການຮ້ານຄ້າ</small>
      </div>
    </div>
    <nav>
      <?php foreach ($menu_groups as $groupLabel => $items):
        $visible = array_filter($items, fn($it) => (bool)$it[4]);
        if (!$visible) continue;
      ?>
        <div class="sidebar-group-label"><?= h($groupLabel) ?></div>
        <?php foreach ($visible as [$key, $url, $icon, $label]): ?>
          <?php
            $isActive = ($page ?? '') === $key;
            $cls = trim(($isActive ? 'active ' : '') . ($key === 'pos' && !$isActive ? 'nav-pos-highlight' : ''));
          ?>
          <a href="<?= $url ?>" class="<?= $cls ?>" title="<?= h($label) ?>">
            <i data-lucide="<?= $icon ?>" aria-hidden="true"></i>
            <span><?= h($label) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar" aria-hidden="true"><?= h($initials) ?></div>
        <div class="user-meta" style="min-width:0;">
          <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($u['fullname']) ?></div>
          <small><?= strtoupper(h($u['role'])) ?></small>
        </div>
      </div>
      <a href="logout.php" class="btn btn-secondary btn-sm btn-block sidebar-logout" title="ອອກຈາກລະບົບ">
        <i data-lucide="log-out" aria-hidden="true" style="width:14px;height:14px;"></i>
        <span>ອອກຈາກລະບົບ</span>
      </a>
    </div>
  </aside>

  <div class="main-shell">
    <header class="topbar no-print">
      <div class="topbar-left">
        <button type="button" class="btn btn-secondary icon-btn mobile-menu-btn" id="mobileMenuBtn" onclick="openMobileSidebar()" aria-label="ເປີດເມນູ">
          <i data-lucide="menu" aria-hidden="true"></i>
        </button>
        <button type="button" class="btn btn-secondary icon-btn desktop-collapse-btn" id="sidebarCollapseBtn" onclick="toggleSidebarCollapse()" aria-label="ຫຍໍ້/ຂະຫຍາຍແຖບຂ້າງ" title="ຫຍໍ້/ຂະຫຍາຍເມນູ">
          <i data-lucide="panel-left" aria-hidden="true"></i>
        </button>
        <div class="topbar-title-wrap">
          <span class="topbar-crumb"><?= h($title ?? 'POS') ?></span>
        </div>
      </div>

      <div class="topbar-right">
        <a href="shifts.php" class="topbar-pill <?= $open_shift ? 'pill-success' : 'pill-warning' ?>" title="ສະຖານະກະເຮັດວຽກ">
          <?php if ($open_shift): ?>
            <span class="pulse-dot" aria-hidden="true"></span>
            <span>ກະ #<?= (int)$open_shift['id'] ?></span>
            <span class="pill-text-hide-sm">ກຳລັງເປີດ</span>
          <?php else: ?>
            <i data-lucide="clock" aria-hidden="true" style="width:14px;height:14px;"></i>
            <span class="pill-text-hide-sm">ຍັງບໍ່ເປີດກະ</span>
          <?php endif; ?>
        </a>

        <?php if ($is_manager && $low_stock_count > 0): ?>
        <a href="dashboard.php#low-stock" class="topbar-pill pill-danger" title="ສິນຄ້າໃກ້ໝົດສະຕ໋ອກ">
          <i data-lucide="alert-triangle" aria-hidden="true" style="width:14px;height:14px;"></i>
          <span class="tabular"><?= $low_stock_count ?></span>
          <span class="pill-text-hide-sm">ໃກ້ໝົດສະຕ໋ອກ</span>
        </a>
        <?php endif; ?>

        <a href="pos.php" class="btn btn-success btn-sm" title="ເປີດໜ້າຂາຍ POS">
          <i data-lucide="shopping-cart" aria-hidden="true"></i>
          <span class="pill-text-hide-sm">ໜ້າຂາຍ POS</span>
        </a>

        <button type="button" class="btn btn-secondary icon-btn" id="themeToggleBtn" onclick="toggleTheme()" aria-label="ສະຫຼັບໂໝດແສງ/ມືດ" title="ສະຫຼັບ Light / Dark Mode">
          <i data-lucide="moon" id="themeIcon" aria-hidden="true"></i>
        </button>
      </div>
    </header>

    <main class="content">
      <div class="page-header no-print">
        <h1 class="page-title"><?= h($title ?? '') ?></h1>
      </div>
      <?php flash_show(); ?>
