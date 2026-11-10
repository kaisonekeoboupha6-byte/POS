<?php
/**
 * receipt.php - ພິມໃບບິນ (Thermal Printer 80mm)
 * ?id=<sale_id>          -> ໃບບິນທຳມະດາ
 * ?id=<sale_id>&tax=1    -> ໃບກຳກັບພາສີ (Tax Invoice)
 */
require_once __DIR__ . '/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$is_tax = !empty($_GET['tax']);

$st = $pdo->prepare(
    'SELECT s.*, u.fullname AS cashier, c.name AS customer_name, c.code AS customer_code,
            c.phone AS customer_phone, c.points AS customer_points
     FROM sales s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE s.id = ?'
);
$st->execute([$id]);
$sale = $st->fetch();
if (!$sale) die('ບໍ່ພົບບິນ');

$st = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ?');
$st->execute([$id]);
$items = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="lo">
<head>
<meta charset="UTF-8">
<title><?= $is_tax ? 'ໃບກຳກັບພາສີ' : 'ໃບບິນ' ?> <?= h($sale['invoice_no']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600;700&family=Noto+Sans+Lao+Looped:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'JetBrains Mono', 'Noto Sans Lao Looped', 'Phetsarath OT', monospace, sans-serif;
    font-size: 12.5px; width: 78mm; margin: 0 auto; padding: 10px 4px; color: #000;
    line-height: 1.4;
    font-variant-numeric: tabular-nums;
  }
  .center { text-align: center; }
  .bold { font-weight: 700; }
  h2 { font-size: 16px; font-weight: 800; margin-bottom: 2px; }
  hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 3px 0; font-size: 12px; vertical-align: top; }
  .r { text-align: right; }
  .total-row td { font-size: 15px; font-weight: 800; padding: 6px 0; }
  .no-print { text-align: center; margin: 16px 0; }
  .no-print button {
    padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
    border: none; border-radius: 6px; background: #0f172a; color: #fff; margin: 0 4px;
    transition: background 0.15s;
  }
  .no-print button:hover { background: #334155; }
  @media print { .no-print { display: none; } body { width: auto; padding: 0; } }
</style>
</head>
<body>
  <div class="center">
    <h2><?= h(get_setting('store_name', 'ຮ້ານຄ້າຕົວຢ່າງ')) ?></h2>
    <div style="font-size: 11px;"><?= h(get_setting('store_address')) ?></div>
    <div style="font-size: 11px;">ໂທ: <?= h(get_setting('store_phone')) ?></div>
    <?php if ($is_tax): ?>
      <div class="bold" style="margin-top:6px; font-size:13px;">-- ໃບກຳກັບພາສີ (TAX INVOICE) --</div>
      <div style="font-size: 11px;">ເລກປະຈຳຕົວຜູ້ເສຍພາສີ: <?= h(get_setting('tax_id')) ?></div>
    <?php else: ?>
      <div class="bold" style="margin-top:6px; font-size:13px;">-- ໃບບິນຮັບເງິນ (RECEIPT) --</div>
    <?php endif; ?>
  </div>
  <hr>
  <table>
    <tr><td>ເລກທີບິນ:</td><td class="r bold"><?= h($sale['invoice_no']) ?></td></tr>
    <tr><td>ວັນທີ:</td><td class="r"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td></tr>
    <tr><td>ພະນັກງານ:</td><td class="r"><?= h($sale['cashier']) ?></td></tr>
    <?php if ($sale['customer_name']): ?>
    <tr><td>ລູກຄ້າ:</td><td class="r"><?= h($sale['customer_name']) ?> (<?= h($sale['customer_code']) ?>)</td></tr>
    <?php endif; ?>
    <?php if ($sale['status'] === 'cancelled'): ?>
    <tr><td colspan="2" class="center bold" style="color:red; padding:4px 0;">*** ບິນນີ້ຖືກຍົກເລີກ ***</td></tr>
    <?php endif; ?>
  </table>
  <hr>
  <table>
    <tr class="bold"><th style="text-align:left">ລາຍການ</th><th class="r">ຈຳນວນ</th><th class="r">ລວມ</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= h($it['product_name']) ?><br><span style="font-size:10.5px; color:#333;"><?= number_format($it['price']) ?> ₭ x <?= $it['qty'] ?></span></td>
      <td class="r"><?= $it['qty'] ?></td>
      <td class="r bold"><?= number_format($it['total']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <tr><td>ຍອດລວມ:</td><td class="r"><?= number_format($sale['subtotal']) ?> ₭</td></tr>
    <?php if ($sale['discount_amount'] > 0): ?>
    <tr><td>ສ່ວນຫຼຸດ<?= $sale['discount_type'] === 'percent' ? ' (' . (float)$sale['discount_value'] . '%)' : '' ?>:</td>
        <td class="r">-<?= number_format($sale['discount_amount']) ?> ₭</td></tr>
    <?php endif; ?>
    <?php if ($sale['points_discount'] > 0): ?>
    <tr><td>ແລກຄະແນນ (<?= $sale['points_used'] ?> ຄະແນນ):</td>
        <td class="r">-<?= number_format($sale['points_discount']) ?> ₭</td></tr>
    <?php endif; ?>
    <?php if ($sale['tax_amount'] > 0 || $is_tax): ?>
    <tr><td>ພາສີມູນຄ່າເພີ່ມ (<?= (float)$sale['tax_rate'] ?>%):</td>
        <td class="r"><?= number_format($sale['tax_amount']) ?> ₭</td></tr>
    <?php endif; ?>
    <tr class="total-row"><td>ຍອດຊຳລະສຸດທິ:</td><td class="r"><?= number_format($sale['total']) ?> ₭</td></tr>
    <?php if ($sale['status'] === 'completed'): ?>
    <tr><td>ວິທີຊຳລະ:</td><td class="r bold"><?php
      echo ['cash' => 'ເງິນສົດ (CASH)', 'card' => 'ບັດ (CARD)', 'qr' => 'BCEL ONE / QR'][$sale['payment_method']] ?? '-';
    ?></td></tr>
    <tr><td>ຮັບເງິນ:</td><td class="r"><?= number_format($sale['paid_amount']) ?> ₭</td></tr>
    <tr><td>ເງິນທອນ:</td><td class="r bold"><?= number_format($sale['change_amount']) ?> ₭</td></tr>
    <?php endif; ?>
    <?php if ($sale['points_earned'] > 0): ?>
    <tr><td>ຄະແນນທີ່ໄດ້ຮັບ:</td><td class="r bold">+<?= $sale['points_earned'] ?></td></tr>
    <?php endif; ?>
  </table>
  <hr>
  <div class="center" style="font-size:12px; margin-top:4px;"><?= h(get_setting('receipt_footer', 'ຂອບໃຈທີ່ອຸດໜູນ')) ?></div>
  <div class="center" style="font-size:10px; margin-top:2px;">================================</div>

  <div class="no-print">
    <button onclick="window.print()">ພິມໃບບິນ</button>
    <?php if (!$is_tax): ?>
    <button onclick="location.href='receipt.php?id=<?= $id ?>&tax=1'">ໃບກຳກັບພາສີ</button>
    <?php endif; ?>
    <button onclick="window.close()" style="background:#64748b">ປິດ</button>
  </div>
  <script>window.onload = () => setTimeout(() => window.print(), 300);</script>
</body>
</html>
