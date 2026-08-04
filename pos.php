<?php
/**
 * pos.php - ໜ້າຂາຍສິນຄ້າ (Front-End / Cashier)
 * - ສະແກນບາໂຄດ / ຄົ້ນຫາສິນຄ້າ
 * - ສ່ວນຫຼຸດ % ຫຼື ຈຳນວນເງິນ
 * - ຈ່າຍເງິນສົດ / ບັດ / QR
 * - ພັກບິນ (Hold), ດຶງບິນພັກ, ພິມໃບບິນ
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
<title>ໜ້າຂາຍ POS - <?= h(get_setting('store_name')) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="pos-layout">

  <!-- ========== ຊ້າຍ: ຄົ້ນຫາ + ສິນຄ້າ ========== -->
  <div class="pos-left">
    <?php if (!$shift): ?>
    <div class="shift-banner">
      <span>⚠️ ທ່ານຍັງບໍ່ໄດ້ເປີດກະ — ຕ້ອງເປີດກະກ່ອນຈຶ່ງຂາຍໄດ້</span>
      <button class="btn btn-warning btn-sm" onclick="openModal('shiftModal')">ເປີດກະ</button>
    </div>
    <?php else: ?>
    <div class="shift-banner" style="background:#dcfce7;color:#166534;border-color:#bbf7d0">
      <span>🟢 ກະ #<?= $shift['id'] ?> ເປີດຢູ່ (ຕັ້ງແຕ່ <?= date('H:i', strtotime($shift['opened_at'])) ?>)</span>
      <button class="btn btn-danger btn-sm" onclick="openCloseShift()">🔴 ປິດກະ</button>
    </div>
    <?php endif; ?>

    <div class="pos-topbar">
      <?php if ($is_manager): ?><a href="dashboard.php" class="back">⬅ ຫຼັງບ້ານ</a><?php endif; ?>
      <input type="text" id="barcodeInput" placeholder="🔍 ສະແກນບາໂຄດ ແລ້ວກົດ Enter..." autofocus>
      <input type="text" id="searchInput" placeholder="ຄົ້ນຫາຊື່ / ລະຫັດສິນຄ້າ...">
      <select id="catFilter" onchange="renderProducts()">
        <option value="">ທຸກໝວດໝູ່</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="product-grid" id="productGrid"></div>
  </div>

  <!-- ========== ຂວາ: ກະຕ່າ / ບິນ ========== -->
  <div class="pos-right">
    <div class="cart-header">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <strong>🧾 ບິນຂາຍ</strong>
        <span style="font-size:13px;color:#64748b">👤 <?= h($u['fullname']) ?>
          <?= $shift ? '| ກະ #' . $shift['id'] : '' ?></span>
      </div>
      <div style="display:flex;gap:6px;margin-top:8px">
        <input type="text" id="customerSearch" placeholder="ຄົ້ນຫາສະມາຊິກ (ຊື່/ເບີໂທ)..."
               style="flex:1;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit">
        <button class="btn btn-sm" onclick="clearCustomer()">✕</button>
      </div>
      <div id="customerResults" style="position:relative"></div>
      <div id="customerInfo" style="font-size:13px;color:#16a34a;margin-top:4px"></div>
    </div>

    <div class="cart-items" id="cartItems">
      <p style="text-align:center;color:#94a3b8;padding:30px 0">ຍັງບໍ່ມີສິນຄ້າໃນບິນ</p>
    </div>

    <div class="cart-summary">
      <div class="sum-row"><span>ລວມ (<span id="sumQty">0</span> ລາຍການ)</span><span id="sumSubtotal">0 ₭</span></div>
      <div class="discount-row">
        <span style="font-size:14px">ສ່ວນຫຼຸດ:</span>
        <select id="discountType" onchange="renderCart()">
          <option value="amount">ຈຳນວນເງິນ (₭)</option>
          <option value="percent">ເປີເຊັນ (%)</option>
        </select>
        <input type="number" id="discountValue" value="0" min="0" style="width:100px" oninput="renderCart()">
      </div>
      <div class="discount-row" id="pointRow" style="display:none">
        <span style="font-size:14px">ໃຊ້ຄະແນນ:</span>
        <input type="number" id="pointsUsed" value="0" min="0" style="width:90px" oninput="renderCart()">
        <small style="color:#64748b">(1 ຄະແນນ = <?= number_format($point_value) ?> ₭)</small>
      </div>
      <div class="sum-row"><span>ຫຼຸດ</span><span id="sumDiscount" style="color:#dc2626">- 0 ₭</span></div>
      <?php if ($tax_rate > 0): ?>
      <div class="sum-row"><span>ພາສີ (<?= $tax_rate ?>%)</span><span id="sumTax">0 ₭</span></div>
      <?php endif; ?>
      <div class="sum-row total"><span>ຕ້ອງຈ່າຍ</span><span id="sumTotal">0 ₭</span></div>
    </div>

    <div class="pay-buttons">
      <button class="btn btn-warning" onclick="holdSale()">⏸ ພັກບິນ</button>
      <button class="btn" onclick="openHeldList()">📂 ບິນທີ່ພັກ</button>
      <button class="btn btn-danger" onclick="clearCart()">🗑 ຍົກເລີກ</button>
      <button class="btn btn-success btn-lg" onclick="openPayModal()">💰 ຈ່າຍເງິນ</button>
    </div>
  </div>
</div>

<!-- ========== Modal: ຈ່າຍເງິນ ========== -->
<div class="modal-bg" id="payModal">
  <div class="modal">
    <h3>💰 ຊຳລະເງິນ — <span id="payTotalLabel"></span></h3>
    <div class="pay-methods">
      <div class="pay-method active" data-method="cash" onclick="selectMethod(this)"><span class="pm-icon">💵</span>ເງິນສົດ</div>
      <div class="pay-method" data-method="card" onclick="selectMethod(this)"><span class="pm-icon">💳</span>ບັດ</div>
      <div class="pay-method" data-method="qr" onclick="selectMethod(this)"><span class="pm-icon">📱</span>QR Code</div>
    </div>
    <div id="cashPanel">
      <div class="form-group">
        <label>ຮັບເງິນມາ (₭)</label>
        <input type="number" id="paidAmount" min="0" oninput="calcChange()" style="font-size:20px;font-weight:700">
      </div>
      <div class="sum-row" style="font-size:18px"><span>ເງິນທອນ:</span><strong id="changeAmount">0 ₭</strong></div>
      <div style="display:flex;gap:6px;margin:10px 0;flex-wrap:wrap" id="quickCash"></div>
    </div>
    <div id="qrPanel" style="display:none" class="qr-box">
      <p>ໃຫ້ລູກຄ້າສະແກນ QR ເພື່ອຊຳລະ</p>
      <img id="qrImg" alt="QR">
      <?php if (!$payment_qr): ?>
      <p style="color:#dc2626;font-size:12px">⚠️ ນີ້ເປັນ QR ຕົວຢ່າງ (ບໍ່ແມ່ນບັນຊີແທ້) — ອັບໂຫຼດ QR ຮັບເງິນຕົວຈິງໄດ້ທີ່ໜ້າ <a href="settings.php" target="_blank">ຕັ້ງຄ່າ</a></p>
      <?php endif; ?>
      <p style="color:#64748b;font-size:13px">ຢືນຢັນເມື່ອໄດ້ຮັບເງິນແລ້ວ</p>
    </div>
    <div id="cardPanel" style="display:none">
      <p style="text-align:center;padding:14px;color:#64748b">💳 ຮູດບັດຜ່ານເຄື່ອງ EDC ແລ້ວກົດຢືນຢັນ</p>
    </div>
    <label style="display:flex;gap:8px;align-items:center;margin:10px 0">
      <input type="checkbox" id="taxInvoice"> ອອກໃບກຳກັບພາສີ (Tax Invoice)
    </label>
    <div style="display:flex;gap:10px">
      <button class="btn btn-block" onclick="closeModal('payModal')">ປິດ</button>
      <button class="btn btn-success btn-block btn-lg" id="confirmPayBtn" onclick="confirmPay()">✅ ຢືນຢັນຈ່າຍ</button>
    </div>
  </div>
</div>

<!-- ========== Modal: ບິນທີ່ພັກໄວ້ ========== -->
<div class="modal-bg" id="heldModal">
  <div class="modal">
    <h3>📂 ບິນທີ່ພັກໄວ້ (Hold Sale)</h3>
    <div id="heldList"></div>
    <button class="btn btn-block" onclick="closeModal('heldModal')" style="margin-top:12px">ປິດ</button>
  </div>
</div>

<!-- ========== Modal: ເປີດກະ ========== -->
<div class="modal-bg" id="shiftModal">
  <div class="modal">
    <h3>⏰ ເປີດກະເຮັດວຽກ</h3>
    <div class="form-group">
      <label>ເງິນທອນເລີ່ມຕົ້ນໃນລີ້ນຊັກ (₭)</label>
      <input type="number" id="openingCash" value="0" min="0" style="font-size:18px">
    </div>
    <div style="display:flex;gap:10px;margin-top:14px">
      <button class="btn btn-block" onclick="closeModal('shiftModal')">ປິດ</button>
      <button class="btn btn-success btn-block" onclick="openShift()">ເປີດກະ</button>
    </div>
  </div>
</div>

<!-- ========== Modal: ປິດກະ ========== -->
<div class="modal-bg" id="closeShiftModal">
  <div class="modal">
    <h3>🔴 ປິດກະ — ນັບເງິນລີ້ນຊັກ</h3>
    <p style="margin-bottom:10px;color:#64748b">ເງິນສົດທີ່ຄວນມີ: <strong id="expectedCashLabel"></strong></p>
    <div class="form-group">
      <label>ເງິນສົດນັບໄດ້ຕົວຈິງ (₭)</label>
      <input type="number" id="closingCashInput" min="0" style="font-size:18px">
    </div>
    <div style="display:flex;gap:10px;margin-top:14px">
      <button class="btn btn-block" onclick="closeModal('closeShiftModal')">ຍົກເລີກ</button>
      <button class="btn btn-danger btn-block" onclick="confirmCloseShift()">ຢືນຢັນປິດກະ</button>
    </div>
  </div>
</div>

<script>
// ============ ຂໍ້ມູນສິນຄ້າຈາກເຊີບເວີ ============
const PRODUCTS   = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const TAX_RATE   = <?= json_encode($tax_rate) ?>;
const POINT_VALUE = <?= json_encode($point_value) ?>;
const HAS_SHIFT  = <?= $shift ? 'true' : 'false' ?>;
const PAYMENT_QR = <?= json_encode($payment_qr ?: null) ?>;

let cart = [];          // {id, name, barcode, price, qty, stock}
let customer = null;    // {id, name, points}
let heldResumeId = null;

// ============ ສະແດງສິນຄ້າ ============
function renderProducts() {
  const q   = document.getElementById('searchInput').value.toLowerCase().trim();
  const cat = document.getElementById('catFilter').value;
  const grid = document.getElementById('productGrid');
  grid.innerHTML = '';
  PRODUCTS.filter(p =>
      (!cat || p.category_id == cat) &&
      (!q || p.name.toLowerCase().includes(q) || p.barcode.includes(q))
  ).forEach(p => {
    const stock = parseInt(p.stock_qty);
    const div = document.createElement('div');
    div.className = 'product-card' + (stock <= 0 ? ' out' : '');
    div.innerHTML =
      (p.image ? `<img src="uploads/${p.image}" alt="">` : `<div class="noimg">📦</div>`) +
      `<div class="pname">${esc(p.name)}</div>` +
      `<div class="pprice">${fmt(p.sell_price)} ₭</div>` +
      `<div class="pstock">ຄົງເຫຼືອ: ${stock}</div>`;
    div.onclick = () => addToCart(p);
    grid.appendChild(div);
  });
}

// ============ ກະຕ່າ ============
function addToCart(p, qty = 1) {
  if (parseInt(p.stock_qty) <= 0) { alert('ສິນຄ້າໝົດສະຕ໋ອກ: ' + p.name); return; }
  const item = cart.find(i => i.id == p.id);
  const inCart = item ? item.qty : 0;
  if (inCart + qty > parseInt(p.stock_qty)) {
    alert('ສະຕ໋ອກບໍ່ພຽງພໍ (ຄົງເຫຼືອ ' + p.stock_qty + ')');
    return;
  }
  if (item) item.qty += qty;
  else cart.push({ id: p.id, name: p.name, barcode: p.barcode,
                   price: parseFloat(p.sell_price), qty: qty, stock: parseInt(p.stock_qty) });
  renderCart();
}

function changeQty(idx, delta) {
  const item = cart[idx];
  const q = item.qty + delta;
  if (q <= 0) { cart.splice(idx, 1); }
  else if (q > item.stock) { alert('ສະຕ໋ອກບໍ່ພຽງພໍ'); return; }
  else item.qty = q;
  renderCart();
}

function setQty(idx, val) {
  const q = parseInt(val) || 1;
  if (q > cart[idx].stock) { alert('ສະຕ໋ອກບໍ່ພຽງພໍ'); renderCart(); return; }
  cart[idx].qty = Math.max(1, q);
  renderCart();
}

function removeItem(idx) { cart.splice(idx, 1); renderCart(); }

function clearCart() {
  if (cart.length && !confirm('ຍົກເລີກບິນນີ້ບໍ່?')) return;
  cart = []; heldResumeId = null;
  document.getElementById('discountValue').value = 0;
  document.getElementById('pointsUsed').value = 0;
  renderCart();
}

function calcTotals() {
  const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const dType = document.getElementById('discountType').value;
  let dVal = parseFloat(document.getElementById('discountValue').value) || 0;
  let discount = dType === 'percent' ? subtotal * Math.min(dVal, 100) / 100 : Math.min(dVal, subtotal);
  let ptsUsed = 0, ptsDiscount = 0;
  if (customer) {
    ptsUsed = Math.min(parseInt(document.getElementById('pointsUsed').value) || 0, customer.points);
    ptsDiscount = ptsUsed * POINT_VALUE;
    const maxPts = Math.max(0, subtotal - discount);
    if (ptsDiscount > maxPts) { ptsDiscount = maxPts; ptsUsed = Math.floor(maxPts / POINT_VALUE); }
  }
  const afterDiscount = Math.max(0, subtotal - discount - ptsDiscount);
  const tax = afterDiscount * TAX_RATE / 100;
  return { subtotal, discount, ptsUsed, ptsDiscount, tax, total: afterDiscount + tax };
}

function renderCart() {
  const box = document.getElementById('cartItems');
  if (!cart.length) {
    box.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:30px 0">ຍັງບໍ່ມີສິນຄ້າໃນບິນ</p>';
  } else {
    box.innerHTML = cart.map((i, idx) => `
      <div class="cart-item">
        <div class="ci-name">${esc(i.name)}<small>${fmt(i.price)} ₭ / ໜ່ວຍ</small></div>
        <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
        <input class="ci-qty" type="number" value="${i.qty}" min="1" onchange="setQty(${idx},this.value)">
        <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
        <div class="ci-total">${fmt(i.price * i.qty)} ₭</div>
        <button class="ci-del" onclick="removeItem(${idx})">🗑</button>
      </div>`).join('');
  }
  const t = calcTotals();
  document.getElementById('sumQty').textContent = cart.reduce((s, i) => s + i.qty, 0);
  document.getElementById('sumSubtotal').textContent = fmt(t.subtotal) + ' ₭';
  document.getElementById('sumDiscount').textContent = '- ' + fmt(t.discount + t.ptsDiscount) + ' ₭';
  const taxEl = document.getElementById('sumTax');
  if (taxEl) taxEl.textContent = fmt(t.tax) + ' ₭';
  document.getElementById('sumTotal').textContent = fmt(t.total) + ' ₭';
}

// ============ ບາໂຄດ + ຄົ້ນຫາ ============
document.getElementById('barcodeInput').addEventListener('keydown', function (e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const code = this.value.trim();
  this.value = '';
  if (!code) return;
  const p = PRODUCTS.find(x => x.barcode === code);
  if (p) addToCart(p);
  else alert('ບໍ່ພົບສິນຄ້າບາໂຄດ: ' + code);
});
document.getElementById('searchInput').addEventListener('input', renderProducts);

// ============ ສະມາຊິກ ============
let custTimer = null;
document.getElementById('customerSearch').addEventListener('input', function () {
  clearTimeout(custTimer);
  const q = this.value.trim();
  if (q.length < 1) { document.getElementById('customerResults').innerHTML = ''; return; }
  custTimer = setTimeout(async () => {
    const res = await api('search_customers', { q });
    const box = document.getElementById('customerResults');
    box.innerHTML = '<div style="position:absolute;top:0;left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;z-index:50;max-height:180px;overflow-y:auto">'
      + res.customers.map(c =>
        `<div style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9" onclick='selectCustomer(${JSON.stringify(c)})'>
           <strong>${esc(c.name)}</strong> <small>(${esc(c.code)} | ${esc(c.phone || '-')} | ${c.points} ຄະແນນ)</small>
         </div>`).join('')
      + '</div>';
  }, 250);
});

function selectCustomer(c) {
  customer = c;
  document.getElementById('customerResults').innerHTML = '';
  document.getElementById('customerSearch').value = c.name;
  document.getElementById('customerInfo').textContent =
    '✅ ສະມາຊິກ: ' + c.name + ' | ຄະແນນສະສົມ: ' + c.points;
  document.getElementById('pointRow').style.display = 'flex';
  renderCart();
}

function clearCustomer() {
  customer = null;
  document.getElementById('customerSearch').value = '';
  document.getElementById('customerInfo').textContent = '';
  document.getElementById('customerResults').innerHTML = '';
  document.getElementById('pointRow').style.display = 'none';
  document.getElementById('pointsUsed').value = 0;
  renderCart();
}

// ============ ຈ່າຍເງິນ ============
let payMethod = 'cash';
function openPayModal() {
  if (!cart.length) { alert('ບິນຫວ່າງເປົ່າ'); return; }
  if (!HAS_SHIFT) { alert('ກະລຸນາເປີດກະກ່ອນຂາຍ'); openModal('shiftModal'); return; }
  const t = calcTotals();
  document.getElementById('payTotalLabel').textContent = fmt(t.total) + ' ₭';
  document.getElementById('paidAmount').value = Math.ceil(t.total);
  document.getElementById('qrImg').src = PAYMENT_QR
    ? 'uploads/' + PAYMENT_QR
    : 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' +
      encodeURIComponent('PAY|<?= h(get_setting('store_name')) ?>|' + t.total);
  // ປຸ່ມເງິນດ່ວນ: "ພໍດີ" = ຕັ້ງຄ່າເທົ່າຍອດ, ປຸ່ມແບັງ = ບວກເພີ່ມ
  const quicks = [5000, 10000, 20000, 50000, 100000];
  document.getElementById('quickCash').innerHTML =
    `<button class="btn btn-sm btn-primary" onclick="setPaid(${Math.ceil(t.total)})">ພໍດີ</button>`
    + quicks.map(v => `<button class="btn btn-sm" onclick="addPaid(${v})">+${fmt(v)}</button>`).join('')
    + `<button class="btn btn-sm btn-danger" onclick="setPaid(0)">ລ້າງ</button>`;
  calcChange();
  openModal('payModal');
}

function setPaid(v) {
  document.getElementById('paidAmount').value = v;
  calcChange();
}

function addPaid(v) {
  const el = document.getElementById('paidAmount');
  el.value = (parseFloat(el.value) || 0) + v;
  calcChange();
}

function selectMethod(el) {
  document.querySelectorAll('.pay-method').forEach(m => m.classList.remove('active'));
  el.classList.add('active');
  payMethod = el.dataset.method;
  document.getElementById('cashPanel').style.display = payMethod === 'cash' ? 'block' : 'none';
  document.getElementById('qrPanel').style.display   = payMethod === 'qr'   ? 'block' : 'none';
  document.getElementById('cardPanel').style.display = payMethod === 'card' ? 'block' : 'none';
}

function calcChange() {
  const t = calcTotals();
  const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
  document.getElementById('changeAmount').textContent = fmt(Math.max(0, paid - t.total)) + ' ₭';
}

async function confirmPay() {
  const t = calcTotals();
  let paid = t.total;
  if (payMethod === 'cash') {
    paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    if (paid < t.total) { alert('ເງິນທີ່ຮັບມາບໍ່ພຽງພໍ'); return; }
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
    if (!res.ok) { alert(res.error); return; }
    closeModal('payModal');
    const tax = document.getElementById('taxInvoice').checked ? '&tax=1' : '';
    window.open('receipt.php?id=' + res.sale_id + tax, '_blank', 'width=400,height=600');
    cart = []; heldResumeId = null;
    document.getElementById('discountValue').value = 0;
    document.getElementById('pointsUsed').value = 0;
    clearCustomer();
    await reloadProducts();
    document.getElementById('barcodeInput').focus();
  } finally { btn.disabled = false; }
}

// ============ ພັກບິນ ============
async function holdSale() {
  if (!cart.length) { alert('ບິນຫວ່າງເປົ່າ'); return; }
  if (!HAS_SHIFT) { alert('ກະລຸນາເປີດກະກ່ອນ'); openModal('shiftModal'); return; }
  const note = prompt('ໝາຍເຫດບິນພັກ (ເຊັ່ນ: ຊື່ລູກຄ້າ):', '') || '';
  const res = await api('checkout', {
    items: cart.map(i => ({ id: i.id, qty: i.qty })),
    discount_type: document.getElementById('discountType').value,
    discount_value: parseFloat(document.getElementById('discountValue').value) || 0,
    customer_id: customer ? customer.id : null,
    points_used: 0, payment_method: null, paid_amount: 0,
    hold: true, note, resume_id: heldResumeId,
  });
  if (!res.ok) { alert(res.error); return; }
  alert('ພັກບິນແລ້ວ: ' + res.invoice_no);
  cart = []; heldResumeId = null;
  document.getElementById('discountValue').value = 0;
  clearCustomer(); renderCart();
}

async function openHeldList() {
  const res = await api('held_list', {});
  const box = document.getElementById('heldList');
  box.innerHTML = res.sales.length ? res.sales.map(s => `
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid #e2e8f0">
      <div><strong>${esc(s.invoice_no)}</strong><br>
        <small>${esc(s.note || '-')} | ${s.created_at} | ${fmt(s.subtotal)} ₭</small></div>
      <button class="btn btn-primary btn-sm" onclick="resumeHeld(${s.id})">ດຶງບິນ</button>
    </div>`).join('') : '<p style="color:#94a3b8;text-align:center;padding:16px">ບໍ່ມີບິນທີ່ພັກໄວ້</p>';
  openModal('heldModal');
}

async function resumeHeld(id) {
  if (cart.length && !confirm('ບິນປັດຈຸບັນຈະຖືກລ້າງ, ດຳເນີນຕໍ່ບໍ່?')) return;
  const res = await api('resume_held', { id });
  if (!res.ok) { alert(res.error); return; }
  cart = res.items.map(i => {
    const p = PRODUCTS.find(x => x.id == i.product_id) || {};
    return { id: i.product_id, name: i.product_name, barcode: p.barcode || '',
             price: parseFloat(i.price), qty: parseInt(i.qty),
             stock: parseInt(p.stock_qty || 9999) };
  });
  heldResumeId = id;
  if (res.customer) selectCustomer(res.customer);
  document.getElementById('discountType').value = res.sale.discount_type || 'amount';
  document.getElementById('discountValue').value = res.sale.discount_value || 0;
  closeModal('heldModal');
  renderCart();
}

// ============ ກະ ============
async function openShift() {
  const cash = parseFloat(document.getElementById('openingCash').value) || 0;
  const res = await api('open_shift', { opening_cash: cash });
  if (res.ok) location.reload();
  else alert(res.error);
}

function openCloseShift() {
  if (cart.length && !confirm('ບິນປັດຈຸບັນຍັງບໍ່ໄດ້ຈ່າຍເງິນ, ຢືນຢັນປິດກະຕໍ່ບໍ່?')) return;
  document.getElementById('expectedCashLabel').textContent = fmt(<?= (float)$shift_cash_expected ?>) + ' ₭';
  document.getElementById('closingCashInput').value = <?= (int)$shift_cash_expected ?>;
  openModal('closeShiftModal');
}

async function confirmCloseShift() {
  const val = document.getElementById('closingCashInput').value;
  if (val === '') { alert('ກະລຸນາປ້ອນເງິນນັບໄດ້'); return; }
  const res = await api('close_shift', { closing_cash: parseFloat(val) || 0 });
  if (res.ok) {
    alert('ປິດກະສຳເລັດ\nເງິນຄວນມີ: ' + fmt(res.expected) + ' ₭\nສ່ວນຕ່າງ: ' + fmt(res.difference) + ' ₭');
    location.reload();
  } else alert(res.error);
}

// ============ Utilities ============
function fmt(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 }); }
function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

async function api(action, data) {
  const res = await fetch('api.php?action=' + action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

async function reloadProducts() {
  const res = await api('all_products', {});
  PRODUCTS.length = 0;
  res.products.forEach(p => PRODUCTS.push(p));
  renderProducts();
}

// ໂຟກັສຊ່ອງບາໂຄດຕະຫຼອດ (ຮອງຮັບເຄື່ອງສະແກນບາໂຄດແບບ Keyboard)
document.addEventListener('keydown', e => {
  const tag = document.activeElement.tagName;
  if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT' && !e.ctrlKey && !e.altKey) {
    document.getElementById('barcodeInput').focus();
  }
});

renderProducts();
renderCart();
</script>
</body>
</html>
