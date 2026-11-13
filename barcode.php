<?php
/**
 * barcode.php - ພິມບາໂຄດສິນຄ້າ (JsBarcode + ເຄື່ອງພິມ)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'barcode';
$title = 'ພິມບາໂຄດສິນຄ້າ';

$products = $pdo->query('SELECT id, barcode, name, sell_price FROM products WHERE status=1 ORDER BY name')->fetchAll();
include __DIR__ . '/header.php';
?>
<div class="card no-print">
  <h3><i data-lucide="barcode"></i> <span>ເລືອກສິນຄ້າ ແລະ ຈຳນວນປ້າຍທີ່ຕ້ອງການພິມ</span></h3>
  <div class="form-row" style="align-items:flex-end">
    <div class="form-group">
      <label>ສິນຄ້າ</label>
      <select id="productSel" onchange="genLabels()">
        <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" data-barcode="<?= h($p['barcode']) ?>"
                data-name="<?= h($p['name']) ?>" data-price="<?= $p['sell_price'] ?>">
          <?= h($p['name']) ?> (<?= h($p['barcode']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="max-width:150px">
      <label>ຈຳນວນປ້າຍ</label>
      <input type="number" id="labelCount" value="12" min="1" max="100" class="tabular" oninput="genLabels()">
    </div>
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button class="btn btn-primary" onclick="genLabels()"><i data-lucide="refresh-cw"></i> ສ້າງປ້າຍ</button>
      <button class="btn btn-success" onclick="window.print()"><i data-lucide="printer"></i> ພິມປ້າຍບາໂຄດ</button>
    </div>
  </div>
</div>

<div id="labels" style="display:flex;flex-wrap:wrap;gap:10px;background:var(--card);padding:18px;border:1px solid var(--border);border-radius:var(--radius)"></div>

<script src="assets/vendor/JsBarcode.all.min.js"></script>
<script>
function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function genLabels() {
  const opt = document.getElementById('productSel').selectedOptions[0];
  if (!opt) return;
  const count = Math.min(100, Math.max(1, parseInt(document.getElementById('labelCount').value) || 1));
  const box = document.getElementById('labels');
  box.innerHTML = '';
  for (let i = 0; i < count; i++) {
    const div = document.createElement('div');
    div.style.cssText = 'border:1px dashed #cbd5e1;border-radius:6px;padding:8px 6px;text-align:center;width:195px;background:#fff;color:#0f172a';
    div.innerHTML = `<div style="font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(opt.dataset.name)}</div>
      <svg class="bc"></svg>
      <div class="tabular" style="font-size:13.5px;font-weight:800">${Number(opt.dataset.price).toLocaleString()} ₭</div>`;
    box.appendChild(div);
    JsBarcode(div.querySelector('.bc'), opt.dataset.barcode,
      { format: 'CODE128', width: 1.6, height: 42, fontSize: 12, margin: 2 });
  }
}
genLabels();
</script>
<?php include __DIR__ . '/footer.php'; ?>
