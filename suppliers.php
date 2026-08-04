<?php
/**
 * suppliers.php - ຈັດການຜູ້ສະໜອງສິນຄ້າ
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'suppliers';
$title = 'ຜູ້ສະໜອງສິນຄ້າ (Supplier)';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']), trim($_POST['address'])];
        if ($data[0] !== '') {
            if ($id) {
                $pdo->prepare('UPDATE suppliers SET name=?, phone=?, email=?, address=? WHERE id=?')
                    ->execute([...$data, $id]);
                flash_set('ແກ້ໄຂຜູ້ສະໜອງສຳເລັດ');
            } else {
                $pdo->prepare('INSERT INTO suppliers (name, phone, email, address) VALUES (?,?,?,?)')->execute($data);
                flash_set('ເພີ່ມຜູ້ສະໜອງສຳເລັດ');
            }
        }
    }
    if ($act === 'delete') {
        $pdo->prepare('DELETE FROM suppliers WHERE id=?')->execute([(int)$_POST['id']]);
        flash_set('ລຶບຜູ້ສະໜອງແລ້ວ');
    }
    header('Location: suppliers.php');
    exit;
}

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
include __DIR__ . '/header.php';
?>
<div class="toolbar">
  <button class="btn btn-success" onclick="editSup(null)">➕ ເພີ່ມຜູ້ສະໜອງ</button>
</div>
<div class="card">
  <table class="table">
    <tr><th>ຊື່</th><th>ເບີໂທ</th><th>ອີເມວ</th><th>ທີ່ຢູ່</th><th style="width:160px">ຈັດການ</th></tr>
    <?php foreach ($suppliers as $s): ?>
    <tr>
      <td><strong><?= h($s['name']) ?></strong></td>
      <td><?= h($s['phone'] ?? '-') ?></td>
      <td><?= h($s['email'] ?? '-') ?></td>
      <td><?= h($s['address'] ?? '-') ?></td>
      <td>
        <button class="btn btn-sm btn-primary" onclick='editSup(<?= json_encode($s, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>✏️</button>
        <form method="post" style="display:inline" onsubmit="return confirm('ລຶບຜູ້ສະໜອງນີ້ບໍ່?')">
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
          <button class="btn btn-sm btn-danger">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="modal-bg" id="supModal">
  <div class="modal">
    <h3 id="smTitle">ເພີ່ມຜູ້ສະໜອງ</h3>
    <form method="post">
      <input type="hidden" name="act" value="save"><input type="hidden" name="id" id="s_id">
      <div class="form-group"><label>ຊື່ຜູ້ສະໜອງ *</label><input type="text" name="name" id="s_name" required></div>
      <div class="form-row">
        <div class="form-group"><label>ເບີໂທ</label><input type="text" name="phone" id="s_phone"></div>
        <div class="form-group"><label>ອີເມວ</label><input type="text" name="email" id="s_email"></div>
      </div>
      <div class="form-group"><label>ທີ່ຢູ່</label><input type="text" name="address" id="s_address"></div>
      <div style="display:flex;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-block" onclick="document.getElementById('supModal').classList.remove('show')">ປິດ</button>
        <button type="submit" class="btn btn-success btn-block">💾 ບັນທຶກ</button>
      </div>
    </form>
  </div>
</div>
<script>
function editSup(s){
  document.getElementById('smTitle').textContent = s ? '✏️ ແກ້ໄຂຜູ້ສະໜອງ' : '➕ ເພີ່ມຜູ້ສະໜອງ';
  document.getElementById('s_id').value = s ? s.id : '';
  document.getElementById('s_name').value = s ? s.name : '';
  document.getElementById('s_phone').value = s ? (s.phone || '') : '';
  document.getElementById('s_email').value = s ? (s.email || '') : '';
  document.getElementById('s_address').value = s ? (s.address || '') : '';
  document.getElementById('supModal').classList.add('show');
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
