<?php
/**
 * products.php - ຈັດການສິນຄ້າ (ເພີ່ມ/ແກ້ໄຂ/ລຶບ + ອັບໂຫຼດຮູບ + ປັບສະຕ໋ອກ)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'products';
$title = 'ຈັດການສິນຄ້າ';
$u = current_user();

/* ---------- ບັນທຶກ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $barcode  = trim($_POST['barcode']) ?: (string)(time() . mt_rand(10, 99));
        $name     = trim($_POST['name']);
        $cat      = (int)$_POST['category_id'] ?: null;
        $cost     = (float)$_POST['cost_price'];
        $sell     = (float)$_POST['sell_price'];
        $stock    = (int)$_POST['stock_qty'];
        $min      = (int)$_POST['min_stock'];
        $image    = upload_image($_FILES['image'] ?? []);

        if ($name === '') {
            flash_set('ກະລຸນາປ້ອນຊື່ສິນຄ້າ', 'danger');
        } else {
            try {
                if ($id) {
                    $sql = 'UPDATE products SET barcode=?, name=?, category_id=?, cost_price=?, sell_price=?, stock_qty=?, min_stock=?' .
                           ($image ? ', image=?' : '') . ' WHERE id=?';
                    $params = [$barcode, $name, $cat, $cost, $sell, $stock, $min];
                    if ($image) $params[] = $image;
                    $params[] = $id;
                    $pdo->prepare($sql)->execute($params);
                    flash_set('ແກ້ໄຂສິນຄ້າສຳເລັດ');
                } else {
                    $pdo->prepare(
                        'INSERT INTO products (barcode, name, category_id, cost_price, sell_price, stock_qty, min_stock, image)
                         VALUES (?,?,?,?,?,?,?,?)'
                    )->execute([$barcode, $name, $cat, $cost, $sell, $stock, $min, $image]);
                    flash_set('ເພີ່ມສິນຄ້າສຳເລັດ');
                }
            } catch (PDOException $e) {
                flash_set($e->getCode() == 23000 ? 'ບາໂຄດນີ້ມີໃນລະບົບແລ້ວ' : 'ຜິດພາດ: ' . $e->getMessage(), 'danger');
            }
        }
    }

    if ($act === 'delete') {
        $pdo->prepare("UPDATE products SET status = 0, barcode = CONCAT(barcode, '_del_', id) WHERE id = ?")
            ->execute([(int)$_POST['id']]);
        flash_set('ລຶບສິນຄ້າແລ້ວ');
    }

    if ($act === 'adjust') {
        $pid = (int)$_POST['id'];
        $qty = (int)$_POST['change_qty'];
        $reason = trim($_POST['reason']) ?: 'ກວດນັບສະຕ໋ອກ';
        if ($qty != 0) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?')->execute([$qty, $pid]);
            $pdo->prepare('INSERT INTO stock_adjustments (product_id, user_id, change_qty, reason) VALUES (?,?,?,?)')
                ->execute([$pid, $u['id'], $qty, $reason]);
            $pdo->commit();
            flash_set('ປັບສະຕ໋ອກສຳເລັດ (' . ($qty > 0 ? '+' : '') . $qty . ')');
        }
    }

    header('Location: products.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

/* ---------- ດຶງຂໍ້ມູນ ---------- */
$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, c.name AS category_name FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.barcode LIKE ?)';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY p.name';
$st = $pdo->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

include __DIR__ . '/header.php';
?>
<div class="toolbar">
  <form method="get" style="display:flex;gap:8px;flex:1">
    <div style="position:relative;flex:1;max-width:360px">
      <i data-lucide="search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted)"></i>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="ຄົ້ນຫາຊື່ສິນຄ້າ ຫຼື ບາໂຄດ..." style="padding-left:38px">
    </div>
    <button class="btn btn-primary"><i data-lucide="search"></i> ຄົ້ນຫາ</button>
    <?php if ($q !== ''): ?>
      <a href="products.php" class="btn btn-secondary"><i data-lucide="x"></i> ລ້າງ</a>
    <?php endif; ?>
  </form>
  <button class="btn btn-success" onclick="editProduct(null)"><i data-lucide="plus"></i> ເພີ່ມສິນຄ້າ</button>
