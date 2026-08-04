<?php
/**
 * settings.php - ຕັ້ງຄ່າຮ້ານ (Admin ເທົ່ານັ້ນ)
 */
require_once __DIR__ . '/config.php';
require_role(['admin']);
$page = 'settings';
$title = 'ຕັ້ງຄ່າຮ້ານ';

$keys = ['store_name', 'store_address', 'store_phone', 'tax_id', 'tax_rate',
         'point_rate', 'point_value', 'receipt_footer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?)
                         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ($keys as $k) {
        $st->execute([$k, trim($_POST[$k] ?? '')]);
    }
    $qr_image = upload_image($_FILES['qr_image'] ?? []);
    if ($qr_image) {
        $st->execute(['payment_qr', $qr_image]);
    }
    if (!empty($_POST['remove_qr'])) {
        $st->execute(['payment_qr', '']);
    }
    flash_set('ບັນທຶກການຕັ້ງຄ່າສຳເລັດ');
    header('Location: settings.php');
    exit;
}

include __DIR__ . '/header.php';
$qr_image = get_setting('payment_qr');
?>
<div class="card" style="max-width:640px">
  <form method="post" enctype="multipart/form-data">
    <div class="form-group"><label>ຊື່ຮ້ານ</label>
      <input type="text" name="store_name" value="<?= h(get_setting('store_name')) ?>"></div>
    <div class="form-group"><label>ທີ່ຢູ່ຮ້ານ</label>
      <input type="text" name="store_address" value="<?= h(get_setting('store_address')) ?>"></div>
    <div class="form-row">
      <div class="form-group"><label>ເບີໂທຮ້ານ</label>
        <input type="text" name="store_phone" value="<?= h(get_setting('store_phone')) ?>"></div>
      <div class="form-group"><label>ເລກປະຈຳຕົວຜູ້ເສຍພາສີ (ໃບກຳກັບພາສີ)</label>
        <input type="text" name="tax_id" value="<?= h(get_setting('tax_id')) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>ອັດຕາພາສີ VAT (%) — 0 = ບໍ່ຄິດພາສີ</label>
        <input type="number" name="tax_rate" step="any" min="0" value="<?= h(get_setting('tax_rate')) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>ຊື້ຄົບເທົ່າໃດໄດ້ 1 ຄະແນນ (₭)</label>
        <input type="number" name="point_rate" min="1" value="<?= h(get_setting('point_rate')) ?>"></div>
      <div class="form-group"><label>1 ຄະແນນ ແລກເປັນສ່ວນຫຼຸດ (₭)</label>
        <input type="number" name="point_value" min="0" value="<?= h(get_setting('point_value')) ?>"></div>
    </div>
    <div class="form-group"><label>ຂໍ້ຄວາມທ້າຍໃບບິນ</label>
      <input type="text" name="receipt_footer" value="<?= h(get_setting('receipt_footer')) ?>"></div>

    <div class="form-group">
      <label>QR ຮັບເງິນ (ຮູບ QR ຈາກແອັບທະນາຄານ/ມືຖືຂອງທ່ານ ເຊັ່ນ BCEL One, LDB)</label>
      <?php if ($qr_image): ?>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px">
          <img src="uploads/<?= h($qr_image) ?>" style="width:120px;height:120px;object-fit:contain;border:1px solid #e5e7eb;border-radius:10px;background:#fff">
          <label style="display:flex;align-items:center;gap:6px;font-weight:500;color:#dc2626">
            <input type="checkbox" name="remove_qr" value="1"> ລຶບຮູບ QR ນີ້
          </label>
        </div>
      <?php else: ?>
        <p style="color:#94a3b8;font-size:13px;margin-bottom:8px">ຍັງບໍ່ໄດ້ອັບໂຫຼດ QR — ຕອນຈ່າຍເງິນລູກຄ້າຈະເຫັນ QR ຕົວຢ່າງເທົ່ານັ້ນ (ບໍ່ແມ່ນບັນຊີແທ້)</p>
      <?php endif; ?>
      <input type="file" name="qr_image" accept="image/*">
    </div>

    <button class="btn btn-success">💾 ບັນທຶກການຕັ້ງຄ່າ</button>
  </form>
</div>

<div class="card" style="max-width:640px">
  <h3>🖨 ການເຊື່ອມຕໍ່ອຸປະກອນ (Hardware)</h3>
  <ul style="line-height:2;padding-left:20px;color:#475569">
    <li><strong>ເຄື່ອງສະແກນບາໂຄດ:</strong> ສຽບ USB ແລ້ວໃຊ້ໄດ້ເລີຍ — ເຄື່ອງສະແກນເຮັດວຽກຄືແປ້ນພິມ, ໜ້າຂາຍຈະໂຟກັສຊ່ອງບາໂຄດອັດຕະໂນມັດ</li>
    <li><strong>ເຄື່ອງພິມໃບບິນ (Thermal 80mm):</strong> ຕັ້ງເປັນ Default Printer ໃນ Windows, ໃບບິນຈະສັ່ງພິມອັດຕະໂນມັດຫຼັງຈ່າຍເງິນ</li>
    <li><strong>ລີ້ນຊັກເກັບເງິນ (Cash Drawer):</strong> ສຽບສາຍ RJ11 ເຂົ້າເຄື່ອງພິມ Thermal — ລີ້ນຊັກຈະເປີດອັດຕະໂນມັດເມື່ອພິມບິນ (ຕັ້ງ "Open cash drawer before printing" ໃນ Driver ຂອງເຄື່ອງພິມ)</li>
  </ul>
</div>
<?php include __DIR__ . '/footer.php'; ?>
