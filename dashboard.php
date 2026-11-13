<?php
/**
 * dashboard.php - ສະຫຼຸບຍອດຂາຍ + ກຣາຟ (Reporting & Analytics)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$page = 'dashboard';
$title = 'ໜ້າຫຼັກ (Dashboard)';
$u = current_user();

// ຕົວເລກສະຫຼຸບ (SARGable index range + ລວມຍອດມື້ນີ້/ເດືອນນີ້ໃນ 1 query)
$todayStart     = date('Y-m-d 00:00:00');
$tomorrowStart  = date('Y-m-d 00:00:00', strtotime('+1 day'));
$monthStart     = date('Y-m-01 00:00:00');
$nextMonthStart = date('Y-m-01 00:00:00', strtotime('first day of next month'));
$weekStart      = date('Y-m-d 00:00:00', strtotime('-6 days'));

$stSummary = $pdo->prepare(
    "SELECT
       COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN total ELSE 0 END), 0) AS today_t,
       SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS today_c,
       COALESCE(SUM(total), 0) AS month_t
     FROM sales
     WHERE status = 'completed' AND created_at >= ? AND created_at < ?"
);
$stSummary->execute([$todayStart, $tomorrowStart, $todayStart, $tomorrowStart, $monthStart, $nextMonthStart]);
$sumRow = $stSummary->fetch();
$today_sales = ['t' => (float)$sumRow['today_t'], 'c' => (int)$sumRow['today_c']];
$month_sales = ['t' => (float)$sumRow['month_t']];

$product_count = $pdo->query('SELECT COUNT(*) FROM products WHERE status=1')->fetchColumn();
$low_stock = $pdo->query(
    'SELECT id, barcode, name, stock_qty, min_stock FROM products WHERE status=1 AND stock_qty <= min_stock ORDER BY stock_qty ASC'
)->fetchAll();
$precomputed_low_stock_count = count($low_stock);
$customer_count = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();

// ຍອດຂາຍ 7 ວັນຫຼ້າສຸດ (SARGable range)
$chart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart[$d] = 0;
}
$stChart = $pdo->prepare(
    "SELECT DATE(created_at) d, SUM(total) t FROM sales
     WHERE status='completed' AND created_at >= ? AND created_at < ?
     GROUP BY DATE(created_at)"
);
$stChart->execute([$weekStart, $tomorrowStart]);
foreach ($stChart->fetchAll() as $r) $chart[$r['d']] = (float)$r['t'];
$week_total = array_sum($chart);

// ສິນຄ້າຂາຍດີ 5 ອັນດັບ (ເດືອນນີ້, SARGable range)
$stTop = $pdo->prepare(
    "SELECT si.product_name, SUM(si.qty) qty, SUM(si.total) total
     FROM sale_items si JOIN sales s ON s.id = si.sale_id
     WHERE s.status='completed' AND s.created_at >= ? AND s.created_at < ?
     GROUP BY si.product_name ORDER BY qty DESC LIMIT 5"
);
$stTop->execute([$monthStart, $nextMonthStart]);
$top = $stTop->fetchAll();
$max_top_qty = $top ? max(array_map(fn($x) => (int)$x['qty'], $top)) : 1;

// ບິນຫຼ້າສຸດ
$recent = $pdo->query(
    "SELECT s.id, s.invoice_no, s.total, s.payment_method, s.status, s.created_at, u.fullname
     FROM sales s JOIN users u ON u.id=s.user_id
     ORDER BY s.id DESC LIMIT 8"
)->fetchAll();

include __DIR__ . '/header.php';
?>
<div class="dash-welcome">
  <div>
    <h2>ສະບາຍດີ, <?= h($u['fullname']) ?></h2>
    <p>ພາບລວມຮ້ານຄ້າປະຈຳວັນທີ <strong class="tabular" style="color:var(--text)"><?= date('d/m/Y') ?></strong> • ສະມາຊິກທັງໝົດ <strong class="tabular" style="color:var(--text)"><?= number_format($customer_count) ?></strong> ຄົນ</p>
  </div>
  <div class="dash-quick-actions">
    <a href="pos.php" class="btn btn-success"><i data-lucide="shopping-cart" aria-hidden="true"></i> <span>ເປີດໜ້າຂາຍ POS</span></a>
    <a href="products.php" class="btn btn-secondary"><i data-lucide="package" aria-hidden="true"></i> <span>ຈັດການສິນຄ້າ</span></a>
    <a href="reports.php" class="btn btn-secondary"><i data-lucide="bar-chart-3" aria-hidden="true"></i> <span>ລາຍງານ</span></a>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom: 22px;">
  <div class="stat-card" style="--stat-accent: var(--emerald);">
    <div class="icon" style="color:var(--emerald); background:var(--emerald-subtle);"><i data-lucide="banknote" aria-hidden="true"></i></div>
    <div>
      <div class="value tabular"><?= money($today_sales['t']) ?></div>
      <div class="label">ຍອດຂາຍມື້ນີ້ (<?= number_format($today_sales['c']) ?> ບິນ)</div>
    </div>
  </div>
  <div class="stat-card" style="--stat-accent: var(--primary);">
    <div class="icon" style="color:var(--primary); background:var(--primary-50);"><i data-lucide="calendar" aria-hidden="true"></i></div>
    <div>
      <div class="value tabular"><?= money($month_sales['t']) ?></div>
      <div class="label">ຍອດຂາຍເດືອນນີ້</div>
    </div>
  </div>
  <div class="stat-card" style="--stat-accent: var(--indigo);">
    <div class="icon" style="color:var(--indigo); background:var(--indigo-50);"><i data-lucide="package" aria-hidden="true"></i></div>
    <div>
      <div class="value tabular"><?= number_format($product_count) ?></div>
      <div class="label">ສິນຄ້າທັງໝົດໃນລະບົບ</div>
    </div>
  </div>
  <div class="stat-card" style="--stat-accent: <?= $low_stock ? 'var(--danger)' : 'var(--success)' ?>;">
    <div class="icon" style="color:<?= $low_stock ? 'var(--danger)' : 'var(--success)' ?>; background:<?= $low_stock ? 'var(--danger-50)' : 'var(--success-50)' ?>;">
      <i data-lucide="<?= $low_stock ? 'alert-triangle' : 'check-circle' ?>" aria-hidden="true"></i>
    </div>
    <div>
      <div class="value tabular" style="color:<?= $low_stock ? 'var(--danger)' : 'var(--success)' ?>"><?= count($low_stock) ?></div>
      <div class="label">ສິນຄ້າໃກ້ໝົດສະຕ໋ອກ</div>
    </div>
  </div>
</div>

<div class="dash-analytics-grid">
  <div class="card" style="margin-bottom:0;">
    <div class="card-head">
      <h3>
        <i data-lucide="trending-up" aria-hidden="true"></i>
        <span>ຍອດຂາຍ 7 ວັນຫຼ້າສຸດ</span>
      </h3>
      <span class="badge badge-info tabular">ລວມ 7 ວັນ: <?= money($week_total) ?></span>
    </div>
    <div style="position:relative; min-height:240px;">
      <canvas id="salesChart" height="190"></canvas>
    </div>
  </div>

  <div class="card" style="margin-bottom:0;">
    <div class="card-head">
      <h3>
        <i data-lucide="award" aria-hidden="true" style="color:var(--warning);"></i>
        <span>ສິນຄ້າຂາຍດີເດືອນນີ້</span>
      </h3>
      <span class="badge">Top 5</span>
    </div>
    <?php if ($top): ?>
    <div class="top-seller-list">
      <?php foreach ($top as $idx => $t):
        $pct = max(6, min(100, round(((int)$t['qty'] / max(1, $max_top_qty)) * 100)));
      ?>
      <div class="top-seller-item">
        <div class="top-seller-row">
          <div style="display:flex;align-items:center;gap:8px;min-width:0;">
            <span class="rank-badge <?= $idx === 0 ? 'top-1' : '' ?>">#<?= $idx + 1 ?></span>
            <span style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($t['product_name']) ?></span>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <strong class="tabular" style="font-size:13.5px;"><?= money($t['total']) ?></strong>
            <span class="tabular" style="display:block;font-size:11.5px;color:var(--text-muted);"><?= number_format($t['qty']) ?> ຊິ້ນ</span>
          </div>
        </div>
        <div class="progress-track">
          <div class="progress-fill" style="width: <?= $pct ?>%;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;color:var(--text-muted);padding:48px 16px;">
      <i data-lucide="inbox" aria-hidden="true" style="width:32px;height:32px;opacity:0.45;margin-bottom:8px;"></i>
      <p>ຍັງບໍ່ມີຂໍ້ມູນການຂາຍໃນເດືອນນີ້</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($low_stock): ?>
<div class="card" id="low-stock" style="border-color:var(--danger-border);">
  <div class="card-head">
    <h3 style="color:var(--danger);">
      <i data-lucide="alert-triangle" aria-hidden="true" style="color:var(--danger);"></i>
      <span>ແຈ້ງເຕືອນ: ສິນຄ້າໃກ້ໝົດ / ໝົດສະຕ໋ອກ — ຄວນສັ່ງເພີ່ມ</span>
    </h3>
    <span class="badge badge-danger tabular"><?= count($low_stock) ?> ລາຍການ</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>ບາໂຄດ</th><th>ສິນຄ້າ</th><th class="num">ຄົງເຫຼືອ</th><th class="num">ຂັ້ນຕໍ່າ</th><th style="text-align:right">ຈັດການ</th></tr>
      <?php foreach ($low_stock as $p): ?>
      <tr>
        <td style="font-family:var(--font-mono); font-size:12.5px;"><?= h($p['barcode']) ?></td>
        <td style="font-weight:600;"><?= h($p['name']) ?></td>
        <td class="num">
          <span class="badge <?= $p['stock_qty'] <= 0 ? 'badge-danger' : 'badge-warning' ?>"><?= (int)$p['stock_qty'] ?></span>
        </td>
        <td class="num" style="color:var(--text-muted);"><?= (int)$p['min_stock'] ?></td>
        <td style="text-align:right;"><a href="purchases.php" class="btn btn-sm btn-primary"><i data-lucide="arrow-down-left" aria-hidden="true"></i> ຮັບເຂົ້າສາງ</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h3>
      <i data-lucide="receipt" aria-hidden="true"></i>
      <span>ບິນຫຼ້າສຸດ</span>
    </h3>
    <a href="reports.php?type=sales" class="btn btn-sm btn-secondary">ເບິ່ງທັງໝົດ</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>ເລກບິນ</th><th>ພະນັກງານ</th><th class="num">ຍອດລວມ</th><th>ຊຳລະດ້ວຍ</th><th>ສະຖານະ</th><th>ວັນທີ</th><th style="text-align:right">ພິມ</th></tr>
      <?php foreach ($recent as $s):
        $empInit = strtoupper(mb_substr(trim($s['fullname'] ?: 'U'), 0, 1, 'UTF-8'));
      ?>
      <tr>
        <td style="font-weight:700; font-family:var(--font-mono); font-size:13px;"><?= h($s['invoice_no']) ?></td>
        <td>
          <span class="avatar-pill">
            <span class="avatar-initials" aria-hidden="true"><?= h($empInit) ?></span>
            <span><?= h($s['fullname']) ?></span>
          </span>
        </td>
        <td class="num" style="font-weight:700;"><?= money($s['total']) ?></td>
        <td>
          <?php
            $pm = $s['payment_method'];
            if ($pm === 'cash') echo '<span class="badge">ເງິນສົດ</span>';
            elseif ($pm === 'qr') echo '<span class="badge badge-info">BCEL QR</span>';
            elseif ($pm === 'card') echo '<span class="badge badge-indigo">ບັດ</span>';
            else echo '-';
          ?>
        </td>
        <td><?php
          $badge = ['completed'=>['badge-success','ສຳເລັດ'],'held'=>['badge-warning','ພັກບິນ'],'cancelled'=>['badge-danger','ຍົກເລີກ']][$s['status']] ?? ['badge-info', $s['status']];
          echo '<span class="badge '.$badge[0].'">'.$badge[1].'</span>';
        ?></td>
        <td style="color:var(--text-muted); font-size:12.5px;"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
        <td style="text-align:right;">
          <a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="ພິມໃບບິນ" aria-label="ພິມໃບບິນ <?= h($s['invoice_no']) ?>">
            <i data-lucide="printer" aria-hidden="true"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
      <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px;">ຍັງບໍ່ມີປະຫວັດການຂາຍ</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

<script src="assets/vendor/chart.umd.min.js?v=<?= asset_v('assets/vendor/chart.umd.min.js') ?>" defer></script>
<script>
(function () {
  function initDashboardChart() {
    var canvas = document.getElementById('salesChart');
    if (!canvas || typeof Chart === 'undefined') return;
    var labels = <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), array_keys($chart))) ?>;
    var values = <?= json_encode(array_values($chart)) ?>;
    var salesChart = null;

    function buildChart() {
      var cs = getComputedStyle(document.documentElement);
      var textMuted = cs.getPropertyValue('--text-muted').trim() || '#475569';
      var borderCol = cs.getPropertyValue('--border').trim() || '#e2e8f0';
      var primary = cs.getPropertyValue('--primary').trim() || '#2563eb';
      var isDark = document.documentElement.getAttribute('data-theme') === 'dark';

      var ctx = canvas.getContext('2d');
      var grad = ctx.createLinearGradient(0, 0, 0, 240);
      grad.addColorStop(0, isDark ? 'rgba(59, 130, 246, 0.85)' : 'rgba(37, 99, 235, 0.9)');
      grad.addColorStop(1, isDark ? 'rgba(59, 130, 246, 0.25)' : 'rgba(37, 99, 235, 0.35)');

      if (salesChart) salesChart.destroy();
      salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'ຍອດຂາຍ (₭)',
            data: values,
            backgroundColor: grad,
            borderColor: primary,
            borderWidth: 1,
            borderRadius: 8,
            borderSkipped: false,
            maxBarThickness: 44
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              padding: 10,
              cornerRadius: 8,
              callbacks: {
                label: function(context) {
                  return new Intl.NumberFormat('en-US').format(context.raw) + ' ₭';
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: borderCol },
              border: { display: false },
              ticks: {
                color: textMuted,
                callback: function(val) {
                  return new Intl.NumberFormat('en-US').format(val) + ' ₭';
                }
              }
            },
            x: {
              grid: { display: false },
              border: { display: false },
              ticks: { color: textMuted }
            }
          }
        }
      });
    }

    buildChart();
    document.addEventListener('pos:themechange', buildChart);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardChart);
  } else {
    initDashboardChart();
  }
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>
