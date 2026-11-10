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
<div class="grid grid-4" style="margin-bottom: 22px;">
  <div class="stat-card">
    <div class="icon" style="color:var(--emerald); background:var(--emerald-subtle);"><i data-lucide="banknote"></i></div>
    <div>
      <div class="value tabular"><?= money($today_sales['t']) ?></div>
      <div class="label">ຍອດຂາຍມື້ນີ້ (<?= $today_sales['c'] ?> ບິນ)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="icon" style="color:var(--primary); background:var(--primary-50);"><i data-lucide="calendar"></i></div>
    <div>
      <div class="value tabular"><?= money($month_sales['t']) ?></div>
      <div class="label">ຍອດຂາຍເດືອນນີ້</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="icon" style="color:#6366f1; background:#eef2ff;"><i data-lucide="package"></i></div>
    <div>
      <div class="value tabular"><?= number_format($product_count) ?></div>
      <div class="label">ສິນຄ້າທັງໝົດໃນລະບົບ</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="icon" style="color:<?= $low_stock ? 'var(--danger)' : 'var(--success)' ?>; background:<?= $low_stock ? 'var(--danger-50)' : 'var(--success-50)' ?>;">
      <i data-lucide="<?= $low_stock ? 'alert-triangle' : 'check-circle' ?>"></i>
    </div>
    <div>
      <div class="value tabular" style="color:<?= $low_stock ? 'var(--danger)' : 'var(--success)' ?>"><?= count($low_stock) ?></div>
      <div class="label">ສິນຄ້າໃກ້ໝົດສະຕ໋ອກ</div>
    </div>
  </div>
</div>

<div class="grid grid-2" style="margin-bottom: 22px;">
  <div class="card">
    <h3 style="display:flex; align-items:center; gap:8px;">
      <i data-lucide="trending-up" style="width:18px; height:18px; color:var(--primary);"></i>
      <span>ຍອດຂາຍ 7 ວັນຫຼ້າສຸດ</span>
    </h3>
    <canvas id="salesChart" height="210"></canvas>
  </div>
  <div class="card">
    <h3 style="display:flex; align-items:center; gap:8px;">
      <i data-lucide="award" style="width:18px; height:18px; color:var(--warning);"></i>
      <span>ສິນຄ້າຂາຍດີເດືອນນີ້</span>
    </h3>
    <table class="table">
      <tr><th>ສິນຄ້າ</th><th style="text-align:right">ຈຳນວນ</th><th style="text-align:right">ຍອດຂາຍ</th></tr>
      <?php foreach ($top as $t): ?>
      <tr>
        <td style="font-weight:600;"><?= h($t['product_name']) ?></td>
        <td class="tabular" style="text-align:right; font-weight:600;"><?= number_format($t['qty']) ?></td>
        <td class="tabular" style="text-align:right; font-weight:700; color:var(--text);"><?= money($t['total']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$top): ?><tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:30px;">ຍັງບໍ່ມີຂໍ້ມູນການຂາຍໃນເດືອນນີ້</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<?php if ($low_stock): ?>
<div class="card" style="border-color:#fecdd3;">
  <h3 style="color:var(--danger); display:flex; align-items:center; gap:8px;">
    <i data-lucide="alert-triangle" style="width:18px; height:18px;"></i>
    <span>ແຈ້ງເຕືອນ: ສິນຄ້າໃກ້ໝົດ / ໝົດສະຕ໋ອກ — ຄວນສັ່ງເພີ່ມ</span>
  </h3>
  <table class="table">
    <tr><th>ບາໂຄດ</th><th>ສິນຄ້າ</th><th style="text-align:right">ຄົງເຫຼືອ</th><th style="text-align:right">ຂັ້ນຕໍ່າ</th><th></th></tr>
    <?php foreach ($low_stock as $p): ?>
    <tr>
      <td style="font-family:var(--font-mono, monospace); font-size:12.5px;"><?= h($p['barcode']) ?></td>
      <td style="font-weight:600;"><?= h($p['name']) ?></td>
      <td class="tabular" style="text-align:right;">
        <span class="badge <?= $p['stock_qty'] <= 0 ? 'badge-danger' : 'badge-warning' ?>"><?= $p['stock_qty'] ?></span>
      </td>
      <td class="tabular" style="text-align:right; color:var(--text-muted);"><?= $p['min_stock'] ?></td>
      <td style="text-align:right;"><a href="purchases.php" class="btn btn-sm btn-primary">ຮັບເຂົ້າສາງ</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="display:flex; align-items:center; gap:8px;">
    <i data-lucide="receipt" style="width:18px; height:18px; color:var(--primary);"></i>
    <span>ບິນຫຼ້າສຸດ</span>
  </h3>
  <table class="table">
    <tr><th>ເລກບິນ</th><th>ພະນັກງານ</th><th style="text-align:right">ຍອດລວມ</th><th>ຊຳລະດ້ວຍ</th><th>ສະຖານະ</th><th>ວັນທີ</th><th style="text-align:right">ພິມ</th></tr>
    <?php foreach ($recent as $s): ?>
    <tr>
      <td style="font-weight:700; font-family:var(--font-mono, monospace); font-size:13px;"><?= h($s['invoice_no']) ?></td>
      <td><?= h($s['fullname']) ?></td>
      <td class="tabular" style="text-align:right; font-weight:700;"><?= money($s['total']) ?></td>
      <td>
        <?php
          $pm = $s['payment_method'];
          if ($pm === 'cash') echo '<span class="badge" style="background:#f1f5f9; color:#334155;">ເງິນສົດ</span>';
          elseif ($pm === 'qr') echo '<span class="badge badge-info">BCEL QR</span>';
          elseif ($pm === 'card') echo '<span class="badge" style="background:#f5f3ff; color:#6d28d9;">ບັດ</span>';
          else echo '-';
        ?>
      </td>
      <td><?php
        $badge = ['completed'=>['badge-success','ສຳເລັດ'],'held'=>['badge-warning','ພັກບິນ'],'cancelled'=>['badge-danger','ຍົກເລີກ']][$s['status']] ?? ['badge-info', $s['status']];
        echo '<span class="badge '.$badge[0].'">'.$badge[1].'</span>';
      ?></td>
      <td style="color:var(--text-muted); font-size:12.5px;"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
      <td style="text-align:right;">
        <a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="ພິມໃບບິນ">
          <i data-lucide="printer" style="width:14px; height:14px;"></i>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?>
    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">ຍັງບໍ່ມີປະຫວັດການຂາຍ</td></tr>
    <?php endif; ?>
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
      backgroundColor: '#0284c7',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function(context) {
            return new Intl.NumberFormat('lo-LA').format(context.raw) + ' ₭';
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#f1f5f9' },
        ticks: {
          callback: function(val) {
            return new Intl.NumberFormat('lo-LA').format(val) + ' ₭';
          }
        }
      },
      x: { grid: { display: false } }
    }
  }
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
