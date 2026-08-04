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
  <h3>ເລືອກສິນຄ້າ ແລະ ຈຳນວນປ້າຍທີ່ຕ້ອງການພິມ</h3>
  <div class="form-row" style="align-items:flex-end">
    <div class="form-group">
      <label>ສິນຄ້າ</label>
      <select id="productSel">
        <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" data-barcode="<?= h($p['barcode']) ?>"
                data-name="<?= h($p['name']) ?>" data-price="<?= $p['sell_price'] ?>">
          <?= h($p['name']) ?> (<?= h($p['barcode']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="max-width:130px">
      <label>ຈຳນວນປ້າຍ</label>
      <input type="number" id="labelCount" value="6" min="1" max="100">
    </div>
    <button class="btn btn-primary" onclick="genLabels()">🏷️ ສ້າງປ້າຍ</button>
    <button class="btn btn-success" onclick="window.print()">🖨 ພິມ</button>
  </div>
</div>

<div id="labels" style="display:flex;flex-wrap:wrap;gap:8px;background:#fff;padding:12px;border-radius:12px"></div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3/dist/JsBarcode.all.min.js"></script>
<script>
function genLabels() {
  const opt = document.getElementById('productSel').selectedOptions[0];
  if (!opt) return;
  const count = Math.min(100, parseInt(document.getElementById('labelCount').value) || 1);
  const box = document.getElementById('labels');
  box.innerHTML = '';
  for (let i = 0; i < count; i++) {
    const div = document.createElement('div');
    div.style.cssText = 'border:1px dashed #cbd5e1;padding:6px;text-align:center;width:190px';
    div.innerHTML = `<div style="font-size:12px;font-weight:600">${opt.dataset.name}</div>
      <svg class="bc"></svg>
      <div style="font-size:13px;font-weight:700">${Number(opt.dataset.price).toLocaleString()} ₭</div>`;
    box.appendChild(div);
    JsBarcode(div.querySelector('.bc'), opt.dataset.barcode,
      { format: 'CODE128', width: 1.6, height: 42, fontSize: 13, margin: 2 });
  }
}
genLabels();
</script>
<?php include __DIR__ . '/footer.php'; ?>
