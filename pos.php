<?php
/**
 * pos.php - ໜ້າຂາຍສິນຄ້າ (Front-End / Cashier Terminal)
 * Modern Clean Retail UI/UX Standard (Anti-AI)
 * - ສະແກນບາໂຄດ (Beep Web Audio API) / ຄົ້ນຫາສິນຄ້າ
 * - Touch Targets & On-screen Numpad
 * - ສ່ວນຫຼຸດ % ຫຼື ຈຳນວນເງິນ
 * - ຈ່າຍເງິນສົດ (Quick Cash) / ບັດ / QR Code
 * - ພັກບິນ (Hold Bills Drawer), ດຶງບິນພັກ, ພິມໃບບິນ Thermal 80mm
 * - ສະມາຊິກ + ໃຊ້ຄະແນນແລກສ່ວນຫຼຸດ
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
?>
<!DOCTYPE html>
<html lang="lo">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ໜ້າຂາຍ POS - <?= h(get_setting('store_name', 'POS')) ?></title>
<link rel="stylesheet" href="style.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
  body { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
  .pos-container { flex: 1; display: grid; grid-template-columns: 1fr 460px; overflow: hidden; }
  .pos-catalog { display: flex; flex-direction: column; height: 100%; border-right: 1.5px solid var(--border); overflow: hidden; }
  .pos-ticket { display: flex; flex-direction: column; height: 100%; background: var(--card); overflow: hidden; }

  .cat-bar { display: flex; gap: 8px; padding: 10px 18px; background: var(--card); border-bottom: 1.5px solid var(--border); overflow-x: auto; scrollbar-width: none; }
  .cat-bar::-webkit-scrollbar { display: none; }
  .cat-chip {
    height: 38px; padding: 0 16px; border-radius: var(--radius-full); font-size: 13px; font-weight: 600;
    white-space: nowrap; border: 1.5px solid var(--border); background: var(--card); color: var(--text-muted);
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s;
  }
  .cat-chip:hover { background: var(--bg-subtle); color: var(--text); }
  .cat-chip.active { background: var(--text); color: #fff; border-color: var(--text); }

  .prod-scroll { flex: 1; overflow-y: auto; padding: 18px; }
  .prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
  .prod-card {
    background: var(--card); border: 1.5px solid var(--border); border-radius: var(--radius);
    padding: 13px; display: flex; flex-direction: column; justify-content: space-between;
    cursor: pointer; transition: transform 0.12s, border-color 0.12s, box-shadow 0.12s; min-height: 128px;
    box-shadow: var(--shadow-sm); position: relative;
  }
  .prod-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
  .prod-card:active { transform: scale(0.98); }
  .prod-card.out { opacity: 0.45; cursor: not-allowed; }
  .prod-img-wrap {
    width: 100%; height: 88px; border-radius: var(--radius-sm); overflow: hidden;
    background: var(--bg-subtle); margin-bottom: 10px; border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center; color: var(--text-subtle);
  }
  .prod-img-wrap img { width: 100%; height: 100%; object-fit: cover; }

  .cart-list { flex: 1; overflow-y: auto; padding: 12px 18px; }
  .cart-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 4px; border-bottom: 1px solid var(--border); gap: 10px; border-radius: var(--radius-sm); }
  .cart-row.flash { animation: flashCart 0.55s ease; }
  @keyframes flashCart { 0% { background: var(--primary-50); } 100% { background: transparent; } }

  .numpad-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
  .numpad-btn {
    height: 50px; background: var(--bg-subtle); border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    font-size: 18px; font-weight: 700; color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center;
  }
  .numpad-btn:hover { background: #e2e8f0; }

  .quick-cash-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 12px 0; }
  .quick-cash-btn {
    height: 44px; background: var(--bg-subtle); border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    font-size: 12.5px; font-weight: 700; color: var(--text); cursor: pointer; font-variant-numeric: tabular-nums;
  }
  .quick-cash-btn:hover { background: #e2e8f0; border-color: var(--border-strong); }

  .change-display {
    padding: 14px 18px; border-radius: var(--radius); background: var(--success-50);
    border: 1.5px solid #a7f3d0; display: flex; align-items: center; justify-content: space-between;
    margin-top: 10px; color: var(--success-dark);
  }
  .change-display.insufficient { background: var(--danger-50); border-color: #fecdd3; color: var(--danger-dark); }

  /* Toast Notification */
  .pos-toast {
    position: fixed; bottom: 24px; left: 24px; z-index: 200;
    background: var(--text); color: #fff; padding: 12px 18px; border-radius: var(--radius);
    box-shadow: var(--shadow-lg); font-size: 13.5px; font-weight: 600;
    display: flex; align-items: center; gap: 10px;
    transform: translateY(20px); opacity: 0; pointer-events: none; transition: all 0.2s ease;
  }
  .pos-toast.show { transform: translateY(0); opacity: 1; }
  .pos-toast.toast-danger { background: var(--danger); }
  .pos-toast.toast-success { background: var(--success); }
