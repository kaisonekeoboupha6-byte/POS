<?php
/**
 * purchases.php - ຮັບສິນຄ້າເຂົ້າສາງຈາກຜູ້ສະໜອງ (ເພີ່ມສະຕ໋ອກ + ອັບເດດຕົ້ນທຶນ)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'purchases';
$title = 'ຮັບສິນຄ້າເຂົ້າສາງ';
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'receive') {
    $supplier_id = (int)$_POST['supplier_id'] ?: null;
    $note = trim($_POST['note']);
    $pids  = $_POST['product_id'] ?? [];
    $qtys  = $_POST['qty'] ?? [];
    $costs = $_POST['cost'] ?? [];

    $lines = [];
    foreach ($pids as $i => $pid) {
        $pid = (int)$pid; $qty = (int)($qtys[$i] ?? 0); $cost = (float)($costs[$i] ?? 0);
        if ($pid && $qty > 0) $lines[] = [$pid, $qty, $cost];
    }

    if ($lines) {
        $pdo->beginTransaction();
        $total = array_sum(array_map(fn($l) => $l[1] * $l[2], $lines));
        $ref = 'PO' . date('Ymd-His');
        $pdo->prepare('INSERT INTO purchases (ref_no, supplier_id, user_id, total, note) VALUES (?,?,?,?,?)')
            ->execute([$ref, $supplier_id, $u['id'], $total, $note ?: null]);
        $purchase_id = (int)$pdo->lastInsertId();

        $stItem = $pdo->prepare('INSERT INTO purchase_items (purchase_id, product_id, qty, cost, total) VALUES (?,?,?,?,?)');
        $stUpd  = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ?, cost_price = ? WHERE id = ?');
        foreach ($lines as [$pid, $qty, $cost]) {
            $stItem->execute([$purchase_id, $pid, $qty, $cost, $qty * $cost]);
            $stUpd->execute([$qty, $cost, $pid]);
        }
        $pdo->commit();
        flash_set('ຮັບສິນຄ້າເຂົ້າສາງສຳເລັດ: ' . $ref . ' (ມູນຄ່າ ' . number_format($total) . ' ₭)');
    } else {
        flash_set('ກະລຸນາເລືອກສິນຄ້າ ແລະ ຈຳນວນ', 'danger');
    }
    header('Location: purchases.php');
    exit;
}

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
$products  = $pdo->query('SELECT id, barcode, name, cost_price, stock_qty FROM products WHERE status=1 ORDER BY name')->fetchAll();
$history   = $pdo->query(
    'SELECT pu.*, s.name AS supplier_name, u.fullname,
            (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = pu.id) item_count
     FROM purchases pu
     LEFT JOIN suppliers s ON s.id = pu.supplier_id
     JOIN users u ON u.id = pu.user_id
     ORDER BY pu.id DESC LIMIT 30')->fetchAll();

include __DIR__ . '/header.php';
?>
<div class="card">
  <h3><i data-lucide="package-open"></i> <span>ບັນທຶກການຮັບສິນຄ້າເຂົ້າສາງໃໝ່</span></h3>
  <form method="post" id="receiveForm">
    <input type="hidden" name="act" value="receive">
    <div class="form-row">
      <div class="form-group">
        <label>ຜູ້ສະໜອງ</label>
        <select name="supplier_id">
          <option value="0">- ບໍ່ລະບຸຜູ້ສະໜອງ -</option>
          <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>ໝາຍເຫດ / ເລກທີໃບບິນຜູ້ສະໜອງ</label><input type="text" name="note" placeholder="ເຊັ່ນ: ໃບສົ່ງເຄື່ອງ #1024"></div>
    </div>
    <table class="table" id="lineTable">
      <tr><th>ສິນຄ້າ</th><th style="width:140px">ຈຳນວນຮັບ</th><th style="width:170px">ຕົ້ນທຶນ/ໜ່ວຍ (₭)</th><th style="width:56px"></th></tr>
    </table>
    <div style="margin-top:14px;display:flex;gap:10px">
      <button type="button" class="btn btn-secondary" onclick="addLine()"><i data-lucide="plus"></i> ເພີ່ມແຖວສິນຄ້າ</button>
      <button type="submit" class="btn btn-success"><i data-lucide="check"></i> ບັນທຶກຮັບເຂົ້າສາງ</button>
    </div>
  </form>
</div>

<div class="card">
  <h3><i data-lucide="history"></i> <span>ປະຫວັດການຮັບສິນຄ້າເຂົ້າສາງ</span></h3>
  <table class="table">
    <tr><th>ເລກທີອ້າງອີງ</th><th>ຜູ້ສະໜອງ</th><th>ຜູ້ບັນທຶກ</th><th class="num">ລາຍການ</th><th class="num">ມູນຄ່າລວມ</th><th>ໝາຍເຫດ</th><th>ວັນທີ</th></tr>
    <?php foreach ($history as $hrow): ?>
    <tr>
      <td style="font-family:var(--font-mono);font-weight:700"><?= h($hrow['ref_no']) ?></td>
      <td><?= h($hrow['supplier_name'] ?? '-') ?></td>
      <td><?= h($hrow['fullname']) ?></td>
      <td class="num"><span class="badge badge-info tabular"><?= $hrow['item_count'] ?></span></td>
      <td class="num"><strong><?= money($hrow['total']) ?></strong></td>
      <td style="color:var(--text-muted)"><?= h($hrow['note'] ?: '-') ?></td>
      <td style="color:var(--text-muted);font-size:12.5px"><?= date('d/m/Y H:i', strtotime($hrow['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:30px">ຍັງບໍ່ມີປະຫວັດການຮັບສິນຄ້າ</td></tr><?php endif; ?>
  </table>
</div>

<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function addLine() {
  const tbl = document.getElementById('lineTable');
  const tr = tbl.insertRow(-1);
  tr.innerHTML = `
    <td><select name="product_id[]" onchange="fillCost(this)">
        <option value="">- ເລືອກສິນຄ້າ -</option>
        ${PRODUCTS.map(p => `<option value="${p.id}" data-cost="${p.cost_price}">
            ${esc(p.name)} (ຄົງເຫຼືອ ${p.stock_qty})</option>`).join('')}
    </select></td>
    <td><input type="number" name="qty[]" min="1" value="1" class="tabular"></td>
    <td><input type="number" name="cost[]" min="0" step="any" value="0" class="tabular"></td>
    <td style="text-align:right"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()" title="ລຶບແຖວ"><i data-lucide="trash-2"></i></button></td>`;
  if (window.lucide) lucide.createIcons();
}
function fillCost(sel) {
  const opt = sel.selectedOptions[0];
  if (opt && opt.dataset.cost) {
    sel.closest('tr').querySelector('input[name="cost[]"]').value = opt.dataset.cost;
  }
}
addLine();
</script>
<?php include __DIR__ . '/footer.php'; ?>