</div>

<div class="table-wrap">
  <table class="table">
    <tr>
      <th style="width:64px">ຮູບ</th><th>ບາໂຄດ</th><th>ຊື່ສິນຄ້າ</th><th>ໝວດໝູ່</th>
      <th class="num">ຕົ້ນທຶນ (₭)</th><th class="num">ລາຄາຂາຍ (₭)</th>
      <th class="num">ຄົງເຫຼືອ</th><th class="num">ຂັ້ນຕໍ່າ</th><th style="width:230px;text-align:right">ຈັດການ</th>
    </tr>
    <?php foreach ($products as $p): ?>
    <tr>
      <td>
        <?php if ($p['image']): ?>
          <img class="thumb" src="uploads/<?= h($p['image']) ?>" alt="<?= h($p['name']) ?>">
        <?php else: ?>
          <span class="thumb-placeholder"><i data-lucide="package"></i></span>
        <?php endif; ?>
      </td>
      <td style="font-family:var(--font-mono);font-size:13px;color:var(--text-muted)"><?= h($p['barcode']) ?></td>
      <td><strong><?= h($p['name']) ?></strong></td>
      <td><span class="badge"><?= h($p['category_name'] ?? 'ທົ່ວໄປ') ?></span></td>
      <td class="num"><?= number_format($p['cost_price']) ?></td>
      <td class="num"><strong><?= number_format($p['sell_price']) ?></strong></td>
      <td class="num">
        <span class="badge tabular <?= $p['stock_qty'] <= 0 ? 'badge-danger' : ($p['stock_qty'] <= $p['min_stock'] ? 'badge-warning' : 'badge-success') ?>">
          <?= number_format($p['stock_qty']) ?>
        </span>
      </td>
      <td class="num" style="color:var(--text-muted)"><?= number_format($p['min_stock']) ?></td>
      <td style="text-align:right">
        <button class="btn btn-sm btn-secondary" onclick='editProduct(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
          <i data-lucide="pencil"></i> ແກ້ໄຂ
        </button>
        <button class="btn btn-sm btn-warning" onclick='adjustStock(<?= (int)$p['id'] ?>, <?= json_encode($p['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
          <i data-lucide="sliders-horizontal"></i> ສະຕ໋ອກ
        </button>
        <form method="post" style="display:inline" onsubmit="return confirmAction(this, 'ລຶບສິນຄ້ານີ້ບໍ່?')">
          <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn btn-sm btn-danger" title="ລຶບສິນຄ້າ"><i data-lucide="trash-2"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <tr><td colspan="9" style="color:var(--text-muted);text-align:center;padding:32px">ບໍ່ພົບສິນຄ້າໃນລະບົບ</td></tr>
    <?php endif; ?>
  </table>
</div>

