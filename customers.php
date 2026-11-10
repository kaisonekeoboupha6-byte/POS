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
    <div style="position:relative;flex:1;max-width:360px">
      <i data-lucide="search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted)"></i>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="ຄົ້ນຫາຊື່ / ເບີໂທ / ລະຫັດສະມາຊິກ..." style="padding-left:38px">
    </div>
    <button class="btn btn-primary"><i data-lucide="search"></i> ຄົ້ນຫາ</button>
    <?php if ($q !== ''): ?>
      <a href="customers.php" class="btn btn-secondary"><i data-lucide="x"></i> ລ້າງ</a>
    <?php endif; ?>
  </form>
  <button class="btn btn-success" onclick="editCust(null)"><i data-lucide="user-plus"></i> ເພີ່ມສະມາຊິກ</button>
</div>

<div class="table-wrap">
  <table class="table">
    <tr><th>ລະຫັດ</th><th>ຊື່ສະມາຊິກ</th><th>ເບີໂທ</th><th class="num">ຄະແນນສະສົມ</th>
        <th class="num">ຈຳນວນຄັ້ງຊື້</th><th class="num">ຍອດຊື້ລວມ</th><th style="width:230px;text-align:right">ຈັດການ</th></tr>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td style="font-family:var(--font-mono);font-weight:700;color:var(--primary)"><?= h($c['code']) ?></td>
      <td><strong><?= h($c['name']) ?></strong></td>
      <td class="tabular"><?= h($c['phone'] ?: '-') ?></td>
      <td class="num"><span class="badge badge-info tabular"><?= number_format($c['points']) ?></span></td>
      <td class="num"><?= number_format($c['buy_count']) ?></td>
      <td class="num"><strong><?= money($c['buy_total']) ?></strong></td>
      <td style="text-align:right">
        <a href="customers.php?view=<?= $c['id'] ?>" class="btn btn-sm btn-secondary"><i data-lucide="history"></i> ປະຫວັດ</a>
        <button class="btn btn-sm btn-secondary" onclick='editCust(<?= json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="ແກ້ໄຂ">
          <i data-lucide="pencil"></i>
        </button>
        <form method="post" style="display:inline" onsubmit="return confirm('ລຶບສະມາຊິກນີ້ບໍ່?')">
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn btn-sm btn-danger" title="ລຶບ"><i data-lucide="trash-2"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$customers): ?>
      <tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:32px">ບໍ່ພົບຂໍ້ມູນສະມາຊິກ</td></tr>
    <?php endif; ?>
  </table>
</div>

<?php if ($viewing): ?>
<div class="grid grid-2">
  <div class="card">
    <h3><i data-lucide="receipt"></i> <span>ປະຫວັດການຊື້ — <?= h($viewing['name']) ?> (<?= h($viewing['code']) ?>)</span></h3>
    <table class="table">
      <tr><th>ເລກບິນ</th><th class="num">ຍອດ</th><th>ສະຖານະ</th><th>ວັນທີ</th><th style="text-align:right">ພິມ</th></tr>
      <?php foreach ($history as $s): ?>
      <tr>
        <td style="font-family:var(--font-mono);font-size:12.5px"><?= h($s['invoice_no']) ?></td>
        <td class="num"><strong><?= money($s['total']) ?></strong></td>
        <td>
          <?php
            $stMap = [
              'completed' => ['badge-success', 'ສຳເລັດ'],
              'held'      => ['badge-warning', 'ພັກບິນ'],
              'cancelled' => ['badge-danger', 'ຍົກເລີກ'],
            ][$s['status']] ?? ['badge', $s['status']];
          ?>
          <span class="badge <?= $stMap[0] ?>"><?= $stMap[1] ?></span>
        </td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
        <td style="text-align:right"><a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i data-lucide="printer"></i></a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:24px">ຍັງບໍ່ມີປະຫວັດການຊື້</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="card">
    <h3><i data-lucide="award"></i> <span>ປະຫວັດຄະແນນ — ຄະແນນປັດຈຸບັນ: <span class="tabular"><?= number_format($viewing['points']) ?></span></span></h3>
    <table class="table">
      <tr><th>ປະເພດ</th><th class="num">ຄະແນນ</th><th>ໝາຍເຫດ</th><th>ວັນທີ</th></tr>
      <?php foreach ($points_log as $pt): ?>
      <tr>
        <td><?= ['earn'=>'<span class="badge badge-success">ໄດ້ຮັບ</span>',
                 'redeem'=>'<span class="badge badge-warning">ແລກໃຊ້</span>',
                 'adjust'=>'<span class="badge badge-info">ປັບຍອດ</span>'][$pt['type']] ?? h($pt['type']) ?></td>
        <td class="num" style="font-weight:700;color:<?= $pt['points'] >= 0 ? 'var(--success-dark)' : 'var(--danger)' ?>">
          <?= $pt['points'] > 0 ? '+' : '' ?><?= number_format($pt['points']) ?>
        </td>
        <td><?= h($pt['note'] ?: '-') ?></td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= date('d/m/Y H:i', strtotime($pt['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$points_log): ?><tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:24px">ຍັງບໍ່ມີປະຫວັດຄະແນນ</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="modal-bg" id="custModal">
  <div class="modal">
    <h3 id="cuTitle"><i data-lucide="user-plus"></i> <span>ເພີ່ມສະມາຊິກ</span></h3>
    <form method="post">
      <input type="hidden" name="act" value="save"><input type="hidden" name="id" id="cu_id">
      <div class="form-group"><label>ຊື່ສະມາຊິກ *</label><input type="text" name="name" id="cu_name" required></div>
      <div class="form-row">
        <div class="form-group"><label>ເບີໂທ</label><input type="text" name="phone" id="cu_phone"></div>
        <div class="form-group"><label>ອີເມວ</label><input type="text" name="email" id="cu_email"></div>
      </div>
      <div class="form-group"><label>ທີ່ຢູ່</label><input type="text" name="address" id="cu_address"></div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="button" class="btn btn-secondary btn-block" onclick="document.getElementById('custModal').classList.remove('show')">ຍົກເລີກ</button>
        <button type="submit" class="btn btn-success btn-block"><i data-lucide="check"></i> ບັນທຶກ</button>
      </div>
    </form>
  </div>
</div>
<script>
function editCust(c){
  document.querySelector('#cuTitle span').textContent = c ? 'ແກ້ໄຂສະມາຊິກ' : 'ເພີ່ມສະມາຊິກ';
  document.getElementById('cu_id').value = c ? c.id : '';
  document.getElementById('cu_name').value = c ? c.name : '';
  document.getElementById('cu_phone').value = c ? (c.phone || '') : '';
  document.getElementById('cu_email').value = c ? (c.email || '') : '';
  document.getElementById('cu_address').value = c ? (c.address || '') : '';
  document.getElementById('custModal').classList.add('show');
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