</style>
</head>
<body>

  <!-- ================= TOPBAR ================= -->
  <header style="height: 60px; background: var(--card); border-bottom: 1.5px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 18px; flex-shrink: 0; z-index: 20;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <div style="width: 38px; height: 38px; background: var(--text); color: #fff; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 17px;">P</div>
      <div>
        <h1 style="font-size: 15px; font-weight: 800; line-height: 1.2;"><?= h(get_setting('store_name', 'POS Terminal')) ?></h1>
        <p style="font-size: 11.5px; color: var(--text-muted);">ຈຸດຂາຍໜ້າຮ້ານ (Cashier Terminal)</p>
      </div>
    </div>

    <div style="display: flex; align-items: center; gap: 10px;">
      <?php if (!$shift): ?>
      <div style="display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px; background: var(--warning-50); color: var(--warning-dark); border: 1.5px solid #fde68a; border-radius: var(--radius-full); font-size: 12px; font-weight: 700;">
        <i data-lucide="alert-circle" style="width: 15px; height: 15px;"></i>
        <span>ຍັງບໍ່ໄດ້ເປີດກະ</span>
        <button class="btn btn-warning btn-sm" onclick="openModal('shiftModal')">ເປີດກະດຽວນີ້</button>
      </div>
      <?php else: ?>
      <div style="display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px; background: var(--success-50); color: var(--success-dark); border: 1.5px solid #a7f3d0; border-radius: var(--radius-full); font-size: 12px; font-weight: 700;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--success);"></span>
        <span>ກະ #<?= $shift['id'] ?> (<?= date('H:i', strtotime($shift['opened_at'])) ?>)</span>
        <button class="btn btn-secondary btn-sm" style="height: 26px; padding: 0 8px; font-size: 11px;" onclick="openCloseShift()">ປິດກະ</button>
      </div>
      <?php endif; ?>

      <!-- Held orders badge button -->
      <button class="btn btn-secondary btn-sm" onclick="openHeldList()" style="height: 36px; padding: 0 12px; border-radius: var(--radius-full);">
        <i data-lucide="pause-circle" style="width: 15px; height: 15px; color: var(--warning);"></i>
        <span>ບິນພັກ (F2)</span>
        <span class="badge tabular" style="background: var(--warning); color: #fff; border-color: var(--warning); padding: 1px 6px; font-size: 11px;" id="heldCountBadge">0</span>
      </button>
    </div>

    <div style="display: flex; align-items: center; gap: 8px;">
      <button class="btn btn-secondary btn-sm" id="btnViewToggle" title="ສະຫຼັບມຸມມອງຮູບສິນຄ້າ" onclick="toggleCardImages()" style="height: 36px; padding: 0 10px;">
        <i data-lucide="image" style="width: 15px; height: 15px;"></i>
        <span id="viewToggleLabel">ສະແດງຮູບ</span>
      </button>
      <button class="btn btn-secondary btn-sm active" id="btnAudioToggle" title="ເປີດ/ປິດສຽງບາໂຄດ" onclick="toggleAudio()" style="width: 36px; height: 36px; padding: 0;">
        <i data-lucide="volume-2" id="audioIcon" style="width: 16px; height: 16px;"></i>
      </button>
      <div style="display: flex; align-items: center; gap: 6px; padding: 0 10px; height: 36px; background: var(--bg-subtle); border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 600;">
        <i data-lucide="user" style="width: 14px; height: 14px; color: var(--text-muted);"></i>
        <span><?= h($u['fullname']) ?></span>
      </div>
      <?php if ($is_manager): ?>
      <a href="dashboard.php" class="btn btn-secondary btn-sm" style="height: 36px;">
        <i data-lucide="layout-dashboard" style="width: 14px; height: 14px;"></i>
        <span>ຫຼັງບ້ານ</span>
      </a>
      <?php else: ?>
      <a href="logout.php" class="btn btn-secondary btn-sm" style="height: 36px;" title="ອອກຈາກລະບົບ">
        <i data-lucide="log-out" style="width: 14px; height: 14px;"></i>
      </a>
      <?php endif; ?>
    </div>
  </header>

  <!-- ================= WORKSPACE ================= -->
  <main class="pos-container">

    <!-- LEFT: SEARCH + CATALOG -->
    <section class="pos-catalog">
      <div style="padding: 12px 18px; background: var(--card); border-bottom: 1.5px solid var(--border); display: flex; gap: 10px;">
        <div style="position: relative; flex: 1;">
          <i data-lucide="scan-barcode" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--text-muted);"></i>
          <input type="text" id="barcodeInput" class="clean-input" placeholder="ສະແກນບາໂຄດ ຫຼື ພິມລະຫັດ ແລ້ວກົດ Enter..." autofocus style="padding-left: 42px;">
        </div>
        <div style="position: relative; width: 260px;">
          <i data-lucide="search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted);"></i>
          <input type="text" id="searchInput" class="clean-input" placeholder="ຄົ້ນຫາຊື່ສິນຄ້າ..." style="padding-left: 38px;">
        </div>
      </div>

      <!-- Categories Pills -->
      <div class="cat-bar" id="catBar">
        <button class="cat-chip active" onclick="filterCategory('', this)">
          <i data-lucide="layout-grid" style="width: 14px; height: 14px;"></i> ທັງໝົດ
        </button>
        <?php foreach ($categories as $c): ?>
        <button class="cat-chip" onclick="filterCategory('<?= $c['id'] ?>', this)">
          <i data-lucide="tag" style="width: 14px; height: 14px;"></i> <?= h($c['name']) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Product Grid -->
      <div class="prod-scroll">
        <div class="prod-grid" id="productGrid"></div>
      </div>
    </section>

    <!-- RIGHT: CART TICKET -->
    <aside class="pos-ticket">
      <!-- Member Search Bar -->
      <div style="padding: 10px 16px; background: var(--bg-subtle); border-bottom: 1.5px solid var(--border); display: flex; align-items: center; gap: 8px;">
        <i data-lucide="user-check" style="width: 16px; height: 16px; color: var(--text-muted); flex-shrink: 0;"></i>
        <input type="text" id="customerSearch" placeholder="ຄົ້ນຫາສະມາຊິກ (ຊື່ / ເບີໂທ / ລະຫັດ)..." style="flex: 1; height: 36px; padding: 0 10px; font-size: 13px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); background: #fff;">
        <button class="btn btn-secondary btn-sm" onclick="clearCustomer()" style="height: 36px; width: 36px; padding: 0;" title="ລ້າງສະມາຊິກ">
          <i data-lucide="x" style="width: 14px; height: 14px;"></i>
        </button>
      </div>
      <div id="customerResults" style="position: relative;"></div>
      <div id="customerInfo" style="padding: 6px 16px; font-size: 12px; font-weight: 700; color: var(--success-dark); background: var(--success-50); border-bottom: 1px solid #a7f3d0; display: none; align-items: center; gap: 6px;"></div>

      <!-- Cart Item List -->
      <div class="cart-list" id="cartItems"></div>

      <!-- Cart Bottom Calculations -->
      <div style="padding: 14px 18px; border-top: 1.5px solid var(--border); background: var(--card);">
        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">
          <span>ຍອດລວມ (<span id="sumQty" class="tabular">0</span> ລາຍການ)</span>
          <span id="sumSubtotal" class="tabular" style="font-weight: 700; color: var(--text);">0 ₭</span>
        </div>

        <!-- Discount row -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span>ສ່ວນຫຼຸດ:</span>
            <select id="discountType" onchange="renderCart()" style="height: 30px; width: 92px; padding: 0 6px; font-size: 12px; border-radius: 5px;">
              <option value="amount">₭ (ກີບ)</option>
              <option value="percent">% (ເປີເຊັນ)</option>
            </select>
            <input type="number" id="discountValue" value="0" min="0" oninput="renderCart()" style="height: 30px; width: 78px; padding: 0 6px; font-size: 12.5px; border-radius: 5px; text-align: right;" class="tabular">
          </div>
          <span id="sumDiscount" class="tabular" style="color: var(--danger); font-weight: 700;">-0 ₭</span>
        </div>

        <!-- Points Redemption row -->
        <div id="pointRow" style="display: none; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span>ແລກຄະແນນ:</span>
            <input type="number" id="pointsUsed" value="0" min="0" oninput="renderCart()" style="height: 30px; width: 72px; padding: 0 6px; font-size: 12.5px; border-radius: 5px; text-align: right;" class="tabular">
            <small style="color: var(--text-muted); font-size: 11px;">(1 ຄະແນນ = <?= number_format($point_value) ?> ₭)</small>
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
        <div style="margin-top: 8px; padding-top: 10px; border-top: 2px dashed var(--border-strong); display: flex; justify-content: space-between; align-items: baseline;">
          <span style="font-size: 15px; font-weight: 700;">ຍອດຕ້ອງຊຳລະ</span>
          <span id="sumTotal" class="tabular" style="font-size: 26px; font-weight: 800; color: var(--text);">0 ₭</span>
        </div>

        <!-- Actions -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px;">
          <button class="btn btn-secondary" onclick="holdSale()">
            <i data-lucide="pause" style="width: 15px; height: 15px;"></i> ພັກບິນ (F2)
          </button>
          <button class="btn btn-secondary" style="color: var(--danger);" onclick="clearCart()">
            <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i> ລຶບບິນ
          </button>
          <button class="btn btn-primary" id="btnPay" onclick="openPayModal()" style="grid-column: span 2; height: 52px; font-size: 16px; justify-content: space-between; padding: 0 20px;">
            <span style="display: flex; align-items: center; gap: 8px;">
              <i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i>
              <span>ຊຳລະເງິນ (F12)</span>
            </span>
            <span id="payBtnAmount" class="tabular">0 ₭</span>
          </button>
        </div>
      </div>
    </aside>
  </main>

  <!-- ================= PAYMENT MODAL ================= -->
  <div class="modal-overlay" id="payModal">
    <div class="modal-window" style="width: 520px;">
      <div style="padding: 16px 20px; border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="wallet" style="width: 18px; height: 18px; color: var(--primary);"></i>
          <span>ຊຳລະເງິນ — <span id="payTotalLabel" class="tabular"></span></span>
        </h3>
        <button class="btn btn-secondary btn-sm" onclick="closeModal('payModal')" style="width: 32px; height: 32px; padding: 0;">
          <i data-lucide="x" style="width: 15px; height: 15px;"></i>
        </button>
      </div>

      <div style="padding: 20px;">
        <!-- Methods Tabs -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;">
          <button class="btn btn-secondary active" id="tabCash" onclick="selectPayMethod('cash')" style="height: 48px; border-radius: var(--radius-sm); font-size: 13.5px;">
            <i data-lucide="banknote" style="width: 16px; height: 16px;"></i> ເງິນສົດ
          </button>
          <button class="btn btn-secondary" id="tabQR" onclick="selectPayMethod('qr')" style="height: 48px; border-radius: var(--radius-sm); font-size: 13.5px;">
            <i data-lucide="qr-code" style="width: 16px; height: 16px;"></i> BCEL One / QR
          </button>
          <button class="btn btn-secondary" id="tabCard" onclick="selectPayMethod('card')" style="height: 48px; border-radius: var(--radius-sm); font-size: 13.5px;">
            <i data-lucide="credit-card" style="width: 16px; height: 16px;"></i> ບັດ
          </button>
        </div>

        <!-- Cash Panel -->
        <div id="cashPanel">
          <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">ຮັບເງິນມາ (₭):</label>
          <input type="number" id="paidAmount" oninput="calcChange()" class="cash-input tabular" placeholder="0" style="width: 100%; height: 50px; font-size: 24px; font-weight: 800; text-align: right; border: 2px solid var(--border-strong); border-radius: var(--radius-sm); padding: 0 14px;">

          <div class="quick-cash-row">
            <button class="quick-cash-btn" onclick="setExactPaid()">ພໍດີບິນ</button>
            <button class="quick-cash-btn" onclick="addPaid(20000)">+20,000</button>
            <button class="quick-cash-btn" onclick="addPaid(50000)">+50,000</button>
            <button class="quick-cash-btn" onclick="addPaid(100000)">+100,000</button>
          </div>

          <div class="change-display" id="changeDisplay">
            <span style="font-weight: 700;" id="changeLabel">ເງິນທອນ:</span>
            <span class="tabular" style="font-size: 24px; font-weight: 800;" id="changeAmount">0 ₭</span>
          </div>
        </div>

        <!-- QR Panel -->
        <div id="qrPanel" style="display: none; text-align: center; padding: 10px 0;">
          <div style="width: 190px; height: 190px; border: 2px dashed var(--border-strong); border-radius: var(--radius); margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; background: #fff; padding: 10px;">
            <img id="qrImg" alt="QR Code" style="width: 100%; height: 100%; object-fit: contain;">
          </div>
          <p style="font-size: 13px; color: var(--text-muted); font-weight: 600;">ໃຫ້ລູກຄ້າສະແກນ QR ຜ່ານ BCEL One ຫຼື LAO QR</p>
        </div>

        <!-- Card Panel -->
        <div id="cardPanel" style="display: none; text-align: center; padding: 30px 10px; color: var(--text-muted);">
          <i data-lucide="credit-card" style="width: 48px; height: 48px; margin-bottom: 8px; color: var(--text);"></i>
          <p style="font-size: 14px; font-weight: 600;">ຮູດບັດຜ່ານເຄື່ອງ EDC ແລ້ວກົດຢືນຢັນການຊຳລະ</p>
        </div>

        <label style="display: flex; gap: 8px; align-items: center; margin-top: 14px; font-size: 13px; font-weight: 600; cursor: pointer;">
          <input type="checkbox" id="taxInvoice" style="width: 16px; height: 16px;"> ອອກໃບກຳກັບພາສີ (Tax Invoice)
        </label>
      </div>

      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1.5px solid var(--border); display: flex; gap: 10px;">
        <button class="btn btn-secondary" style="flex: 0.35;" onclick="closeModal('payModal')">ຍົກເລີກ</button>
        <button class="btn btn-success btn-lg" id="confirmPayBtn" onclick="confirmPay()" style="flex: 1;">
          <i data-lucide="printer" style="width: 18px; height: 18px;"></i>
          <span>ຢືນຢັນ & ພິມໃບບິນ</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ================= HELD ORDERS MODAL ================= -->
  <div class="modal-overlay" id="heldModal">
    <div class="modal-window" style="width: 460px;">
      <div style="padding: 16px 20px; border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="pause-circle" style="width: 18px; height: 18px; color: var(--warning);"></i>
          <span>ບິນທີ່ພັກໄວ້ (Held Bills)</span>
        </h3>
        <button class="btn btn-secondary btn-sm" onclick="closeModal('heldModal')" style="width: 32px; height: 32px; padding: 0;">
          <i data-lucide="x" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div id="heldList" style="padding: 14px 20px; max-height: 60vh; overflow-y: auto;"></div>
    </div>
  </div>

  <!-- ================= NUMPAD MODAL ================= -->
  <div class="modal-overlay" id="numpadModal">
    <div class="modal-window" style="width: 320px;">
      <div style="padding: 14px 18px; border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 15px; font-weight: 800;" id="numpadTitle">ໃສ່ຈຳນວນ</h3>
        <button class="btn btn-secondary btn-sm" onclick="closeModal('numpadModal')" style="width: 28px; height: 28px; padding: 0;">
          <i data-lucide="x" style="width: 14px; height: 14px;"></i>
        </button>
      </div>
      <div style="padding: 16px;">
        <input type="number" id="numpadInput" class="clean-input tabular" style="height: 48px; text-align: center; font-size: 26px; font-weight: 800;" value="1">
        <div class="numpad-grid">
          <button class="numpad-btn" onclick="pressNum(1)">1</button>
          <button class="numpad-btn" onclick="pressNum(2)">2</button>
          <button class="numpad-btn" onclick="pressNum(3)">3</button>
          <button class="numpad-btn" onclick="pressNum(4)">4</button>
          <button class="numpad-btn" onclick="pressNum(5)">5</button>
          <button class="numpad-btn" onclick="pressNum(6)">6</button>
          <button class="numpad-btn" onclick="pressNum(7)">7</button>
          <button class="numpad-btn" onclick="pressNum(8)">8</button>
          <button class="numpad-btn" onclick="pressNum(9)">9</button>
          <button class="numpad-btn" style="color: var(--danger);" onclick="clearNum()">C</button>
          <button class="numpad-btn" onclick="pressNum(0)">0</button>
          <button class="numpad-btn" style="color: var(--success);" onclick="confirmNum()">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= SHIFT MODAL ================= -->
  <div class="modal-overlay" id="shiftModal">
    <div class="modal-window" style="width: 400px;">
      <div style="padding: 16px 20px; border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="clock" style="width: 18px; height: 18px; color: var(--success);"></i>
          <span>ເປີດກະເຮັດວຽກ</span>
        </h3>
        <button class="btn btn-secondary btn-sm" onclick="closeModal('shiftModal')" style="width: 32px; height: 32px; padding: 0;">
          <i data-lucide="x" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div style="padding: 20px;">
        <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">ເງິນທອນເລີ່ມຕົ້ນໃນລີ້ນຊັກ (₭):</label>
        <input type="number" id="openingCash" value="0" min="0" class="clean-input tabular" style="height: 48px; font-size: 20px; font-weight: 700;">
      </div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1.5px solid var(--border); display: flex; gap: 10px;">
        <button class="btn btn-secondary" style="flex: 0.4;" onclick="closeModal('shiftModal')">ຍົກເລີກ</button>
        <button class="btn btn-success" style="flex: 1;" onclick="openShift()">ຢືນຢັນເປີດກະ</button>
      </div>
    </div>
  </div>

  <!-- ================= CLOSE SHIFT MODAL ================= -->
  <div class="modal-overlay" id="closeShiftModal">
    <div class="modal-window" style="width: 420px;">
      <div style="padding: 16px 20px; border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="lock" style="width: 18px; height: 18px; color: var(--danger);"></i>
          <span>ປິດກະ — ນັບເງິນລີ້ນຊັກ</span>
        </h3>
        <button class="btn btn-secondary btn-sm" onclick="closeModal('closeShiftModal')" style="width: 32px; height: 32px; padding: 0;">
          <i data-lucide="x" style="width: 15px; height: 15px;"></i>
        </button>
      </div>
      <div style="padding: 20px;">
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">ເງິນສົດທີ່ຄວນມີ: <strong id="expectedCashLabel" class="tabular" style="color: var(--text); font-size: 16px;"></strong></p>
        <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">ເງິນສົດນັບໄດ້ຕົວຈິງ (₭):</label>
        <input type="number" id="closingCashInput" min="0" class="clean-input tabular" style="height: 48px; font-size: 20px; font-weight: 700;">
      </div>
      <div style="padding: 14px 20px; background: var(--bg-subtle); border-top: 1.5px solid var(--border); display: flex; gap: 10px;">
        <button class="btn btn-secondary" style="flex: 0.4;" onclick="closeModal('closeShiftModal')">ຍົກເລີກ</button>
        <button class="btn btn-danger" style="flex: 1;" onclick="confirmCloseShift()">ຢືນຢັນປິດກະ</button>
      </div>
    </div>
  </div>

  <!-- Toast Element -->
  <div id="posToast" class="pos-toast"></div>

  <!-- ================= CLIENT JAVASCRIPT ================= -->
  <script>
    // State & Constants
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

    function showToast(msg, type = 'default') {
      const el = document.getElementById('posToast');
      el.className = 'pos-toast show' + (type !== 'default' ? ' toast-' + type : '');
      el.textContent = msg;
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
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
      lucide.createIcons();
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

    // Render Catalog
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
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 48px 20px;"><i data-lucide="package-search" style="width: 36px; height: 36px; margin-bottom: 8px; opacity: 0.5;"></i><p>ບໍ່ພົບສິນຄ້າທີ່ຄົ້ນຫາ</p></div>';
        lucide.createIcons();
        return;
      }

      grid.innerHTML = list.map(p => {
        const out = Number(p.stock_qty) <= 0;
        const low = Number(p.stock_qty) > 0 && Number(p.stock_qty) <= Number(p.min_stock);
        const imgBlock = (showProductImages && p.image)
          ? `<div class="prod-img-wrap"><img src="uploads/${esc(p.image)}" alt="${esc(p.name)}" loading="lazy"></div>`
          : '';
        return `
          <div class="prod-card ${out ? 'out' : ''}" onclick="addToCartById(${p.id})">
            <div>
              ${imgBlock}
              <div style="display: flex; justify-content: space-between; align-items: center; gap: 4px;">
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">${esc(p.category_name || 'ທົ່ວໄປ')}</span>
                <span style="font-family: var(--font-mono); font-size: 10.5px; color: var(--text-subtle);">${esc(p.barcode || '')}</span>
              </div>
              <div style="font-size: 13.5px; font-weight: 700; color: var(--text); line-height: 1.35; margin-top: 4px;">${esc(p.name)}</div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 12px;">
              <span class="tabular" style="font-size: 16px; font-weight: 800; color: var(--text);">${fmt(p.sell_price)} ₭</span>
              <span class="badge tabular ${out ? 'badge-danger' : (low ? 'badge-warning' : '')}" style="font-size: 11px;">
                ${out ? 'ໝົດສະຕ໋ອກ' : 'ເຫຼືອ ' + p.stock_qty}
              </span>
            </div>
          </div>
        `;
      }).join('');
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

    function clearCart() {
      if (!cart.length) return;
      if (confirm('ລຶບລາຍການສິນຄ້າທັງໝົດໃນບິນແທ້ບໍ່?')) {
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

    // Calculations
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

      if (!cart.length) {
        wrap.innerHTML = '<div style="text-align: center; color: var(--text-subtle); padding: 54px 0;"><i data-lucide="shopping-cart" style="width: 44px; height: 44px; stroke-width: 1.5; margin-bottom: 8px;"></i><p style="font-weight: 600;">ຍັງບໍ່ມີສິນຄ້າໃນບິນ</p><small>ສະແກນບາໂຄດ ຫຼື ຄລິກເລືອກສິນຄ້າທາງຊ້າຍ</small></div>';
        document.getElementById('sumQty').textContent = '0';
        document.getElementById('sumSubtotal').textContent = '0 ₭';
        document.getElementById('sumDiscount').textContent = '-0 ₭';
        document.getElementById('sumPointsDiscount').textContent = '-0 ₭';
        if (document.getElementById('sumTax')) document.getElementById('sumTax').textContent = '0 ₭';
        document.getElementById('sumTotal').textContent = '0 ₭';
        document.getElementById('payBtnAmount').textContent = '0 ₭';
        document.getElementById('btnPay').disabled = true;
        lucide.createIcons();
        return;
      }

      wrap.innerHTML = cart.map((i, idx) => `
        <div class="cart-row ${i._flash ? 'flash' : ''}">
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 13.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${esc(i.name)}</div>
            <div class="tabular" style="font-size: 12px; color: var(--text-muted);">${fmt(i.price)} ₭ / ໜ່ວຍ</div>
          </div>
          <div style="display: flex; align-items: center; border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden;">
            <button class="btn btn-secondary btn-sm" style="width: 30px; height: 30px; padding: 0; border: none; border-radius: 0;" onclick="changeQty(${idx}, -1)">-</button>
            <span class="tabular" style="width: 34px; text-align: center; font-size: 13.5px; font-weight: 700; cursor: pointer;" onclick="openNumpad(${idx})" title="ຄລິກໃສ່ຈຳນວນ">${i.qty}</span>
            <button class="btn btn-secondary btn-sm" style="width: 30px; height: 30px; padding: 0; border: none; border-radius: 0;" onclick="changeQty(${idx}, 1)">+</button>
          </div>
          <div class="tabular" style="font-size: 14.5px; font-weight: 800; min-width: 82px; text-align: right;">${fmt(i.price * i.qty)} ₭</div>
          <button class="btn btn-secondary btn-sm" style="width: 30px; height: 30px; padding: 0; border: none; color: var(--danger);" onclick="removeItem(${idx})" title="ລຶບລາຍການ">
            <i data-lucide="x" style="width: 14px; height: 14px;"></i>
          </button>
        </div>
      `).join('');

      cart.forEach(i => i._flash = false);

      document.getElementById('sumQty').textContent = cart.reduce((s, i) => s + i.qty, 0);
      document.getElementById('sumSubtotal').textContent = fmt(t.subtotal) + ' ₭';
      document.getElementById('sumDiscount').textContent = '-' + fmt(t.discAmount) + ' ₭';
      document.getElementById('sumPointsDiscount').textContent = '-' + fmt(t.ptsDiscount) + ' ₭';

      if (document.getElementById('sumTax')) {
        document.getElementById('sumTax').textContent = fmt(t.tax) + ' ₭';
      }

      document.getElementById('sumTotal').textContent = fmt(t.total) + ' ₭';
      document.getElementById('payBtnAmount').textContent = fmt(t.total) + ' ₭';
      document.getElementById('btnPay').disabled = !HAS_SHIFT;
      lucide.createIcons();
    }

    // Barcode Listener
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

    // Customer Search (Fixed action name & safe index selection)
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
          resBox.innerHTML = '<div style="position: absolute; top: 0; left: 0; right: 0; background: #fff; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; color: var(--text-muted); box-shadow: var(--shadow-md); z-index: 30;">ບໍ່ພົບສະມາຊິກ</div>';
          return;
        }
        resBox.innerHTML = '<div style="position: absolute; top: 0; left: 0; right: 0; background: #fff; border: 1.5px solid var(--border); border-radius: var(--radius-sm); max-height: 220px; overflow-y: auto; box-shadow: var(--shadow-md); z-index: 30;">' +
          lastCustomerResults.map((c, idx) => `
            <div style="padding: 9px 14px; border-bottom: 1px solid var(--border); cursor: pointer;" onclick="selectCustomerByIdx(${idx})">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong>${esc(c.name)}</strong>
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
      info.innerHTML = `<i data-lucide="award" style="width:14px;height:14px;"></i> <span>${esc(c.name)} | ຄະແນນສະສົມ: <strong class="tabular">${fmt(c.points)}</strong> (ແລກໄດ້ສູງສຸດ <span class="tabular">${fmt(c.points * POINT_VALUE)} ₭</span>)</span>`;
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

    // Payment Methods & Quick Cash
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
        img.src = PAYMENT_QR ? ('uploads/' + PAYMENT_QR) : ('https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=LAOQR-PAY-' + t.total);
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

    // Held Bills (F2)
    async function holdSale() {
      if (!cart.length) {
        openHeldList();
        return;
      }
      if (!HAS_SHIFT) {
        showToast('ກະລຸນາເປີດກະກ່ອນ', 'danger');
        openModal('shiftModal');
        return;
      }
      const note = prompt('ໝາຍເຫດບິນພັກ (ເຊັ່ນ: ຊື່ລູກຄ້າ / ໂຕະ):', '');
      if (note === null) return;
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
              <strong style="font-size: 14px; font-family: var(--font-mono);">${esc(s.invoice_no)}</strong><br>
              <small style="color: var(--text-muted);">${esc(s.note || 'ບໍ່ມີໝາຍເຫດ')} • ${s.created_at}</small>
              <div class="tabular" style="font-weight: 800; color: var(--text); margin-top: 2px;">${fmt(s.total ?? s.subtotal)} ₭</div>
            </div>
            <div style="display:flex;gap:6px;">
              <button class="btn btn-primary btn-sm" onclick="resumeHeld(${s.id})">
                <i data-lucide="play" style="width:13px;height:13px;"></i> ດຶງບິນ
              </button>
              <button class="btn btn-danger btn-sm" onclick="deleteHeld(${s.id})" title="ລຶບບິນພັກ">
                <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
              </button>
            </div>
          </div>
        `).join('');
        lucide.createIcons();
      } else {
        box.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 28px 0;">ບໍ່ມີບິນທີ່ພັກໄວ້</p>';
      }
      openModal('heldModal');
    }

    async function deleteHeld(id) {
      if (!confirm('ຕ້ອງການລຶບບິນພັກນີ້ຖາວອນບໍ່?')) return;
      const res = await api('delete_held', { id });
      if (res.ok) {
        if (heldResumeId == id) heldResumeId = null;
        showToast('ລຶບບິນພັກແລ້ວ', 'success');
        openHeldList();
        updateHeldCounter();
      }
    }

    async function resumeHeld(id) {
      if (cart.length && !confirm('ບິນປັດຈຸບັນຈະຖືກລ້າງ, ດຳເນີນຕໍ່ບໍ່?')) return;
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

    // Shifts
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
        alert('ປິດກະສຳເລັດແລ້ວ!\nເງິນສົດທີ່ຄວນມີ: ' + fmt(res.expected) + ' ₭\nສ່ວນຕ່າງ: ' + (diff >= 0 ? '+' : '') + fmt(diff) + ' ₭');
        location.reload();
      } else {
        showToast(res.error, 'danger');
      }
    }

    // API Helper
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

    // Global Shortcuts (F12, F2, Esc)
    window.addEventListener('keydown', (e) => {
      if (e.key === 'F12') {
        e.preventDefault();
        if (cart.length > 0) openPayModal();
      } else if (e.key === 'F2') {
        e.preventDefault();
        holdSale();
      } else if (e.key === 'Escape') {
        closeModal('payModal');
        closeModal('heldModal');
        closeModal('numpadModal');
        closeModal('shiftModal');
        closeModal('closeShiftModal');
      }
    });

    // Initialize
    renderProducts();
    renderCart();
    updateHeldCounter();
    lucide.createIcons();
  </script>
</body>
</html>
