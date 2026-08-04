<?php
/**
 * users.php - ຈັດການພະນັກງານ / ຜູ້ໃຊ້ລະບົບ (Admin ເທົ່ານັ້ນ)
 * ສິດທິ: admin = ທຸກຢ່າງ, manager = ຫຼັງບ້ານ, cashier = ໜ້າຂາຍ
 */
require_once __DIR__ . '/config.php';
require_role(['admin']);
$page = 'users';
$title = 'ພະນັກງານ / ຜູ້ໃຊ້ລະບົບ';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username']);
        $fullname = trim($_POST['fullname']);
        $phone    = trim($_POST['phone']);
        $role     = in_array($_POST['role'], ['admin', 'manager', 'cashier']) ? $_POST['role'] : 'cashier';
        $password = $_POST['password'] ?? '';
        $status   = (int)($_POST['status'] ?? 1);

        if ($username === '' || $fullname === '') {
            flash_set('ກະລຸນາປ້ອນຂໍ້ມູນໃຫ້ຄົບ', 'danger');
        } else {
            try {
                if ($id) {
                    if ($password !== '') {
                        $pdo->prepare('UPDATE users SET username=?, fullname=?, phone=?, role=?, status=?, password=? WHERE id=?')
                            ->execute([$username, $fullname, $phone, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
                    } else {
                        $pdo->prepare('UPDATE users SET username=?, fullname=?, phone=?, role=?, status=? WHERE id=?')
                            ->execute([$username, $fullname, $phone, $role, $status, $id]);
                    }
                    flash_set('ແກ້ໄຂຜູ້ໃຊ້ສຳເລັດ');
                } else {
                    if ($password === '') $password = '123456';
                    $pdo->prepare('INSERT INTO users (username, password, fullname, phone, role, status) VALUES (?,?,?,?,?,?)')
                        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullname, $phone, $role, $status]);
                    flash_set('ເພີ່ມຜູ້ໃຊ້ສຳເລັດ');
                }
            } catch (PDOException $e) {
                flash_set($e->getCode() == 23000 ? 'ຊື່ຜູ້ໃຊ້ນີ້ມີແລ້ວ' : 'ຜິດພາດ: ' . $e->getMessage(), 'danger');
            }
        }
    }
    if ($act === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$me['id']) {
            flash_set('ບໍ່ສາມາດລຶບບັນຊີຕົນເອງໄດ້', 'danger');
        } else {
            $pdo->prepare('UPDATE users SET status = 0 WHERE id = ?')->execute([$id]);
            flash_set('ປິດການໃຊ້ງານຜູ້ໃຊ້ແລ້ວ');
        }
    }
    header('Location: users.php');
    exit;
}

$users = $pdo->query(
    'SELECT u.*,
       (SELECT COALESCE(SUM(total),0) FROM sales s WHERE s.user_id=u.id AND s.status="completed"
        AND YEAR(s.created_at)=YEAR(CURDATE()) AND MONTH(s.created_at)=MONTH(CURDATE())) month_sales
     FROM users u ORDER BY u.id')->fetchAll();

$roleLabel = ['admin' => 'ຜູ້ດູແລລະບົບ', 'manager' => 'ຜູ້ຈັດການ', 'cashier' => 'ພະນັກງານຂາຍ'];
include __DIR__ . '/header.php';
?>
<div class="toolbar">
  <button class="btn btn-success" onclick="editUser(null)">➕ ເພີ່ມພະນັກງານ</button>
</div>
<div class="card">
  <table class="table">
    <tr><th>ຊື່ຜູ້ໃຊ້</th><th>ຊື່-ນາມສະກຸນ</th><th>ເບີໂທ</th><th>ສິດທິ</th>
        <th class="num">ຍອດຂາຍເດືອນນີ້</th><th>ສະຖານະ</th><th style="width:150px">ຈັດການ</th></tr>
    <?php foreach ($users as $usr): ?>
    <tr>
      <td><strong><?= h($usr['username']) ?></strong></td>
      <td><?= h($usr['fullname']) ?></td>
      <td><?= h($usr['phone'] ?? '-') ?></td>
      <td><span class="badge badge-info"><?= $roleLabel[$usr['role']] ?></span></td>
      <td class="num"><?= money($usr['month_sales']) ?></td>
      <td><span class="badge <?= $usr['status'] ? 'badge-success' : 'badge-danger' ?>"><?= $usr['status'] ? 'ໃຊ້ງານ' : 'ປິດ' ?></span></td>
      <td>
        <button class="btn btn-sm btn-primary" onclick='editUser(<?= json_encode($usr, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>✏️</button>
        <?php if ($usr['id'] != $me['id']): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('ປິດການໃຊ້ງານຜູ້ໃຊ້ນີ້ບໍ່?')">
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $usr['id'] ?>">
          <button class="btn btn-sm btn-danger">🗑</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="modal-bg" id="userModal">
  <div class="modal">
    <h3 id="umTitle">ເພີ່ມພະນັກງານ</h3>
    <form method="post">
      <input type="hidden" name="act" value="save"><input type="hidden" name="id" id="u_id">
      <div class="form-row">
        <div class="form-group"><label>ຊື່ຜູ້ໃຊ້ (username) *</label><input type="text" name="username" id="u_username" required></div>
        <div class="form-group"><label>ລະຫັດຜ່ານ <small id="u_pwhint">(ວ່າງ = 123456)</small></label>
          <input type="password" name="password" id="u_password"></div>
      </div>
      <div class="form-group"><label>ຊື່-ນາມສະກຸນ *</label><input type="text" name="fullname" id="u_fullname" required></div>
      <div class="form-row">
        <div class="form-group"><label>ເບີໂທ</label><input type="text" name="phone" id="u_phone"></div>
        <div class="form-group"><label>ສິດທິ (Role)</label>
          <select name="role" id="u_role">
            <option value="cashier">ພະນັກງານຂາຍ (Cashier)</option>
            <option value="manager">ຜູ້ຈັດການ (Manager)</option>
            <option value="admin">ຜູ້ດູແລລະບົບ (Admin)</option>
          </select></div>
      </div>
      <div class="form-group"><label>ສະຖານະ</label>
        <select name="status" id="u_status"><option value="1">ໃຊ້ງານ</option><option value="0">ປິດ</option></select></div>
      <div style="display:flex;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-block" onclick="document.getElementById('userModal').classList.remove('show')">ປິດ</button>
        <button type="submit" class="btn btn-success btn-block">💾 ບັນທຶກ</button>
      </div>
    </form>
  </div>
</div>
<script>
function editUser(u){
  document.getElementById('umTitle').textContent = u ? '✏️ ແກ້ໄຂພະນັກງານ' : '➕ ເພີ່ມພະນັກງານ';
  document.getElementById('u_id').value = u ? u.id : '';
  document.getElementById('u_username').value = u ? u.username : '';
  document.getElementById('u_fullname').value = u ? u.fullname : '';
  document.getElementById('u_phone').value = u ? (u.phone || '') : '';
  document.getElementById('u_role').value = u ? u.role : 'cashier';
  document.getElementById('u_status').value = u ? u.status : 1;
  document.getElementById('u_password').value = '';
  document.getElementById('u_pwhint').textContent = u ? '(ວ່າງ = ບໍ່ປ່ຽນ)' : '(ວ່າງ = 123456)';
  document.getElementById('userModal').classList.add('show');
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
