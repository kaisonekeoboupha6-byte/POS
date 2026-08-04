<?php
/**
 * customers.php - ລູກຄ້າ / ສະມາຊິກ (CRM & Loyalty)
 * - ຂໍ້ມູນສະມາຊິກ + ຄະແນນສະສົມ + ປະຫວັດການຊື້
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'customers';
$title = 'ລູກຄ້າ / ສະມາຊິກ (CRM)';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        if ($name !== '') {
            if ($id) {
                $pdo->prepare('UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=?')
                    ->execute([$name, $phone, $email, $address, $id]);
                flash_set('ແກ້ໄຂຂໍ້ມູນສະມາຊິກສຳເລັດ');
            } else {
                $next = (int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 n FROM customers')->fetch()['n'];
                $code = 'C' . str_pad($next, 4, '0', STR_PAD_LEFT);
                $pdo->prepare('INSERT INTO customers (code, name, phone, email, address) VALUES (?,?,?,?,?)')
                    ->execute([$code, $name, $phone, $email, $address]);
                flash_set('ເພີ່ມສະມາຊິກສຳເລັດ: ' . $code);
            }
        }
    }
    if ($act === 'delete') {
        $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([(int)$_POST['id']]);
        flash_set('ລຶບສະມາຊິກແລ້ວ');
    }
    header('Location: customers.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$view_id = (int)($_GET['view'] ?? 0);

$sql = 'SELECT c.*,
          (SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id AND s.status="completed") buy_count,
          (SELECT COALESCE(SUM(total),0) FROM sales s WHERE s.customer_id=c.id AND s.status="completed") buy_total
        FROM customers c';
$params = [];
if ($q !== '') { $sql .= ' WHERE c.name LIKE ? OR c.phone LIKE ? OR c.code LIKE ?'; $params = ["%$q%", "%$q%", "%$q%"]; }
$sql .= ' ORDER BY c.id DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$customers = $st->fetchAll();

// ປະຫວັດການຊື້ຂອງລູກຄ້າຄົນທີ່ເລືອກ
$history = []; $points_log = []; $viewing = null;
if ($view_id) {
    $st = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $st->execute([$view_id]);
    $viewing = $st->fetch();
    if ($viewing) {
        $st = $pdo->prepare(
            'SELECT s.*, u.fullname FROM sales s JOIN users u ON u.id=s.user_id
             WHERE s.customer_id=? ORDER BY s.id DESC LIMIT 30');
        $st->execute([$view_id]);
        $history = $st->fetchAll();
        $st = $pdo->prepare('SELECT * FROM point_transactions WHERE customer_id=? ORDER BY id DESC LIMIT 30');
        $st->execute([$view_id]);
        $points_log = $st->fetchAll();
    }
}

include __DIR__ . '/header.php';
?>
<div class="toolbar">
  <form method="get" style="display:flex;gap:8px;flex:1">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="🔍 ຄົ້ນຫາຊື່ / ເບີໂທ / ລະຫັດ..." style="flex:1;max-width:340px">
    <button class="btn btn-primary">ຄົ້ນຫາ</button>
  </form>
  <button class="btn btn-success" onclick="editCust(null)">➕ ເພີ່ມສະມາຊິກ</button>
</div>

<div class="card">
  <table class="table">
    <tr><th>ລະຫັດ</th><th>ຊື່</th><th>ເບີໂທ</th><th class="num">ຄະແນນ</th>
        <th class="num">ຈຳນວນຄັ້ງຊື້</th><th class="num">ຍອດຊື້ລວມ</th><th style="width:220px">ຈັດການ</th></tr>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td><?= h($c['code']) ?></td>
      <td><strong><?= h($c['name']) ?></strong></td>
      <td><?= h($c['phone'] ?? '-') ?></td>
      <td class="num"><span class="badge badge-info"><?= number_format($c['points']) ?></span></td>
      <td class="num"><?= number_format($c['buy_count']) ?></td>
      <td class="num"><?= money($c['buy_total']) ?></td>
      <td>
        <a href="customers.php?view=<?= $c['id'] ?>" class="btn btn-sm">📜 ປະຫວັດ</a>
        <button class="btn btn-sm btn-primary" onclick='editCust(<?= json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>✏️</button>
        <form method="post" style="display:inline" onsubmit="return confirm('ລຶບສະມາຊິກນີ້ບໍ່?')">
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn btn-sm btn-danger">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($viewing): ?>
<div class="grid grid-2">
  <div class="card">
    <h3>🧾 ປະຫວັດການຊື້ — <?= h($viewing['name']) ?> (<?= h($viewing['code']) ?>)</h3>
    <table class="table">
      <tr><th>ເລກບິນ</th><th class="num">ຍອດ</th><th>ສະຖານະ</th><th>ວັນທີ</th><th></th></tr>
      <?php foreach ($history as $s): ?>
      <tr>
        <td><?= h($s['invoice_no']) ?></td>
        <td class="num"><?= money($s['total']) ?></td>
        <td><?= ['completed'=>'✅','held'=>'⏸','cancelled'=>'❌'][$s['status']] ?></td>
        <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
        <td><a href="receipt.php?id=<?= $s['id'] ?>" target="_blank">🖨</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="5" style="color:#94a3b8">ຍັງບໍ່ມີປະຫວັດການຊື້</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="card">
    <h3>⭐ ປະຫວັດຄະແນນ — ຄະແນນປັດຈຸບັນ: <?= number_format($viewing['points']) ?></h3>
    <table class="table">
      <tr><th>ປະເພດ</th><th class="num">ຄະແນນ</th><th>ໝາຍເຫດ</th><th>ວັນທີ</th></tr>
      <?php foreach ($points_log as $pt): ?>
      <tr>
        <td><?= ['earn'=>'<span class="badge badge-success">ໄດ້ຮັບ</span>',
                 'redeem'=>'<span class="badge badge-warning">ແລກໃຊ້</span>',
                 'adjust'=>'<span class="badge badge-info">ປັບ</span>'][$pt['type']] ?></td>
        <td class="num"><?= $pt['points'] > 0 ? '+' : '' ?><?= number_format($pt['points']) ?></td>
        <td><?= h($pt['note'] ?? '-') ?></td>
        <td><?= date('d/m/Y H:i', strtotime($pt['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$points_log): ?><tr><td colspan="4" style="color:#94a3b8">ຍັງບໍ່ມີປະຫວັດຄະແນນ</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="modal-bg" id="custModal">
  <div class="modal">
    <h3 id="cuTitle">ເພີ່ມສະມາຊິກ</h3>
    <form method="post">
      <input type="hidden" name="act" value="save"><input type="hidden" name="id" id="cu_id">
      <div class="form-group"><label>ຊື່ *</label><input type="text" name="name" id="cu_name" required></div>
      <div class="form-row">
        <div class="form-group"><label>ເບີໂທ</label><input type="text" name="phone" id="cu_phone"></div>
        <div class="form-group"><label>ອີເມວ</label><input type="text" name="email" id="cu_email"></div>
      </div>
      <div class="form-group"><label>ທີ່ຢູ່</label><input type="text" name="address" id="cu_address"></div>
      <div style="display:flex;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-block" onclick="document.getElementById('custModal').classList.remove('show')">ປິດ</button>
        <button type="submit" class="btn btn-success btn-block">💾 ບັນທຶກ</button>
      </div>
    </form>
  </div>
</div>
<script>
function editCust(c){
  document.getElementById('cuTitle').textContent = c ? '✏️ ແກ້ໄຂສະມາຊິກ' : '➕ ເພີ່ມສະມາຊິກ';
  document.getElementById('cu_id').value = c ? c.id : '';
  document.getElementById('cu_name').value = c ? c.name : '';
  document.getElementById('cu_phone').value = c ? (c.phone || '') : '';
  document.getElementById('cu_email').value = c ? (c.email || '') : '';
  document.getElementById('cu_address').value = c ? (c.address || '') : '';
  document.getElementById('custModal').classList.add('show');
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