<!-- Modal ເພີ່ມ/ແກ້ໄຂສິນຄ້າ -->
<div class="modal-bg" id="productModal">
  <div class="modal">
    <h3 id="pmTitle"><i data-lucide="package-plus"></i> <span>ເພີ່ມສິນຄ້າ</span></h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="id" id="p_id">
      <div class="form-row">
        <div class="form-group"><label>ບາໂຄດ <small>(ວ່າງ = ສ້າງອັດຕະໂນມັດ)</small></label>
          <input type="text" name="barcode" id="p_barcode"></div>
        <div class="form-group"><label>ໝວດໝູ່</label>
          <select name="category_id" id="p_cat">
            <option value="0">- ບໍ່ລະບຸ -</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <div class="form-group"><label>ຊື່ສິນຄ້າ *</label>
        <input type="text" name="name" id="p_name" required></div>
      <div class="form-row">
        <div class="form-group"><label>ລາຄາຕົ້ນທຶນ (₭)</label>
          <input type="number" name="cost_price" id="p_cost" min="0" step="any" value="0" class="tabular"></div>
        <div class="form-group"><label>ລາຄາຂາຍ (₭)</label>
          <input type="number" name="sell_price" id="p_sell" min="0" step="any" value="0" class="tabular"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>ຈຳນວນຄົງຄັງ</label>
          <input type="number" name="stock_qty" id="p_stock" value="0" class="tabular"></div>
        <div class="form-group"><label>ຈຳນວນຂັ້ນຕໍ່າ <small>(ແຈ້ງເຕືອນ)</small></label>
          <input type="number" name="min_stock" id="p_min" value="5" class="tabular"></div>
      </div>
      <div class="form-group">
        <label>ຮູບສິນຄ້າ <small>(jpg, png, gif, webp)</small></label>
        <input type="file" name="image" accept="image/*" onchange="previewImg(this)">
        <img id="p_preview" style="max-width:110px;margin-top:8px;border-radius:8px;border:1px solid var(--border);display:none">
      </div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('productModal')">ຍົກເລີກ</button>
        <button type="submit" class="btn btn-success btn-block"><i data-lucide="check"></i> ບັນທຶກ</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal ປັບສະຕ໋ອກ -->
<div class="modal-bg" id="adjustModal">
  <div class="modal">
    <h3><i data-lucide="sliders-horizontal"></i> <span>ປັບ / ກວດນັບສະຕ໋ອກ — <span id="a_name"></span></span></h3>
    <form method="post">
      <input type="hidden" name="act" value="adjust">
      <input type="hidden" name="id" id="a_id">
      <div class="form-group"><label>ຈຳນວນປັບ <small>(ບວກ = ເພີ່ມ, ລົບ = ຫັກອອກ ເຊັ່ນ -3)</small></label>
        <input type="number" name="change_qty" required class="tabular"></div>
      <div class="form-group"><label>ເຫດຜົນ</label>
        <input type="text" name="reason" placeholder="ເຊັ່ນ: ກວດນັບ, ເສຍຫາຍ, ໝົດອາຍຸ"></div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('adjustModal')">ຍົກເລີກ</button>
        <button type="submit" class="btn btn-warning btn-block"><i data-lucide="check"></i> ຢືນຢັນປັບສະຕ໋ອກ</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
function editProduct(p){
  document.querySelector('#pmTitle span').textContent = p ? 'ແກ້ໄຂສິນຄ້າ' : 'ເພີ່ມສິນຄ້າ';
  document.getElementById('p_id').value      = p ? p.id : '';
  document.getElementById('p_barcode').value = p ? p.barcode : '';
  document.getElementById('p_name').value    = p ? p.name : '';
  document.getElementById('p_cat').value     = p ? (p.category_id || 0) : 0;
  document.getElementById('p_cost').value    = p ? p.cost_price : 0;
  document.getElementById('p_sell').value    = p ? p.sell_price : 0;
  document.getElementById('p_stock').value   = p ? p.stock_qty : 0;
  document.getElementById('p_min').value     = p ? p.min_stock : 5;
  const prev = document.getElementById('p_preview');
  if (p && p.image) { prev.src = 'uploads/' + p.image; prev.style.display = 'block'; }
  else prev.style.display = 'none';
  openModal('productModal');
}
function adjustStock(id, name){
  document.getElementById('a_id').value = id;
  document.getElementById('a_name').textContent = name;
  openModal('adjustModal');
}
function previewImg(input){
  if (input.files && input.files[0]) {
    const prev = document.getElementById('p_preview');
    prev.src = URL.createObjectURL(input.files[0]);
    prev.style.display = 'block';
  }
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
