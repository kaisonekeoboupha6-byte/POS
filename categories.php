<?php
/**
 * categories.php - ຈັດການໝວດໝູ່ສິນຄ້າ (ເພີ່ມ/ແກ້ໄຂ/ລຶບ)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'categories';
$title = 'ໝວດໝູ່ສິນຄ້າ';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        if ($name !== '') {
            if ($id) {
                $pdo->prepare('UPDATE categories SET name=?, description=? WHERE id=?')->execute([$name, $desc, $id]);
                flash_set('ແກ້ໄຂໝວດໝູ່ສຳເລັດ');
            } else {
                $pdo->prepare('INSERT INTO categories (name, description) VALUES (?,?)')->execute([$name, $desc]);
                flash_set('ເພີ່ມໝວດໝູ່ສຳເລັດ');
            }
        }
    }
    if ($act === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([(int)$_POST['id']]);
        flash_set('ລຶບໝວດໝູ່ແລ້ວ (ສິນຄ້າໃນໝວດຈະກາຍເປັນ "ບໍ່ລະບຸ")');
    }
    header('Location: categories.php');
    exit;
}

$cats = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status=1) cnt
     FROM categories c ORDER BY c.name')->fetchAll();

include __DIR__ . '/header.php';
?>
<div class="toolbar">
  <button class="btn btn-success" onclick="editCat(null)">➕ ເພີ່ມໝວດໝູ່</button>
</div>
<div class="card">
  <table class="table">
    <tr><th>ຊື່ໝວດໝູ່</th><th>ຄຳອະທິບາຍ</th><th class="num">ຈຳນວນສິນຄ້າ</th><th style="width:160px">ຈັດການ</th></tr>
    <?php foreach ($cats as $c): ?>
    <tr>
      <td><strong><?= h($c['name']) ?></strong></td>
      <td><?= h($c['description'] ?? '-') ?></td>
      <td class="num"><?= $c['cnt'] ?></td>
      <td>
        <button class="btn btn-sm btn-primary" onclick='editCat(<?= json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>✏️</button>
        <form method="post" style="display:inline" onsubmit="return confirm('ລຶບໝວດໝູ່ນີ້ບໍ່?')">
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn btn-sm btn-danger">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="modal-bg" id="catModal">
  <div class="modal">
    <h3 id="cmTitle">ເພີ່ມໝວດໝູ່</h3>
    <form method="post">
      <input type="hidden" name="act" value="save"><input type="hidden" name="id" id="c_id">
      <div class="form-group"><label>ຊື່ໝວດໝູ່ *</label><input type="text" name="name" id="c_name" required></div>
      <div class="form-group"><label>ຄຳອະທິບາຍ</label><input type="text" name="description" id="c_desc"></div>
      <div style="display:flex;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-block" onclick="document.getElementById('catModal').classList.remove('show')">ປິດ</button>
        <button type="submit" class="btn btn-success btn-block">💾 ບັນທຶກ</button>
      </div>
    </form>
  </div>
</div>
<script>
function editCat(c){
  document.getElementById('cmTitle').textContent = c ? '✏️ ແກ້ໄຂໝວດໝູ່' : '➕ ເພີ່ມໝວດໝູ່';
  document.getElementById('c_id').value = c ? c.id : '';
  document.getElementById('c_name').value = c ? c.name : '';
  document.getElementById('c_desc').value = c ? (c.description || '') : '';
  document.getElementById('catModal').classList.add('show');
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
