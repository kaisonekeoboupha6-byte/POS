<?php
/**
 * dashboard.php - ສະຫຼຸບຍອດຂາຍ + ກຣາຟ (Reporting & Analytics)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'dashboard';
$title = 'ໜ້າຫຼັກ (Dashboard)';

// ຕົວເລກສະຫຼຸບ
$today_sales = $pdo->query(
    "SELECT COALESCE(SUM(total),0) t, COUNT(*) c FROM sales
     WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetch();
$month_sales = $pdo->query(
    "SELECT COALESCE(SUM(total),0) t FROM sales
     WHERE status='completed' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetch();
$product_count = $pdo->query('SELECT COUNT(*) c FROM products WHERE status=1')->fetch()['c'];
$low_stock = $pdo->query(
    'SELECT * FROM products WHERE status=1 AND stock_qty <= min_stock ORDER BY stock_qty ASC')->fetchAll();
$customer_count = $pdo->query('SELECT COUNT(*) c FROM customers')->fetch()['c'];

// ຍອດຂາຍ 7 ວັນຫຼ້າສຸດ
$chart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart[$d] = 0;
}
$rows = $pdo->query(
    "SELECT DATE(created_at) d, SUM(total) t FROM sales
     WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)")->fetchAll();
foreach ($rows as $r) $chart[$r['d']] = (float)$r['t'];

// ສິນຄ້າຂາຍດີ 5 ອັນດັບ (ເດືອນນີ້)
$top = $pdo->query(
    "SELECT si.product_name, SUM(si.qty) qty, SUM(si.total) total
     FROM sale_items si JOIN sales s ON s.id = si.sale_id
     WHERE s.status='completed' AND YEAR(s.created_at)=YEAR(CURDATE()) AND MONTH(s.created_at)=MONTH(CURDATE())
     GROUP BY si.product_name ORDER BY qty DESC LIMIT 5")->fetchAll();

// ບິນຫຼ້າສຸດ
$recent = $pdo->query(
    "SELECT s.*, u.fullname FROM sales s JOIN users u ON u.id=s.user_id
     ORDER BY s.id DESC LIMIT 8")->fetchAll();

include __DIR__ . '/header.php';
?>
<div class="grid grid-4">
  <div class="card stat-card"><span class="icon">💰</span>
    <div><div class="value"><?= money($today_sales['t']) ?></div><div class="label">ຍອດຂາຍມື້ນີ້ (<?= $today_sales['c'] ?> ບິນ)</div></div></div>
  <div class="card stat-card"><span class="icon">📅</span>
    <div><div class="value"><?= money($month_sales['t']) ?></div><div class="label">ຍອດຂາຍເດືອນນີ້</div></div></div>
  <div class="card stat-card"><span class="icon">📦</span>
    <div><div class="value"><?= number_format($product_count) ?></div><div class="label">ສິນຄ້າທັງໝົດ</div></div></div>
  <div class="card stat-card"><span class="icon">⚠️</span>
    <div><div class="value" style="color:<?= $low_stock ? '#dc2626' : '#16a34a' ?>"><?= count($low_stock) ?></div>
    <div class="label">ສິນຄ້າໃກ້ໝົດສະຕ໋ອກ</div></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>📈 ຍອດຂາຍ 7 ວັນຫຼ້າສຸດ</h3>
    <canvas id="salesChart" height="220"></canvas>
  </div>
  <div class="card">
    <h3>🏆 ສິນຄ້າຂາຍດີເດືອນນີ້</h3>
    <table class="table">
      <tr><th>ສິນຄ້າ</th><th class="num">ຈຳນວນຂາຍ</th><th class="num">ຍອດເງິນ</th></tr>
      <?php foreach ($top as $t): ?>
      <tr><td><?= h($t['product_name']) ?></td><td class="num"><?= number_format($t['qty']) ?></td>
          <td class="num"><?= money($t['total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$top): ?><tr><td colspan="3" style="color:#94a3b8">ຍັງບໍ່ມີຂໍ້ມູນການຂາຍ</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<?php if ($low_stock): ?>
<div class="card">
  <h3 style="color:#dc2626">⚠️ ແຈ້ງເຕືອນ: ສິນຄ້າໃກ້ໝົດ / ໝົດສະຕ໋ອກ — ຄວນສັ່ງເພີ່ມ</h3>
  <table class="table">
    <tr><th>ບາໂຄດ</th><th>ສິນຄ້າ</th><th class="num">ຄົງເຫຼືອ</th><th class="num">ຂັ້ນຕໍ່າ</th><th></th></tr>
    <?php foreach ($low_stock as $p): ?>
    <tr>
      <td><?= h($p['barcode']) ?></td><td><?= h($p['name']) ?></td>
      <td class="num"><span class="badge <?= $p['stock_qty'] <= 0 ? 'badge-danger' : 'badge-warning' ?>"><?= $p['stock_qty'] ?></span></td>
      <td class="num"><?= $p['min_stock'] ?></td>
      <td><a href="purchases.php" class="btn btn-sm btn-primary">ຮັບເຂົ້າສາງ</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h3>🧾 ບິນຫຼ້າສຸດ</h3>
  <table class="table">
    <tr><th>ເລກບິນ</th><th>ພະນັກງານ</th><th class="num">ຍອດ</th><th>ຈ່າຍ</th><th>ສະຖານະ</th><th>ວັນທີ</th><th></th></tr>
    <?php foreach ($recent as $s): ?>
    <tr>
      <td><?= h($s['invoice_no']) ?></td>
      <td><?= h($s['fullname']) ?></td>
      <td class="num"><?= money($s['total']) ?></td>
      <td><?= ['cash'=>'💵 ສົດ','card'=>'💳 ບັດ','qr'=>'📱 QR'][$s['payment_method']] ?? '-' ?></td>
      <td><?php
        $badge = ['completed'=>['badge-success','ສຳເລັດ'],'held'=>['badge-warning','ພັກບິນ'],'cancelled'=>['badge-danger','ຍົກເລີກ']][$s['status']];
        echo '<span class="badge '.$badge[0].'">'.$badge[1].'</span>';
      ?></td>
      <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
      <td><a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm">🖨</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($chart))) ?>,
    datasets: [{
      label: 'ຍອດຂາຍ (₭)',
      data: <?= json_encode(array_values($chart)) ?>,
      backgroundColor: '#2563eb',
      borderRadius: 6,
    }]
  },
  options: { plugins: { legend: { display: false } },
             scales: { y: { beginAtZero: true } } }
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
