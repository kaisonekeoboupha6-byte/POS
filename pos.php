<?php
/**
 * pos.php - ໜ້າຂາຍສິນຄ້າ (Front-End / Cashier Terminal)
 * Commercial Retail UI/UX (Light + Dark Mode, Offline Vendor Safe)
 */
require_once __DIR__ . '/config.php';
require_login();
$u = current_user();
$shift = get_open_shift($u['id']);
$is_manager = in_array($u['role'], ['admin', 'manager']);

$products = $pdo->query(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1 ORDER BY p.name"
)->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$cat_counts = [];
foreach ($products as $p) {
    $cid = (string)($p['category_id'] ?? '');
    if ($cid !== '') {
        $cat_counts[$cid] = ($cat_counts[$cid] ?? 0) + 1;
    }
}

$shift_cash_expected = 0;
if ($shift) {
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(total),0) t FROM sales
         WHERE shift_id = ? AND status = 'completed' AND payment_method = 'cash'"
    );
    $st->execute([$shift['id']]);
    $shift_cash_expected = $shift['opening_cash'] + (float)$st->fetch()['t'];
}

$tax_rate    = (float)get_setting('tax_rate', 0);
$point_value = (int)get_setting('point_value', 100);
$point_rate  = (int)get_setting('point_rate', 10000);
$payment_qr  = get_setting('payment_qr');
$store_name  = get_setting('store_name', 'POS Terminal');
?>
<!DOCTYPE html>
<html lang="lo" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ໜ້າຂາຍ POS - <?= h($store_name) ?></title>
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
<link rel="stylesheet" href="style.css">
<script src="assets/vendor/lucide.min.js"></script>
<style>
  body.pos-body {
    height: 100dvh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: var(--bg);
  }

  .pos-topbar {
    height: 62px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 18px;
    gap: 12px;
    flex-shrink: 0;
    z-index: 20;
  }
  .pos-top-group { display: flex; align-items: center; gap: 10px; min-width: 0; }
  .pos-clock {
    padding: 5px 10px;
    border-radius: var(--radius-sm);
    background: var(--bg-subtle);
    border: 1px solid var(--border);
    font-family: var(--font-mono);
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text);
  }

  .pos-container {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 440px;
    overflow: hidden;
    min-height: 0;
  }

  .pos-catalog {
    display: flex;
    flex-direction: column;
    height: 100%;
    border-right: 1px solid var(--border);
    overflow: hidden;
    min-width: 0;
  }

  .pos-search-bar {
    padding: 12px 18px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 10px;
    align-items: center;
  }
  .kbd-hint {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    padding: 2px 7px;
    border-radius: 5px;
    font-family: var(--font-mono);
    font-size: 10.5px;
    font-weight: 700;
    background: var(--bg-subtle);
    color: var(--text-muted);
    border: 1px solid var(--border);
    pointer-events: none;
  }

  .cat-bar {
    display: flex;
    gap: 8px;
    padding: 10px 18px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
    scrollbar-width: none;
    flex-shrink: 0;
  }
  .cat-bar::-webkit-scrollbar { display: none; }
  .cat-chip {
    height: 38px;
    padding: 0 14px;
    border-radius: var(--radius-full);
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--text-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all var(--dur-fast) ease;
    font-family: inherit;
    touch-action: manipulation;
  }
  .cat-chip:hover { background: var(--bg-subtle); color: var(--text); border-color: var(--border-strong); }
  .cat-chip.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 2px 8px rgba(37, 99, 235, 0.24); }
  .cat-count {
    font-size: 11px;
    padding: 1px 6px;
    border-radius: var(--radius-full);
    background: var(--bg-subtle);
    color: var(--text-muted);
    font-variant-numeric: tabular-nums;
  }
  .cat-chip.active .cat-count { background: rgba(255, 255, 255, 0.22); color: #fff; }

  .prod-scroll { flex: 1; overflow-y: auto; padding: 16px 18px; }
  .prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(178px, 1fr)); gap: 14px; }
  .prod-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 12px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    transition: transform var(--dur-fast) var(--ease-out), border-color var(--dur-fast), box-shadow var(--dur-fast);
    min-height: 124px;
    box-shadow: var(--shadow-xs);
    position: relative;
    user-select: none;
    touch-action: manipulation;
  }
  .prod-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
  .prod-card:active { transform: scale(0.98); }
  .prod-card.in-cart { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-ring); }
  .prod-card.out { opacity: 0.5; cursor: not-allowed; }
  .prod-card.out:hover { transform: none; border-color: var(--border); box-shadow: var(--shadow-xs); }

  .prod-cart-qty {
    position: absolute;
    top: 8px;
    right: 8px;
    min-width: 24px;
    height: 24px;
    padding: 0 7px;
    border-radius: var(--radius-full);
    background: var(--primary);
    color: #fff;
    font-size: 11.5px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-sm);
    z-index: 2;
  }

  .prod-img-wrap {
    width: 100%;
    height: 92px;
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: var(--bg-subtle);
    margin-bottom: 10px;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-subtle);
  }
  .prod-img-wrap img { width: 100%; height: 100%; object-fit: cover; }

  .pos-ticket {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--card);
    overflow: hidden;
    min-width: 0;
  }

  .ticket-head {
    padding: 10px 16px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .customer-dropdown {
    position: absolute;
    top: 4px;
    left: 16px;
    right: 16px;
    background: var(--card-elevated);
    border: 1px solid var(--border-strong);
    border-radius: var(--radius-sm);
    max-height: 230px;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
    z-index: 35;
  }
  .customer-option {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background var(--dur-fast);
  }
  .customer-option:last-child { border-bottom: none; }
  .customer-option:hover { background: var(--surface-hover); }

  .cart-list { flex: 1; overflow-y: auto; padding: 10px 16px; }
  .cart-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 8px;
    border-bottom: 1px solid var(--border);
    gap: 10px;
    border-radius: var(--radius-sm);
  }
  .cart-row.flash { animation: flashCart 0.55s ease; }
  @keyframes flashCart { 0% { background: var(--primary-50); } 100% { background: transparent; } }

  .stepper-group {
    display: flex;
    align-items: center;
    border: 1px solid var(--border-strong);
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: var(--bg);
    flex-shrink: 0;
  }
  .stepper-btn {
    width: 34px;
    height: 36px;
    border: none;
    background: var(--bg-subtle);
    color: var(--text);
    font-weight: 800;
    font-size: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background var(--dur-fast);
    touch-action: manipulation;
  }
  .stepper-btn:hover { background: var(--border); }
  .stepper-val {
    width: 36px;
    text-align: center;
    font-size: 13.5px;
    font-weight: 800;
    cursor: pointer;
    color: var(--text);
    padding: 6px 0;
  }

  .ticket-footer {
    padding: 14px 18px 16px;
    border-top: 1px solid var(--border);
    background: var(--card);
  }

  .ticket-total-box {
    margin-top: 10px;
    padding: 12px 14px;
    border-radius: var(--radius);
    background: var(--bg-subtle);
    border: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: baseline;
  }

  .numpad-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
  .numpad-btn {
    height: 52px;
    background: var(--bg-subtle);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background var(--dur-fast), transform var(--dur-fast);
    touch-action: manipulation;
  }
  .numpad-btn:hover { background: var(--border); }
  .numpad-btn:active { transform: scale(0.97); }

  .quick-cash-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 12px 0; }
  .quick-cash-btn {
    height: 44px;
    background: var(--bg-subtle);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text);
    cursor: pointer;
    font-variant-numeric: tabular-nums;
    transition: background var(--dur-fast), border-color var(--dur-fast);
    touch-action: manipulation;
  }
  .quick-cash-btn:hover { background: var(--border); border-color: var(--border-strong); }

  .change-display {
    padding: 14px 18px;
    border-radius: var(--radius);
    background: var(--success-50);
    border: 1px solid var(--success-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
    color: var(--success-dark);
  }
  .change-display.insufficient {
    background: var(--danger-50);
    border-color: var(--danger-border);
    color: var(--danger-dark);
  }

  /* Toast Notification */
  .pos-toast {
    position: fixed;
    bottom: 24px;
    left: 24px;
    z-index: 200;
    background: var(--text);
    color: var(--card);
    padding: 12px 18px;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    font-size: 13.5px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    transform: translateY(20px);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.2s var(--ease-out), opacity 0.2s ease;
  }
  .pos-toast.show { transform: translateY(0); opacity: 1; }
  .pos-toast.toast-danger { background: var(--danger); color: #fff; }
  .pos-toast.toast-success { background: var(--success); color: #fff; }

  .pos-mobile-tabs {
    display: none;
    background: var(--card);
    border-top: 1px solid var(--border);
    padding: 8px 12px;
    gap: 8px;
    flex-shrink: 0;
    z-index: 20;
  }

  @media (max-width: 1200px) {
    .pos-container { grid-template-columns: 1fr 380px; }
  }

  @media (max-width: 960px) {
    .pos-topbar { padding: 0 12px; height: auto; min-height: 58px; flex-wrap: wrap; padding-top: 8px; padding-bottom: 8px; }
    .pos-clock, .hide-tablet { display: none !important; }
    .pos-container { grid-template-columns: 1fr; }
    .pos-catalog, .pos-ticket { border-right: none; }
    body.pos-mobile-view-catalog .pos-ticket { display: none; }
    body.pos-mobile-view-cart .pos-catalog { display: none; }
    .pos-mobile-tabs { display: grid; grid-template-columns: 1fr 1fr; }
    .pos-search-bar { flex-direction: column; align-items: stretch; }
    .pos-search-bar > div { width: 100% !important; }
  }
</style>
</head>
<body class="pos-body pos-mobile-view-catalog">

  <!-- ================= TOPBAR ================= -->
  <header class="pos-topbar">
    <div class="pos-top-group">
      <div class="brand-mark" style="width:36px;height:36px;font-size:16px;" aria-hidden="true">P</div>
      <div>
        <h1 style="font-size: 15px; font-weight: 800; line-height: 1.2; color: var(--text);"><?= h($store_name) ?></h1>
        <p style="font-size: 11.5px; color: var(--text-muted);">ຈຸດຂາຍໜ້າຮ້ານ (Cashier Terminal)</p>
      </div>
      <span class="pos-clock tabular" id="posClock">--:--</span>
    </div>

    <div class="pos-top-group">
      <?php if (!$shift): ?>
      <div class="topbar-pill pill-warning">
        <i data-lucide="alert-circle" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        <span>ຍັງບໍ່ໄດ້ເປີດກະ</span>
        <button type="button" class="btn btn-warning btn-sm" style="height:26px;padding:0 10px;font-size:11.5px;" onclick="openModal('shiftModal')">ເປີດກະ</button>
      </div>
      <?php else: ?>
      <div class="topbar-pill pill-success">
        <span class="pulse-dot" aria-hidden="true"></span>
        <span>ກະ #<?= (int)$shift['id'] ?> (<?= date('H:i', strtotime($shift['opened_at'])) ?>)</span>
        <button type="button" class="btn btn-secondary btn-sm" style="height: 26px; padding: 0 9px; font-size: 11px;" onclick="openCloseShift()">ປິດກະ</button>
      </div>
      <?php endif; ?>

      <button type="button" class="btn btn-secondary btn-sm" onclick="openHeldList()" style="height: 36px; padding: 0 12px; border-radius: var(--radius-full);">
        <i data-lucide="pause-circle" aria-hidden="true" style="width: 15px; height: 15px; color: var(--warning);"></i>
        <span class="hide-tablet">ບິນພັກ (F2)</span>
        <span class="badge tabular" style="background: var(--warning); color: #fff; border-color: var(--warning); padding: 1px 7px; font-size: 11px;" id="heldCountBadge">0</span>
      </button>
    </div>

    <div class="pos-top-group">
      <button type="button" class="btn btn-secondary btn-sm" id="btnViewToggle" title="ສະຫຼັບມຸມມອງຮູບສິນຄ້າ" onclick="toggleCardImages()" style="height: 36px; padding: 0 10px;">
        <i data-lucide="image" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        <span id="viewToggleLabel" class="hide-tablet">ສະແດງຮູບ</span>
      </button>
      <button type="button" class="btn btn-secondary btn-sm active" id="btnAudioToggle" title="ເປີດ/ປິດສຽງບາໂຄດ" aria-label="ເປີດ/ປິດສຽງບາໂຄດ" onclick="toggleAudio()" style="width: 36px; height: 36px; padding: 0;">
        <i data-lucide="volume-2" id="audioIcon" aria-hidden="true" style="width: 16px; height: 16px;"></i>
      </button>
      <button type="button" class="btn btn-secondary btn-sm" id="posThemeBtn" title="ສະຫຼັບ Light / Dark Mode" aria-label="ສະຫຼັບໂໝດແສງ/ມືດ" onclick="togglePosTheme()" style="width: 36px; height: 36px; padding: 0;">
        <i data-lucide="moon" id="posThemeIcon" aria-hidden="true" style="width: 16px; height: 16px;"></i>
      </button>
      <div class="hide-tablet" style="display: flex; align-items: center; gap: 6px; padding: 0 10px; height: 36px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 700;">
        <i data-lucide="user" aria-hidden="true" style="width: 14px; height: 14px; color: var(--text-muted);"></i>
        <span><?= h($u['fullname']) ?></span>
      </div>
      <?php if ($is_manager): ?>
      <a href="dashboard.php" class="btn btn-secondary btn-sm" style="height: 36px;">
        <i data-lucide="layout-dashboard" aria-hidden="true" style="width: 14px; height: 14px;"></i>
        <span>ຫຼັງບ້ານ</span>
      </a>
      <?php else: ?>
      <a href="logout.php" class="btn btn-secondary btn-sm" style="height: 36px;" title="ອອກຈາກລະບົບ" aria-label="ອອກຈາກລະບົບ">
        <i data-lucide="log-out" aria-hidden="true" style="width: 14px; height: 14px;"></i>
      </a>
      <?php endif; ?>
    </div>
  </header>

  <!-- ================= WORKSPACE ================= -->
  <main class="pos-container">

    <!-- LEFT: SEARCH + CATALOG -->
    <section class="pos-catalog" aria-label="ລາຍການສິນຄ້າ">
      <div class="pos-search-bar">
        <div style="position: relative; flex: 1;">
          <i data-lucide="scan-barcode" aria-hidden="true" style="position: absolute; left: 13px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--primary);"></i>
          <input type="text" id="barcodeInput" class="clean-input" placeholder="ສະແກນບາໂຄດ ຫຼື ພິມລະຫັດ..." autofocus style="padding-left: 42px; padding-right: 64px;">
          <span class="kbd-hint">Enter</span>
        </div>
        <div style="position: relative; width: 270px;">
          <i data-lucide="search" aria-hidden="true" style="position: absolute; left: 13px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted);"></i>
          <input type="text" id="searchInput" class="clean-input" placeholder="ຄົ້ນຫາຊື່ສິນຄ້າ..." style="padding-left: 38px; padding-right: 38px;">
          <span class="kbd-hint">/</span>
        </div>
      </div>

      <!-- Categories Pills -->
      <div class="cat-bar" id="catBar">
        <button type="button" class="cat-chip active" onclick="filterCategory('', this)">
          <i data-lucide="layout-grid" aria-hidden="true" style="width: 14px; height: 14px;"></i>
          <span>ທັງໝົດ</span>
          <span class="cat-count"><?= count($products) ?></span>
        </button>
        <?php foreach ($categories as $c):
          $cnt = $cat_counts[(string)$c['id']] ?? 0;
        ?>
        <button type="button" class="cat-chip" onclick="filterCategory('<?= $c['id'] ?>', this)">
          <i data-lucide="tag" aria-hidden="true" style="width: 14px; height: 14px;"></i>
          <span><?= h($c['name']) ?></span>
          <span class="cat-count"><?= $cnt ?></span>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Product Grid -->
      <div class="prod-scroll">
        <div class="prod-grid" id="productGrid"></div>
      </div>
    </section>

    <!-- RIGHT: CART TICKET -->
    <aside class="pos-ticket" aria-label="ກະຕ່າສິນຄ້າ">
      <!-- Member Search Bar -->
      <div class="ticket-head">
        <i data-lucide="user-check" aria-hidden="true" style="width: 16px; height: 16px; color: var(--primary); flex-shrink: 0;"></i>
        <input type="text" id="customerSearch" placeholder="ຄົ້ນຫາສະມາຊິກ (ຊື່ / ເບີໂທ / ລະຫັດ)..." style="flex: 1; height: 36px; padding: 0 12px; font-size: 13px;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="clearCustomer()" style="height: 36px; width: 36px; padding: 0;" title="ລ້າງສະມາຊິກ" aria-label="ລ້າງສະມາຊິກ">
          <i data-lucide="x" aria-hidden="true" style="width: 14px; height: 14px;"></i>
        </button>
      </div>
      <div id="customerResults" style="position: relative;"></div>
      <div id="customerInfo" style="padding: 8px 16px; font-size: 12.5px; font-weight: 700; color: var(--success-dark); background: var(--success-50); border-bottom: 1px solid var(--success-border); display: none; align-items: center; gap: 8px;"></div>

      <!-- Cart Item List -->
      <div class="cart-list" id="cartItems"></div>

      <!-- Cart Bottom Calculations -->
      <div class="ticket-footer">
        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">
          <span>ຍອດລວມ (<span id="sumQty" class="tabular">0</span> ລາຍການ)</span>
          <span id="sumSubtotal" class="tabular" style="font-weight: 700; color: var(--text);">0 ₭</span>
        </div>

        <!-- Discount row -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; font-size: 13px; gap: 8px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="color:var(--text-muted);">ສ່ວນຫຼຸດ:</span>
            <select id="discountType" onchange="renderCart()" style="height: 32px; width: 92px; padding: 0 6px; font-size: 12px;">
              <option value="amount">₭ (ກີບ)</option>
              <option value="percent">% (ເປີເຊັນ)</option>
            </select>
            <input type="number" id="discountValue" value="0" min="0" oninput="renderCart()" style="height: 32px; width: 78px; padding: 0 8px; font-size: 12.5px; text-align: right;" class="tabular">
          </div>
          <span id="sumDiscount" class="tabular" style="color: var(--danger); font-weight: 700;">-0 ₭</span>
        </div>

        <!-- Points Redemption row -->
        <div id="pointRow" style="display: none; align-items: center; justify-content: space-between; margin-bottom: 8px; font-size: 13px; gap: 8px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="color:var(--text-muted);">ແລກຄະແນນ:</span>
            <input type="number" id="pointsUsed" value="0" min="0" oninput="renderCart()" style="height: 32px; width: 72px; padding: 0 8px; font-size: 12.5px; text-align: right;" class="tabular">
            <small style="color: var(--text-muted); font-size: 11px;">(1 pt = <?= number_format($point_value) ?> ₭)</small>
          </div>
          <span id="sumPointsDiscount" class="tabular" style="color: var(--success-dark); font-weight: 700;">-0 ₭</span>
        </div>

        <?php if ($tax_rate > 0): ?>
        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">
          <span>ພາສີ VAT (<?= $tax_rate ?>%)</span>
          <span id="sumTax" class="tabular">0 ₭</span>
        </div>
        <?php endif; ?>

        <!-- Grand Total -->
        <div class="ticket-total-box">
          <span style="font-size: 14.5px; font-weight: 700; color:var(--text);">ຍອດຕ້ອງຊຳລະ</span>
          <span id="sumTotal" class="tabular" style="font-size: 26px; font-weight: 800; color: var(--success);">0 ₭</span>
        </div>

        <!-- Actions -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px;">
          <button type="button" class="btn btn-secondary" onclick="holdSale()">
            <i data-lucide="pause" aria-hidden="true"></i> <span>ພັກບິນ (F2)</span>
          </button>
          <button type="button" class="btn btn-secondary" style="color: var(--danger);" onclick="clearCart()">
            <i data-lucide="trash-2" aria-hidden="true"></i> <span>ລຶບບິນ</span>
          </button>
          <button type="button" class="btn btn-success btn-lg" id="btnPay" onclick="openPayModal()" style="grid-column: span 2; height: 52px; font-size: 16px; justify-content: space-between; padding: 0 20px;">
            <span style="display: flex; align-items: center; gap: 8px;">
              <i data-lucide="check-circle-2" aria-hidden="true" style="width: 19px; height: 19px;"></i>
              <span>ຊຳລະເງິນ (F12)</span>
            </span>
            <span id="payBtnAmount" class="tabular">0 ₭</span>
          </button>
        </div>
      </div>
    </aside>
  </main>

  <!-- Mobile/Tablet Bottom Pane Switcher -->
  <div class="pos-mobile-tabs" aria-label="ສະຫຼັບໜ້າຈໍ">
    <button type="button" class="btn btn-primary" id="mobTabCatalog" onclick="setMobilePane('catalog')">
      <i data-lucide="layout-grid" aria-hidden="true"></i>
      <span>ສິນຄ້າ</span>
    </button>
    <button type="button" class="btn btn-secondary" id="mobTabCart" onclick="setMobilePane('cart')">
      <i data-lucide="shopping-cart" aria-hidden="true"></i>
      <span>ກະຕ່າ (<span id="mobCartCount" class="tabular">0</span>)</span>
    </button>
  </div>

  <!-- ================= PAYMENT MODAL ================= -->
  <div class="modal-overlay" id="payModal" role="dialog" aria-modal="true" aria-labelledby="payModalHeading">
    <div class="modal-window" style="width: 520px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 id="payModalHeading" style="font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="wallet" aria-hidden="true" style="width: 18px; height: 18px; color: var(--success);"></i>
          <span>ຊຳລະເງິນ — <span id="payTotalLabel" class="tabular"></span></span>
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('payModal')" style="width: 34px; height: 34px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        </button>
      </div>

      <div style="padding: 20px;">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;">
          <button type="button" class="btn btn-secondary active" id="tabCash" onclick="selectPayMethod('cash')" style="height: 48px; font-size: 13.5px;">
            <i data-lucide="banknote" aria-hidden="true"></i> <span>ເງິນສົດ</span>
          </button>
          <button type="button" class="btn btn-secondary" id="tabQR" onclick="selectPayMethod('qr')" style="height: 48px; font-size: 13.5px;">
            <i data-lucide="qr-code" aria-hidden="true"></i> <span>BCEL / QR</span>
          </button>
          <button type="button" class="btn btn-secondary" id="tabCard" onclick="selectPayMethod('card')" style="height: 48px; font-size: 13.5px;">
            <i data-lucide="credit-card" aria-hidden="true"></i> <span>ບັດ</span>
          </button>
        </div>

        <!-- Cash Panel -->
        <div id="cashPanel">
          <label for="paidAmount" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">ຮັບເງິນມາ (₭):</label>
          <input type="number" id="paidAmount" oninput="calcChange()" onkeydown="if(event.key==='Enter'){event.preventDefault();confirmPay();}" class="cash-input tabular" placeholder="0" style="width: 100%; height: 52px; font-size: 24px; font-weight: 800; text-align: right; border: 2px solid var(--border-strong); padding: 0 14px;">

          <div class="quick-cash-row">
            <button type="button" class="quick-cash-btn" onclick="setExactPaid()">ພໍດີບິນ</button>
            <button type="button" class="quick-cash-btn" onclick="addPaid(20000)">+20,000</button>
            <button type="button" class="quick-cash-btn" onclick="addPaid(50000)">+50,000</button>
            <button type="button" class="quick-cash-btn" onclick="addPaid(100000)">+100,000</button>
          </div>

          <div class="change-display" id="changeDisplay">
            <span style="font-weight: 700;" id="changeLabel">ເງິນທອນ:</span>
            <span class="tabular" style="font-size: 24px; font-weight: 800;" id="changeAmount">0 ₭</span>
          </div>
        </div>

        <!-- QR Panel (with Offline Local SVG Fallback) -->
        <div id="qrPanel" style="display: none; text-align: center; padding: 10px 0;">
          <div style="width: 196px; height: 196px; border: 2px dashed var(--border-strong); border-radius: var(--radius); margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; background: #fff; padding: 10px; position:relative;">
            <img id="qrImg" alt="QR Code" style="width: 100%; height: 100%; object-fit: contain;" onerror="showOfflineQrFallback()">
            <div id="qrOfflineFallback" style="display:none; flex-direction:column; align-items:center; justify-content:center; color:#0f172a; gap:6px; width:100%; height:100%;">
              <svg viewBox="0 0 100 100" width="128" height="128" aria-hidden="true">
                <rect x="4" y="4" width="28" height="28" fill="none" stroke="#0f172a" stroke-width="6"/>
                <rect x="13" y="13" width="10" height="10" fill="#0f172a"/>
                <rect x="68" y="4" width="28" height="28" fill="none" stroke="#0f172a" stroke-width="6"/>
                <rect x="77" y="13" width="10" height="10" fill="#0f172a"/>
                <rect x="4" y="68" width="28" height="28" fill="none" stroke="#0f172a" stroke-width="6"/>
                <rect x="13" y="77" width="10" height="10" fill="#0f172a"/>
                <rect x="42" y="12" width="8" height="8" fill="#0f172a"/>
                <rect x="42" y="28" width="16" height="8" fill="#0f172a"/>
                <rect x="12" y="42" width="16" height="8" fill="#0f172a"/>
                <rect x="38" y="42" width="24" height="24" fill="#2563eb"/>
                <rect x="70" y="44" width="18" height="8" fill="#0f172a"/>
                <rect x="44" y="72" width="14" height="10" fill="#0f172a"/>
                <rect x="68" y="68" width="24" height="24" fill="none" stroke="#0f172a" stroke-width="5"/>
              </svg>
              <span style="font-size:11px;font-weight:700;color:#475569;">LAO QR DEMO</span>
            </div>
          </div>
          <p style="font-size: 13px; color: var(--text-muted); font-weight: 600;">ໃຫ້ລູກຄ້າສະແກນ QR ຜ່ານ BCEL One ຫຼື LAO QR</p>
        </div>

        <!-- Card Panel -->
        <div id="cardPanel" style="display: none; text-align: center; padding: 30px 10px; color: var(--text-muted);">
          <i data-lucide="credit-card" aria-hidden="true" style="width: 48px; height: 48px; margin-bottom: 8px; color: var(--primary);"></i>
          <p style="font-size: 14px; font-weight: 600; color: var(--text);">ຮູດບັດຜ່ານເຄື່ອງ EDC ແລ້ວກົດຢືນຢັນການຊຳລະ</p>
        </div>

        <label style="display: flex; gap: 8px; align-items: center; margin-top: 14px; font-size: 13px; font-weight: 600; cursor: pointer;">
          <input type="checkbox" id="taxInvoice" style="width: 17px; height: 17px;"> ອອກໃບກຳກັບພາສີ (Tax Invoice)
        </label>
      </div>

      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="flex: 0.35;" onclick="closeModal('payModal')">ຍົກເລີກ</button>
        <button type="button" class="btn btn-success btn-lg" id="confirmPayBtn" onclick="confirmPay()" style="flex: 1;">
          <i data-lucide="printer" aria-hidden="true" style="width: 18px; height: 18px;"></i>
          <span>ຢືນຢັນ &amp; ພິມໃບບິນ</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ================= HELD ORDERS MODAL ================= -->
  <div class="modal-overlay" id="heldModal" role="dialog" aria-modal="true" aria-labelledby="heldModalTitle">
    <div class="modal-window" style="width: 460px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 id="heldModalTitle" style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="pause-circle" aria-hidden="true" style="width: 18px; height: 18px; color: var(--warning);"></i>
          <span>ບິນທີ່ພັກໄວ້ (Held Bills)</span>
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('heldModal')" style="width: 34px; height: 34px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div id="heldList" style="padding: 14px 20px; max-height: 60dvh; overflow-y: auto;"></div>
    </div>
  </div>

  <!-- ================= HOLD NOTE MODAL (replaces prompt) ================= -->
  <div class="modal-overlay" id="holdNoteModal" role="dialog" aria-modal="true" aria-labelledby="holdNoteTitle">
    <div class="modal-window" style="width: 420px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 id="holdNoteTitle" style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="pause" aria-hidden="true" style="width: 18px; height: 18px; color: var(--warning);"></i>
          <span>ພັກບິນຊົ່ວຄາວ</span>
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('holdNoteModal')" style="width: 34px; height: 34px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div style="padding: 20px;">
        <label for="holdNoteInput" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 8px;">ໝາຍເຫດບິນພັກ (ເຊັ່ນ: ຊື່ລູກຄ້າ ຫຼື ເລກໂຕະ):</label>
        <input type="text" id="holdNoteInput" class="clean-input" placeholder="ພິມໝາຍເຫດ (ຫຼື ປະວ່າງ)...">
        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:10px;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('holdNoteInput').value='ລູກຄ້າລໍຖ້າ'">ລູກຄ້າລໍຖ້າ</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('holdNoteInput').value='ກຳລັງເລືອກເພີ່ມ'">ກຳລັງເລືອກເພີ່ມ</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('holdNoteInput').value='ໂຕະ 1'">ໂຕະ 1</button>
        </div>
      </div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="flex: 0.4;" onclick="closeModal('holdNoteModal')">ຍົກເລີກ</button>
        <button type="button" class="btn btn-warning" style="flex: 1;" onclick="submitHoldSale()">
          <i data-lucide="check" aria-hidden="true"></i>
          <span>ຢືນຢັນພັກບິນ</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ================= CONFIRM ACTION MODAL (replaces confirm) ================= -->
  <div class="modal-overlay" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="modal-window" style="width: 400px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 id="confirmModalTitle" style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="alert-triangle" aria-hidden="true" style="width: 18px; height: 18px; color: var(--danger);"></i>
          <span>ຢືນຢັນການດຳເນີນການ</span>
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="resolveConfirm(false)" style="width: 34px; height: 34px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div style="padding: 20px;">
        <p id="confirmModalMsg" style="font-size: 14px; color: var(--text-muted); line-height: 1.6;"></p>
      </div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="flex: 0.45;" onclick="resolveConfirm(false)">ຍົກເລີກ</button>
        <button type="button" class="btn btn-danger" style="flex: 1;" id="confirmModalOkBtn" onclick="resolveConfirm(true)">ຢືນຢັນ</button>
      </div>
    </div>
  </div>

  <!-- ================= NUMPAD MODAL ================= -->
  <div class="modal-overlay" id="numpadModal" role="dialog" aria-modal="true" aria-labelledby="numpadTitle">
    <div class="modal-window" style="width: 330px;">
      <div style="padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 15px; font-weight: 800;" id="numpadTitle">ໃສ່ຈຳນວນ</h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('numpadModal')" style="width: 30px; height: 30px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 14px; height: 14px;"></i>
        </button>
      </div>
      <div style="padding: 16px;">
        <input type="number" id="numpadInput" onkeydown="if(event.key==='Enter'){event.preventDefault();confirmNum();}" class="clean-input tabular" style="height: 50px; text-align: center; font-size: 26px; font-weight: 800;" value="1">
        <div class="numpad-grid">
          <button type="button" class="numpad-btn" onclick="pressNum(1)">1</button>
          <button type="button" class="numpad-btn" onclick="pressNum(2)">2</button>
          <button type="button" class="numpad-btn" onclick="pressNum(3)">3</button>
          <button type="button" class="numpad-btn" onclick="pressNum(4)">4</button>
          <button type="button" class="numpad-btn" onclick="pressNum(5)">5</button>
          <button type="button" class="numpad-btn" onclick="pressNum(6)">6</button>
          <button type="button" class="numpad-btn" onclick="pressNum(7)">7</button>
          <button type="button" class="numpad-btn" onclick="pressNum(8)">8</button>
          <button type="button" class="numpad-btn" onclick="pressNum(9)">9</button>
          <button type="button" class="numpad-btn" style="color: var(--danger);" onclick="clearNum()">C</button>
          <button type="button" class="numpad-btn" onclick="pressNum(0)">0</button>
          <button type="button" class="numpad-btn" style="color: var(--success);" onclick="confirmNum()">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= SHIFT MODAL ================= -->
  <div class="modal-overlay" id="shiftModal" role="dialog" aria-modal="true" aria-labelledby="shiftModalTitle">
    <div class="modal-window" style="width: 400px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 id="shiftModalTitle" style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="clock" aria-hidden="true" style="width: 18px; height: 18px; color: var(--success);"></i>
          <span>ເປີດກະເຮັດວຽກ</span>
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('shiftModal')" style="width: 34px; height: 34px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div style="padding: 20px;">
        <label for="openingCash" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">ເງິນທອນເລີ່ມຕົ້ນໃນລີ້ນຊັກ (₭):</label>
        <input type="number" id="openingCash" value="0" min="0" class="clean-input tabular" style="height: 48px; font-size: 20px; font-weight: 700;">
      </div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="flex: 0.4;" onclick="closeModal('shiftModal')">ຍົກເລີກ</button>
        <button type="button" class="btn btn-success" style="flex: 1;" onclick="openShift()">ຢືນຢັນເປີດກະ</button>
      </div>
    </div>
  </div>

  <!-- ================= CLOSE SHIFT MODAL ================= -->
  <div class="modal-overlay" id="closeShiftModal" role="dialog" aria-modal="true" aria-labelledby="closeShiftModalTitle">
    <div class="modal-window" style="width: 420px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 id="closeShiftModalTitle" style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="lock" aria-hidden="true" style="width: 18px; height: 18px; color: var(--danger);"></i>
          <span>ປິດກະ — ນັບເງິນລີ້ນຊັກ</span>
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('closeShiftModal')" style="width: 34px; height: 34px; padding: 0;" aria-label="ປິດ">
          <i data-lucide="x" aria-hidden="true" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div style="padding: 20px;">
        <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 12px;">ເງິນສົດທີ່ຄວນມີ: <strong id="expectedCashLabel" class="tabular" style="color: var(--text); font-size: 16px;"></strong></p>
        <label for="closingCashInput" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">ເງິນສົດນັບໄດ້ຕົວຈິງ (₭):</label>
        <input type="number" id="closingCashInput" min="0" class="clean-input tabular" style="height: 48px; font-size: 20px; font-weight: 700;">
      </div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="flex: 0.4;" onclick="closeModal('closeShiftModal')">ຍົກເລີກ</button>
        <button type="button" class="btn btn-danger" style="flex: 1;" onclick="confirmCloseShift()">ຢືນຢັນປິດກະ</button>
      </div>
    </div>
  </div>

  <!-- ================= SHIFT CLOSED RESULT MODAL (replaces alert) ================= -->
  <div class="modal-overlay" id="shiftResultModal" role="dialog" aria-modal="true" aria-labelledby="shiftResultTitle">
    <div class="modal-window" style="width: 400px;">
      <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px;">
        <i data-lucide="check-circle-2" aria-hidden="true" style="width: 20px; height: 20px; color: var(--success);"></i>
        <h3 id="shiftResultTitle" style="font-size: 16px; font-weight: 800;">ປິດກະສຳເລັດແລ້ວ</h3>
      </div>
      <div style="padding: 20px; display:flex; flex-direction:column; gap:10px;" id="shiftResultBody"></div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1px solid var(--border);">
        <button type="button" class="btn btn-primary btn-block" onclick="location.reload()">ຕົກລົງ</button>
      </div>
    </div>
  </div>

  <!-- Toast Element -->
  <div id="posToast" class="pos-toast" role="status" aria-live="polite"></div>

  <!-- ================= CLIENT JAVASCRIPT ================= -->
  <script>
    let PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
    const TAX_RATE = <?= json_encode($tax_rate) ?>;
    const POINT_VALUE = <?= json_encode($point_value) ?>;
    const HAS_SHIFT = <?= $shift ? 'true' : 'false' ?>;
    const PAYMENT_QR = <?= json_encode($payment_qr ?: null) ?>;
    const SHIFT_CASH_EXPECTED = <?= (float)$shift_cash_expected ?>;

    let cart = [];
    let customer = null;
    let lastCustomerResults = [];
    let heldResumeId = null;
    let currentCategory = '';
    let payMethod = 'cash';
    let numpadTargetIdx = null;
    let showProductImages = true;
    let toastTimer = null;
    let confirmResolver = null;

    function updateClock() {
      const el = document.getElementById('posClock');
      if (!el) return;
      const now = new Date();
      el.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }
    updateClock();
    setInterval(updateClock, 15000);

    function syncPosThemeIcon(theme) {
      const icon = document.getElementById('posThemeIcon');
      if (!icon) return;
      icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
      if (window.lucide) lucide.createIcons();
    }

    function togglePosTheme() {
      const root = document.documentElement;
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('pos_theme', next); } catch (e) {}
      syncPosThemeIcon(next);
    }

    function setMobilePane(pane) {
      document.body.classList.toggle('pos-mobile-view-catalog', pane === 'catalog');
      document.body.classList.toggle('pos-mobile-view-cart', pane === 'cart');
      document.getElementById('mobTabCatalog').className = pane === 'catalog' ? 'btn btn-primary' : 'btn btn-secondary';
      document.getElementById('mobTabCart').className = pane === 'cart' ? 'btn btn-primary' : 'btn btn-secondary';
    }

    function showToast(msg, type = 'default') {
      const el = document.getElementById('posToast');
      el.className = 'pos-toast show' + (type !== 'default' ? ' toast-' + type : '');
      el.textContent = msg;
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
    }

    function askConfirm(message) {
      return new Promise((resolve) => {
        confirmResolver = resolve;
        document.getElementById('confirmModalMsg').textContent = message;
        openModal('confirmModal');
        setTimeout(() => document.getElementById('confirmModalOkBtn').focus(), 40);
      });
    }

    function resolveConfirm(accepted) {
      closeModal('confirmModal');
      if (confirmResolver) {
        const fn = confirmResolver;
        confirmResolver = null;
        fn(accepted);
      }
    }

    function toggleCardImages() {
      showProductImages = !showProductImages;
      document.getElementById('btnViewToggle').classList.toggle('active', showProductImages);
      document.getElementById('viewToggleLabel').textContent = showProductImages ? 'ສະແດງຮູບ' : 'ໂໝດກະທັດຮັດ';
      renderProducts();
    }

    // Web Audio Synthesizer
    let audioEnabled = true;
    let audioCtx = null;
    function getAudioContext() {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      return audioCtx;
    }
    function toggleAudio() {
      audioEnabled = !audioEnabled;
      document.getElementById('btnAudioToggle').classList.toggle('active', audioEnabled);
      document.getElementById('audioIcon').setAttribute('data-lucide', audioEnabled ? 'volume-2' : 'volume-x');
      if (window.lucide) lucide.createIcons();
    }
    function playBeep() {
      if (!audioEnabled) return;
      try {
        const ctx = getAudioContext();
        if (ctx.state === 'suspended') ctx.resume();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1760, ctx.currentTime);
        gain.gain.setValueAtTime(0.18, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.05);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.05);
      } catch (e) {}
    }
    function playChime() {
      if (!audioEnabled) return;
      try {
        const ctx = getAudioContext();
        if (ctx.state === 'suspended') ctx.resume();
        [1046.5, 1318.5].forEach((freq, idx) => {
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          const start = ctx.currentTime + (idx * 0.08);
          osc.type = 'triangle';
          osc.frequency.setValueAtTime(freq, start);
          gain.gain.setValueAtTime(0.2, start);
          gain.gain.exponentialRampToValueAtTime(0.001, start + 0.22);
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(start);
          osc.stop(start + 0.22);
        });
      } catch (e) {}
    }

    function fmt(n) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); }
    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    function filterCategory(catId, btnEl) {
      currentCategory = catId;
      document.querySelectorAll('.cat-chip').forEach(b => b.classList.remove('active'));
      if (btnEl) btnEl.classList.add('active');
      renderProducts();
    }

    function renderProducts() {
      const q = document.getElementById('searchInput').value.toLowerCase().trim();
      const list = PRODUCTS.filter(p => {
        const matchCat = !currentCategory || p.category_id == currentCategory;
        const matchQ = !q || p.name.toLowerCase().includes(q) || (p.barcode && p.barcode.toLowerCase().includes(q));
        return matchCat && matchQ;
      });

      const grid = document.getElementById('productGrid');
      if (!list.length) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 48px 20px;"><i data-lucide="package-search" aria-hidden="true" style="width: 38px; height: 38px; margin-bottom: 8px; opacity: 0.5;"></i><p style="font-weight:600;">ບໍ່ພົບສິນຄ້າທີ່ຄົ້ນຫາ</p></div>';
        if (window.lucide) lucide.createIcons();
        return;
      }

      grid.innerHTML = list.map(p => {
        const out = Number(p.stock_qty) <= 0;
        const low = Number(p.stock_qty) > 0 && Number(p.stock_qty) <= Number(p.min_stock);
        const cartItem = cart.find(i => i.id == p.id);
        const imgBlock = showProductImages
          ? (p.image
              ? `<div class="prod-img-wrap"><img src="uploads/${esc(p.image)}" alt="${esc(p.name)}" loading="lazy"></div>`
              : `<div class="prod-img-wrap"><i data-lucide="package" aria-hidden="true" style="width:28px;height:28px;opacity:0.45;"></i></div>`)
          : '';
        return `
          <div class="prod-card ${out ? 'out' : ''} ${cartItem ? 'in-cart' : ''}" onclick="addToCartById(${p.id})" role="button" tabindex="0">
            ${cartItem ? `<span class="prod-cart-qty tabular">${cartItem.qty}</span>` : ''}
            <div>
              ${imgBlock}
              <div style="display: flex; justify-content: space-between; align-items: center; gap: 4px;">
                <span style="font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(p.category_name || 'ທົ່ວໄປ')}</span>
                <span style="font-family: var(--font-mono); font-size: 10.5px; color: var(--text-subtle);">${esc(p.barcode || '')}</span>
              </div>
              <div style="font-size: 13.5px; font-weight: 700; color: var(--text); line-height: 1.35; margin-top: 4px;">${esc(p.name)}</div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 12px; gap: 6px;">
              <span class="tabular" style="font-size: 16px; font-weight: 800; color: var(--text);">${fmt(p.sell_price)} ₭</span>
              <span class="badge tabular ${out ? 'badge-danger' : (low ? 'badge-warning' : 'badge-success')}" style="font-size: 11px;">
                ${out ? 'ໝົດສະຕ໋ອກ' : 'ເຫຼືອ ' + p.stock_qty}
              </span>
            </div>
          </div>
        `;
      }).join('');
      if (window.lucide) lucide.createIcons();
    }

    function addToCartById(id) {
      const p = PRODUCTS.find(x => x.id == id);
      if (p) addToCart(p, 1);
    }

    function addToCart(p, qty = 1) {
      const maxStock = parseInt(p.stock_qty) || 0;
      if (maxStock <= 0) {
        showToast('ສິນຄ້າໝົດສະຕ໋ອກ: ' + p.name, 'danger');
        return;
      }
      const idx = cart.findIndex(i => i.id == p.id);
      if (idx >= 0) {
        if (cart[idx].qty + qty > maxStock) {
          cart[idx].qty = maxStock;
          showToast('ຈຳນວນສູງສຸດຕາມສະຕ໋ອກ (' + maxStock + ')', 'danger');
          renderCart();
          return;
        }
        cart[idx].qty += qty;
        cart[idx]._flash = true;
      } else {
        cart.push({
          id: p.id,
          name: p.name,
          barcode: p.barcode || '',
          price: parseFloat(p.sell_price),
          qty: Math.min(qty, maxStock),
          stock: maxStock,
          _flash: true
        });
      }
      playBeep();
      renderCart();
    }

    function changeQty(idx, delta) {
      if (!cart[idx]) return;
      const nextQty = cart[idx].qty + delta;
      if (nextQty > cart[idx].stock) {
        showToast('ສະຕ໋ອກເຫຼືອພຽງ ' + cart[idx].stock + ' ໜ່ວຍ', 'danger');
        return;
      }
      cart[idx].qty = nextQty;
      if (cart[idx].qty <= 0) cart.splice(idx, 1);
      renderCart();
    }

    function removeItem(idx) {
      cart.splice(idx, 1);
      renderCart();
    }

    async function clearCart() {
      if (!cart.length) return;
      if (await askConfirm('ລຶບລາຍການສິນຄ້າທັງໝົດໃນບິນແທ້ບໍ່?')) {
        cart = [];
        heldResumeId = null;
        renderCart();
      }
    }

    // Touch Numpad
    function openNumpad(idx) {
      numpadTargetIdx = idx;
      document.getElementById('numpadTitle').innerText = cart[idx].name + ' (ສູງສຸດ ' + cart[idx].stock + ')';
      document.getElementById('numpadInput').value = cart[idx].qty;
      openModal('numpadModal');
    }
    function pressNum(n) {
      const el = document.getElementById('numpadInput');
      el.value = el.value === '0' ? String(n) : el.value + String(n);
    }
    function clearNum() { document.getElementById('numpadInput').value = '0'; }
    function confirmNum() {
      let val = parseInt(document.getElementById('numpadInput').value) || 1;
      const item = cart[numpadTargetIdx];
      if (item) {
        if (val > item.stock) {
          val = item.stock;
          showToast('ປັບເປັນຈຳນວນສູງສຸດໃນສະຕ໋ອກ (' + item.stock + ')', 'danger');
        }
        item.qty = Math.max(1, val);
        renderCart();
      }
      closeModal('numpadModal');
    }

    function calcTotals() {
      const subtotal = cart.reduce((s, i) => s + (i.price * i.qty), 0);
      const discType = document.getElementById('discountType').value;
      const discVal = parseFloat(document.getElementById('discountValue').value) || 0;
      let discAmount = discType === 'percent' ? (subtotal * Math.min(discVal, 100) / 100) : discVal;
      discAmount = Math.min(subtotal, Math.max(0, discAmount));

      let ptsUsed = parseInt(document.getElementById('pointsUsed').value) || 0;
      let maxPts = (customer && POINT_VALUE > 0)
        ? Math.min(parseInt(customer.points) || 0, Math.floor(Math.max(0, subtotal - discAmount) / POINT_VALUE))
        : 0;
      if (ptsUsed > maxPts) { ptsUsed = maxPts; document.getElementById('pointsUsed').value = ptsUsed; }
      const ptsDiscount = ptsUsed * POINT_VALUE;

      const afterDisc = Math.max(0, subtotal - discAmount - ptsDiscount);
      const tax = (afterDisc * TAX_RATE / 100);
      const total = Math.round(afterDisc + tax);
      return { subtotal, discAmount, ptsDiscount, ptsUsed, tax, total };
    }

    function renderCart() {
      const t = calcTotals();
      const wrap = document.getElementById('cartItems');
      const totalQty = cart.reduce((s, i) => s + i.qty, 0);
      const mobCnt = document.getElementById('mobCartCount');
      if (mobCnt) mobCnt.textContent = totalQty;

      if (!cart.length) {
        wrap.innerHTML = '<div style="text-align: center; color: var(--text-subtle); padding: 54px 16px;"><i data-lucide="shopping-cart" aria-hidden="true" style="width: 44px; height: 44px; stroke-width: 1.5; margin-bottom: 10px;"></i><p style="font-weight: 700; color: var(--text-muted);">ຍັງບໍ່ມີສິນຄ້າໃນບິນ</p><small>ສະແກນບາໂຄດ ຫຼື ຄລິກເລືອກສິນຄ້າທາງຊ້າຍ</small></div>';
        document.getElementById('sumQty').textContent = '0';
        document.getElementById('sumSubtotal').textContent = '0 ₭';
        document.getElementById('sumDiscount').textContent = '-0 ₭';
        document.getElementById('sumPointsDiscount').textContent = '-0 ₭';
        if (document.getElementById('sumTax')) document.getElementById('sumTax').textContent = '0 ₭';
        document.getElementById('sumTotal').textContent = '0 ₭';
        document.getElementById('payBtnAmount').textContent = '0 ₭';
        document.getElementById('btnPay').disabled = true;
        renderProducts();
        return;
      }

      wrap.innerHTML = cart.map((i, idx) => `
        <div class="cart-row ${i._flash ? 'flash' : ''}">
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${esc(i.name)}</div>
            <div class="tabular" style="font-size: 12px; color: var(--text-muted);">${fmt(i.price)} ₭ / ໜ່ວຍ</div>
          </div>
          <div class="stepper-group">
            <button type="button" class="stepper-btn" onclick="changeQty(${idx}, -1)" aria-label="ຫຼຸດຈຳນວນ">−</button>
            <span class="stepper-val tabular" onclick="openNumpad(${idx})" title="ຄລິກໃສ່ຈຳນວນ">${i.qty}</span>
            <button type="button" class="stepper-btn" onclick="changeQty(${idx}, 1)" aria-label="ເພີ່ມຈຳນວນ">+</button>
          </div>
          <div class="tabular" style="font-size: 14.5px; font-weight: 800; color: var(--text); min-width: 82px; text-align: right;">${fmt(i.price * i.qty)} ₭</div>
          <button type="button" class="btn btn-secondary btn-sm" style="width: 34px; height: 34px; padding: 0; color: var(--danger);" onclick="removeItem(${idx})" title="ລຶບລາຍການ" aria-label="ລຶບລາຍການ">
            <i data-lucide="x" aria-hidden="true" style="width: 14px; height: 14px;"></i>
          </button>
        </div>
      `).join('');

      cart.forEach(i => i._flash = false);

      document.getElementById('sumQty').textContent = totalQty;
      document.getElementById('sumSubtotal').textContent = fmt(t.subtotal) + ' ₭';
      document.getElementById('sumDiscount').textContent = '-' + fmt(t.discAmount) + ' ₭';
      document.getElementById('sumPointsDiscount').textContent = '-' + fmt(t.ptsDiscount) + ' ₭';

      if (document.getElementById('sumTax')) {
        document.getElementById('sumTax').textContent = fmt(t.tax) + ' ₭';
      }

      document.getElementById('sumTotal').textContent = fmt(t.total) + ' ₭';
      document.getElementById('payBtnAmount').textContent = fmt(t.total) + ' ₭';
      document.getElementById('btnPay').disabled = !HAS_SHIFT;
      renderProducts();
    }

    document.getElementById('barcodeInput').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const code = this.value.trim();
        if (!code) return;
        const p = PRODUCTS.find(x => x.barcode === code || String(x.id) === code);
        if (p) {
          addToCart(p, 1);
          this.value = '';
        } else {
          showToast('ບໍ່ພົບສິນຄ້າລະຫັດ: ' + code, 'danger');
          this.select();
        }
      }
    });

    document.getElementById('searchInput').addEventListener('input', renderProducts);

    let searchTimer = null;
    document.getElementById('customerSearch').addEventListener('input', function() {
      clearTimeout(searchTimer);
      const q = this.value.trim();
      const resBox = document.getElementById('customerResults');
      if (!q) { resBox.innerHTML = ''; return; }
      searchTimer = setTimeout(async () => {
        const res = await api('search_customers', { q });
        lastCustomerResults = res.customers || [];
        if (!lastCustomerResults.length) {
          resBox.innerHTML = '<div class="customer-dropdown" style="padding: 10px 14px; font-size: 13px; color: var(--text-muted);">ບໍ່ພົບສະມາຊິກ</div>';
          return;
        }
        resBox.innerHTML = '<div class="customer-dropdown">' +
          lastCustomerResults.map((c, idx) => `
            <div class="customer-option" onclick="selectCustomerByIdx(${idx})">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong style="color:var(--text);">${esc(c.name)}</strong>
                <span class="badge badge-info tabular">${esc(c.code)}</span>
              </div>
              <small style="color: var(--text-muted);">ໂທ: ${esc(c.phone || '-')} • <strong style="color: var(--success-dark);">ຄະແນນ: ${fmt(c.points)}</strong></small>
            </div>
          `).join('') + '</div>';
      }, 220);
    });

    function selectCustomerByIdx(idx) {
      if (lastCustomerResults[idx]) selectCustomer(lastCustomerResults[idx]);
    }

    function selectCustomer(c) {
      customer = c;
      document.getElementById('customerResults').innerHTML = '';
      document.getElementById('customerSearch').value = c.name + ' (' + c.code + ')';
      const info = document.getElementById('customerInfo');
      info.style.display = 'flex';
      info.innerHTML = `<i data-lucide="award" aria-hidden="true" style="width:15px;height:15px;flex-shrink:0;"></i> <span>${esc(c.name)} | ຄະແນນສະສົມ: <strong class="tabular">${fmt(c.points)}</strong> (ແລກໄດ້ສູງສຸດ <span class="tabular">${fmt(c.points * POINT_VALUE)} ₭</span>)</span>`;
      document.getElementById('pointRow').style.display = 'flex';
      renderCart();
    }

    function clearCustomer() {
      customer = null;
      document.getElementById('customerSearch').value = '';
      document.getElementById('customerResults').innerHTML = '';
      document.getElementById('customerInfo').style.display = 'none';
      document.getElementById('pointRow').style.display = 'none';
      document.getElementById('pointsUsed').value = 0;
      renderCart();
    }

    function showOfflineQrFallback() {
      const img = document.getElementById('qrImg');
      const fb = document.getElementById('qrOfflineFallback');
      if (img) img.style.display = 'none';
      if (fb) fb.style.display = 'flex';
    }

    function selectPayMethod(method) {
      payMethod = method;
      document.getElementById('tabCash').classList.toggle('active', method === 'cash');
      document.getElementById('tabQR').classList.toggle('active', method === 'qr');
      document.getElementById('tabCard').classList.toggle('active', method === 'card');

      document.getElementById('cashPanel').style.display = method === 'cash' ? 'block' : 'none';
      document.getElementById('qrPanel').style.display = method === 'qr' ? 'block' : 'none';
      document.getElementById('cardPanel').style.display = method === 'card' ? 'block' : 'none';

      if (method === 'qr') {
        const t = calcTotals();
        const img = document.getElementById('qrImg');
        const fb = document.getElementById('qrOfflineFallback');
        if (PAYMENT_QR) {
          fb.style.display = 'none';
          img.style.display = 'block';
          img.src = 'uploads/' + PAYMENT_QR;
        } else {
          fb.style.display = 'none';
          img.style.display = 'block';
          img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=LAOQR-PAY-' + t.total;
        }
      }
    }

    function openPayModal() {
      if (!cart.length) return;
      if (!HAS_SHIFT) {
        showToast('ກະລຸນາເປີດກະກ່ອນຂາຍສິນຄ້າ', 'danger');
        openModal('shiftModal');
        return;
      }
      const t = calcTotals();
      document.getElementById('payTotalLabel').textContent = fmt(t.total) + ' ₭';
      document.getElementById('paidAmount').value = t.total;
      selectPayMethod('cash');
      calcChange();
      openModal('payModal');
      setTimeout(() => {
        const inp = document.getElementById('paidAmount');
        inp.focus();
        inp.select();
      }, 120);
    }

    function setExactPaid() {
      const t = calcTotals();
      document.getElementById('paidAmount').value = t.total;
      calcChange();
    }

    function addPaid(val) {
      const cur = parseFloat(document.getElementById('paidAmount').value) || 0;
      document.getElementById('paidAmount').value = cur + val;
      calcChange();
    }

    function calcChange() {
      const t = calcTotals();
      const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
      const diff = paid - t.total;
      const disp = document.getElementById('changeDisplay');
      const lbl = document.getElementById('changeLabel');
      const amt = document.getElementById('changeAmount');

      if (paid === 0) {
        disp.className = 'change-display';
        lbl.textContent = 'ເງິນທອນ:';
        amt.textContent = '0 ₭';
      } else if (diff < 0) {
        disp.className = 'change-display insufficient';
        lbl.textContent = 'ຍັງຂາດອີກ:';
        amt.textContent = fmt(Math.abs(diff)) + ' ₭';
      } else {
        disp.className = 'change-display';
        lbl.textContent = 'ເງິນທອນ:';
        amt.textContent = fmt(diff) + ' ₭';
      }
    }

    async function confirmPay() {
      const t = calcTotals();
      let paid = t.total;
      if (payMethod === 'cash') {
        paid = parseFloat(document.getElementById('paidAmount').value) || 0;
        if (paid < t.total) {
          showToast('ເງິນທີ່ຮັບມາບໍ່ພຽງພໍ', 'danger');
          return;
        }
      }

      const btn = document.getElementById('confirmPayBtn');
      btn.disabled = true;
      try {
        const res = await api('checkout', {
          items: cart.map(i => ({ id: i.id, qty: i.qty })),
          discount_type: document.getElementById('discountType').value,
          discount_value: parseFloat(document.getElementById('discountValue').value) || 0,
          customer_id: customer ? customer.id : null,
          points_used: t.ptsUsed,
          payment_method: payMethod,
          paid_amount: paid,
          hold: false,
          resume_id: heldResumeId,
        });

        if (!res.ok) {
          showToast(res.error || 'ເກີດຂໍ້ຜິດພາດ', 'danger');
          return;
        }

        playChime();
        closeModal('payModal');
        showToast('ຊຳລະສຳເລັດ: ' + res.invoice_no, 'success');
        const tax = document.getElementById('taxInvoice').checked ? '&tax=1' : '';
        window.open('receipt.php?id=' + res.sale_id + tax, '_blank', 'width=420,height=650');

        cart = [];
        heldResumeId = null;
        document.getElementById('discountValue').value = 0;
        document.getElementById('pointsUsed').value = 0;
        clearCustomer();
        await reloadProducts();
        updateHeldCounter();
        document.getElementById('barcodeInput').focus();
      } finally {
        btn.disabled = false;
      }
    }

    // Held Bills (F2) using custom #holdNoteModal
    function holdSale() {
      if (!cart.length) {
        openHeldList();
        return;
      }
      if (!HAS_SHIFT) {
        showToast('ກະລຸນາເປີດກະກ່ອນ', 'danger');
        openModal('shiftModal');
        return;
      }
      const inp = document.getElementById('holdNoteInput');
      inp.value = '';
      openModal('holdNoteModal');
      setTimeout(() => inp.focus(), 60);
    }

    async function submitHoldSale() {
      const note = document.getElementById('holdNoteInput').value.trim();
      closeModal('holdNoteModal');
      const res = await api('checkout', {
        items: cart.map(i => ({ id: i.id, qty: i.qty })),
        discount_type: document.getElementById('discountType').value,
        discount_value: parseFloat(document.getElementById('discountValue').value) || 0,
        customer_id: customer ? customer.id : null,
        points_used: 0, payment_method: null, paid_amount: 0,
        hold: true, note, resume_id: heldResumeId,
      });

      if (!res.ok) {
        showToast(res.error || 'ຜິດພາດ', 'danger');
        return;
      }
      showToast('ພັກບິນແລ້ວ: ' + res.invoice_no, 'success');
      cart = []; heldResumeId = null;
      document.getElementById('discountValue').value = 0;
      clearCustomer();
      renderCart();
      updateHeldCounter();
    }

    async function openHeldList() {
      const res = await api('held_list', {});
      const box = document.getElementById('heldList');
      if (res.sales && res.sales.length) {
        box.innerHTML = res.sales.map(s => `
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
            <div>
              <strong style="font-size: 14px; font-family: var(--font-mono); color: var(--text);">${esc(s.invoice_no)}</strong><br>
              <small style="color: var(--text-muted);">${esc(s.note || 'ບໍ່ມີໝາຍເຫດ')} • ${s.created_at}</small>
              <div class="tabular" style="font-weight: 800; color: var(--text); margin-top: 2px;">${fmt(s.total ?? s.subtotal)} ₭</div>
            </div>
            <div style="display:flex;gap:6px;">
              <button type="button" class="btn btn-primary btn-sm" onclick="resumeHeld(${s.id})">
                <i data-lucide="play" aria-hidden="true" style="width:13px;height:13px;"></i> <span>ດຶງບິນ</span>
              </button>
              <button type="button" class="btn btn-danger btn-sm" onclick="deleteHeld(${s.id})" title="ລຶບບິນພັກ" aria-label="ລຶບບິນພັກ">
                <i data-lucide="trash-2" aria-hidden="true" style="width:13px;height:13px;"></i>
              </button>
            </div>
          </div>
        `).join('');
        if (window.lucide) lucide.createIcons();
      } else {
        box.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 28px 0;">ບໍ່ມີບິນທີ່ພັກໄວ້</p>';
      }
      openModal('heldModal');
    }

    async function deleteHeld(id) {
      if (!(await askConfirm('ຕ້ອງການລຶບບິນພັກນີ້ຖາວອນບໍ່?'))) return;
      const res = await api('delete_held', { id });
      if (res.ok) {
        if (heldResumeId == id) heldResumeId = null;
        showToast('ລຶບບິນພັກແລ້ວ', 'success');
        openHeldList();
        updateHeldCounter();
      }
    }

    async function resumeHeld(id) {
      if (cart.length && !(await askConfirm('ບິນປັດຈຸບັນຈະຖືກລ້າງ, ດຳເນີນຕໍ່ບໍ່?'))) return;
      const res = await api('resume_held', { id });
      if (!res.ok) { showToast(res.error, 'danger'); return; }
      cart = res.items.map(i => {
        const p = PRODUCTS.find(x => x.id == i.product_id) || {};
        return {
          id: i.product_id,
          name: i.product_name,
          barcode: p.barcode || '',
          price: parseFloat(i.price),
          qty: parseInt(i.qty),
          stock: parseInt(p.stock_qty ?? 9999)
        };
      });
      heldResumeId = id;
      if (res.customer) selectCustomer(res.customer);
      else clearCustomer();
      document.getElementById('discountType').value = res.sale.discount_type || 'amount';
      document.getElementById('discountValue').value = res.sale.discount_value || 0;
      closeModal('heldModal');
      renderCart();
      updateHeldCounter();
      showToast('ດຶງບິນ ' + res.sale.invoice_no + ' ກັບມາແລ້ວ', 'success');
    }

    async function updateHeldCounter() {
      const res = await api('held_list', {});
      const badge = document.getElementById('heldCountBadge');
      if (badge && res.sales) badge.innerText = res.sales.length;
    }

    async function openShift() {
      const cash = parseFloat(document.getElementById('openingCash').value) || 0;
      const res = await api('open_shift', { opening_cash: cash });
      if (res.ok) location.reload();
      else showToast(res.error, 'danger');
    }

    async function openCloseShift() {
      let expected = SHIFT_CASH_EXPECTED;
      try {
        const st = await api('shift_status', {});
        if (st.ok && typeof st.expected_cash === 'number') {
          expected = st.expected_cash;
        }
      } catch (e) {}
      document.getElementById('expectedCashLabel').textContent = fmt(expected) + ' ₭';
      document.getElementById('closingCashInput').value = expected;
      openModal('closeShiftModal');
    }

    async function confirmCloseShift() {
      const cash = parseFloat(document.getElementById('closingCashInput').value) || 0;
      const res = await api('close_shift', { closing_cash: cash });
      if (res.ok) {
        const diff = res.difference ?? res.diff ?? 0;
        closeModal('closeShiftModal');
        document.getElementById('shiftResultBody').innerHTML = `
          <div style="display:flex;justify-content:space-between;font-size:14px;">
            <span style="color:var(--text-muted);">ເງິນສົດທີ່ຄວນມີ:</span>
            <strong class="tabular">${fmt(res.expected)} ₭</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:14px;">
            <span style="color:var(--text-muted);">ເງິນສົດນັບໄດ້ຕົວຈິງ:</span>
            <strong class="tabular">${fmt(cash)} ₭</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:15px;padding-top:10px;border-top:1px solid var(--border);">
            <span style="font-weight:700;">ສ່ວນຕ່າງ:</span>
            <strong class="tabular" style="color:${diff < 0 ? 'var(--danger)' : 'var(--success)'};">${diff >= 0 ? '+' : ''}${fmt(diff)} ₭</strong>
          </div>
        `;
        openModal('shiftResultModal');
      } else {
        showToast(res.error, 'danger');
      }
    }

    async function api(action, data) {
      const res = await fetch('api.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      return await res.json();
    }

    async function reloadProducts() {
      const res = await fetch('api.php?action=all_products');
      const data = await res.json();
      if (data.products) {
        PRODUCTS = data.products;
        renderProducts();
      }
    }

    // Global Shortcuts (F12, F2, /, Esc)
    window.addEventListener('keydown', (e) => {
      if (e.key === 'F12') {
        e.preventDefault();
        if (cart.length > 0) openPayModal();
      } else if (e.key === 'F2') {
        e.preventDefault();
        holdSale();
      } else if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
        e.preventDefault();
        document.getElementById('searchInput').focus();
      } else if (e.key === 'Escape') {
        if (confirmResolver) {
          resolveConfirm(false);
          return;
        }
        const srModal = document.getElementById('shiftResultModal');
        if (srModal && srModal.classList.contains('open')) {
          location.reload();
          return;
        }
        ['payModal', 'heldModal', 'numpadModal', 'shiftModal', 'closeShiftModal', 'holdNoteModal'].forEach(closeModal);
      }
    });

    document.getElementById('holdNoteInput').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitHoldSale();
      }
    });

    syncPosThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');
    renderProducts();
    renderCart();
    updateHeldCounter();
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
