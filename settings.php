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
<div class="grid grid-2" style="align-items:start">
  <div class="card">
    <h3><i data-lucide="store"></i> <span>ຂໍ້ມູນຮ້ານຄ້າ & ລະບົບການຂາຍ</span></h3>
    <form method="post" enctype="multipart/form-data">
      <div class="form-group"><label>ຊື່ຮ້ານ</label>
        <input type="text" name="store_name" value="<?= h(get_setting('store_name')) ?>"></div>
      <div class="form-group"><label>ທີ່ຢູ່ຮ້ານ</label>
        <input type="text" name="store_address" value="<?= h(get_setting('store_address')) ?>"></div>
      <div class="form-row">
        <div class="form-group"><label>ເບີໂທຮ້ານ</label>
          <input type="text" name="store_phone" value="<?= h(get_setting('store_phone')) ?>"></div>
        <div class="form-group"><label>ເລກປະຈຳຕົວຜູ້ເສຍພາສີ <small>(ໃບກຳກັບພາສີ)</small></label>
          <input type="text" name="tax_id" value="<?= h(get_setting('tax_id')) ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>ອັດຕາພາສີ VAT (%) <small>— 0 = ບໍ່ຄິດພາສີ</small></label>
          <input type="number" name="tax_rate" step="any" min="0" value="<?= h(get_setting('tax_rate')) ?>" class="tabular"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>ຊື້ຄົບເທົ່າໃດໄດ້ 1 ຄະແນນ (₭)</label>
          <input type="number" name="point_rate" min="1" value="<?= h(get_setting('point_rate')) ?>" class="tabular"></div>
        <div class="form-group"><label>1 ຄະແນນ ແລກເປັນສ່ວນຫຼຸດ (₭)</label>
          <input type="number" name="point_value" min="0" value="<?= h(get_setting('point_value')) ?>" class="tabular"></div>
      </div>
      <div class="form-group"><label>ຂໍ້ຄວາມທ້າຍໃບບິນ</label>
        <input type="text" name="receipt_footer" value="<?= h(get_setting('receipt_footer')) ?>"></div>

      <div class="form-group">
        <label>QR ຮັບເງິນ <small>(ຮູບ QR ຈາກແອັບທະນາຄານ ເຊັ່ນ BCEL One, LDB)</small></label>
        <?php if ($qr_image): ?>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px">
            <img src="uploads/<?= h($qr_image) ?>" style="width:120px;height:120px;object-fit:contain;border:1.5px solid var(--border);border-radius:var(--radius);background:#fff;padding:6px">
            <label style="display:flex;align-items:center;gap:6px;font-weight:600;color:var(--danger);cursor:pointer">
              <input type="checkbox" name="remove_qr" value="1"> ລຶບຮູບ QR ນີ້
            </label>
          </div>
        <?php else: ?>
          <p style="color:var(--text-muted);font-size:13px;margin-bottom:8px">ຍັງບໍ່ໄດ້ອັບໂຫຼດ QR — ຕອນຈ່າຍເງິນລູກຄ້າຈະເຫັນ QR ຕົວຢ່າງເທົ່ານັ້ນ</p>
        <?php endif; ?>
        <input type="file" name="qr_image" accept="image/*">
      </div>

      <button class="btn btn-success"><i data-lucide="check"></i> ບັນທຶກການຕັ້ງຄ່າ</button>
    </form>
  </div>

  <div class="card">
    <h3><i data-lucide="cpu"></i> <span>ການເຊື່ອມຕໍ່ອຸປະກອນ (Hardware Guide)</span></h3>
    <div style="display:flex;flex-direction:column;gap:16px;color:var(--text-muted);font-size:13.5px">
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:var(--primary-50);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i data-lucide="scan-barcode" style="width:18px;height:18px"></i>
        </div>
        <div>
          <strong style="color:var(--text);display:block">ເຄື່ອງສະແກນບາໂຄດ (USB Barcode Scanner)</strong>
          ສຽບສາຍ USB ແລ້ວໃຊ້ໄດ້ທັນທີ — ເຄື່ອງສະແກນເຮັດວຽກຄືແປ້ນພິມ (HID Keyboard), ໜ້າຂາຍຈະໂຟກັສຊ່ອງບາໂຄດອັດຕະໂນມັດພ້ອມສຽງ Beep.
        </div>
      </div>
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:var(--success-50);color:var(--success);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i data-lucide="printer" style="width:18px;height:18px"></i>
        </div>
        <div>
          <strong style="color:var(--text);display:block">ເຄື່ອງພິມໃບບິນ (Thermal Printer 80mm)</strong>
          ຕັ້ງເປັນ Default Printer ໃນລະບົບປະຕິບັດການ, ໃບບິນຈະສັ່ງພິມອັດຕະໂນມັດທັນທີຫຼັງຢືນຢັນການຊຳລະເງິນ.
        </div>
      </div>
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:var(--warning-50);color:var(--warning);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i data-lucide="inbox" style="width:18px;height:18px"></i>
        </div>
        <div>
          <strong style="color:var(--text);display:block">ລີ້ນຊັກເກັບເງິນ (Cash Drawer RJ11)</strong>
          ສຽບສາຍ RJ11 ເຂົ້າຫຼັງເຄື່ອງພິມ Thermal — ລີ້ນຊັກຈະເດັ້ງເປີດອັດຕະໂນມັດເມື່ອພິມບິນ (ຕັ້ງ "Open cash drawer before printing" ໃນ Printer Driver).
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
