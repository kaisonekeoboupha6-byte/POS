<?php
/**
 * reports.php - ລາຍງານ ແລະ ການວິເຄາະ
 * - ລາຍງານການຂາຍ / ສິນຄ້າຂາຍດີ / ກຳໄລ-ຂາດທຶນ / ຍອດຂາຍພະນັກງານ
 * - Export CSV (ເປີດໃນ Excel ໄດ້) + ພິມເປັນ PDF (window.print)
 * - ຍົກເລີກບິນ (ຄືນສະຕ໋ອກ + ຄືນຄະແນນ)
 */
require_once __DIR__ . '/config.php';
require_role(['admin', 'manager']);
$u = current_user();

$type = $_GET['type'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/* ---------- ຍົກເລີກບິນ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'cancel_sale') {
    $sid = (int)$_POST['sale_id'];
    $st = $pdo->prepare("SELECT * FROM sales WHERE id=? AND status='completed'");
    $st->execute([$sid]);
    $sale = $st->fetch();
    if ($sale) {
        $pdo->beginTransaction();
        // ຄືນສະຕ໋ອກແບບ batch
        $items = $pdo->prepare('SELECT product_id, qty FROM sale_items WHERE sale_id=?');
        $items->execute([$sid]);
        $restockByPid = [];
        foreach ($items->fetchAll() as $it) {
            if ($it['product_id']) {
                $pid = (int)$it['product_id'];
                $restockByPid[$pid] = ($restockByPid[$pid] ?? 0) + (int)$it['qty'];
            }
        }
        if ($restockByPid) {
            ksort($restockByPid, SORT_NUMERIC);
            $cases = [];
            $uParams = [];
            foreach ($restockByPid as $pid => $rq) {
                $cases[] = 'WHEN ? THEN stock_qty + ?';
                $uParams[] = $pid;
                $uParams[] = $rq;
            }
            $uIds = array_keys($restockByPid);
            $uIn = implode(',', array_fill(0, count($uIds), '?'));
            $pdo->prepare('UPDATE products SET stock_qty = CASE id ' . implode(' ', $cases) . " END WHERE id IN ($uIn)")
                ->execute(array_merge($uParams, $uIds));
        }
        // ຄືນຄະແນນ
        if ($sale['customer_id']) {
            $delta = $sale['points_used'] - $sale['points_earned'];
            if ($delta != 0) {
                $pdo->prepare('UPDATE customers SET points = points + ? WHERE id = ?')
                    ->execute([$delta, $sale['customer_id']]);
                $pdo->prepare("INSERT INTO point_transactions (customer_id, sale_id, points, type, note)
                               VALUES (?,?,?,'adjust','ຍົກເລີກບິນ')")
                    ->execute([$sale['customer_id'], $sid, $delta]);
            }
        }
        $pdo->prepare("UPDATE sales SET status='cancelled' WHERE id=?")->execute([$sid]);
        $pdo->commit();
        flash_set('ຍົກເລີກບິນ ' . $sale['invoice_no'] . ' ແລ້ວ (ຄືນສະຕ໋ອກ + ຄະແນນ)');
    }
    header("Location: reports.php?type=sales&from=$from&to=$to");
    exit;
}

/* ---------- ດຶງຂໍ້ມູນຕາມປະເພດລາຍງານ ---------- */
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

// ສະຫຼຸບລວມ
$st = $pdo->prepare(
    "SELECT COUNT(*) bills, COALESCE(SUM(subtotal),0) subtotal,
        COALESCE(SUM(discount_amount + points_discount),0) discount,
        COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(total),0) total
     FROM sales WHERE status='completed' AND created_at BETWEEN ? AND ?");
$st->execute($range);
$summary = $st->fetch();

// ຕົ້ນທຶນ + ກຳໄລ
$st = $pdo->prepare(
    "SELECT COALESCE(SUM(si.cost * si.qty),0) cost, COALESCE(SUM(si.total),0) revenue
     FROM sale_items si JOIN sales s ON s.id = si.sale_id
     WHERE s.status='completed' AND s.created_at BETWEEN ? AND ?");
$st->execute($range);
$pl = $st->fetch();
$profit = $summary['total'] - $pl['cost'];

$rows = [];
if ($type === 'sales') {
    $st = $pdo->prepare(
        "SELECT s.*, u.fullname, c.name customer_name FROM sales s
         JOIN users u ON u.id=s.user_id LEFT JOIN customers c ON c.id=s.customer_id
         WHERE s.created_at BETWEEN ? AND ? ORDER BY s.id DESC");
    $st->execute($range);
    $rows = $st->fetchAll();
} elseif ($type === 'products') {
    $st = $pdo->prepare(
        "SELECT si.product_name, SUM(si.qty) qty, SUM(si.total) revenue,
                SUM(si.cost * si.qty) cost, SUM(si.total) - SUM(si.cost * si.qty) profit
         FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE s.status='completed' AND s.created_at BETWEEN ? AND ?
         GROUP BY si.product_name ORDER BY qty DESC");
    $st->execute($range);
    $rows = $st->fetchAll();
} elseif ($type === 'staff') {
    $st = $pdo->prepare(
        "SELECT u.fullname, u.role, COUNT(s.id) bills, COALESCE(SUM(s.total),0) total
         FROM users u LEFT JOIN sales s
           ON s.user_id=u.id AND s.status='completed' AND s.created_at BETWEEN ? AND ?
         GROUP BY u.id ORDER BY total DESC");
    $st->execute($range);
    $rows = $st->fetchAll();
} elseif ($type === 'daily') {
    $st = $pdo->prepare(
        "SELECT DATE(created_at) d, COUNT(*) bills, SUM(total) total
         FROM sales WHERE status='completed' AND created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY d");
    $st->execute($range);
    $rows = $st->fetchAll();
}

/* ---------- Export CSV (Excel) ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . $from . '_' . $to . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM ໃຫ້ Excel ອ່ານພາສາລາວໄດ້
    $out = fopen('php://output', 'w');
    if ($type === 'sales') {
        fputcsv($out, ['ເລກບິນ', 'ວັນທີ', 'ພະນັກງານ', 'ລູກຄ້າ', 'ລວມ', 'ສ່ວນຫຼຸດ', 'ພາສີ', 'ຍອດຈ່າຍ', 'ວິທີຈ່າຍ', 'ສະຖານະ']);
        foreach ($rows as $r) fputcsv($out, [$r['invoice_no'], $r['created_at'], $r['fullname'],
            $r['customer_name'] ?? '-', $r['subtotal'], $r['discount_amount'] + $r['points_discount'],
            $r['tax_amount'], $r['total'], $r['payment_method'], $r['status']]);
    } elseif ($type === 'products') {
        fputcsv($out, ['ສິນຄ້າ', 'ຈຳນວນຂາຍ', 'ຍອດຂາຍ', 'ຕົ້ນທຶນ', 'ກຳໄລ']);
        foreach ($rows as $r) fputcsv($out, [$r['product_name'], $r['qty'], $r['revenue'], $r['cost'], $r['profit']]);
    } elseif ($type === 'staff') {
        fputcsv($out, ['ພະນັກງານ', 'ສິດທິ', 'ຈຳນວນບິນ', 'ຍອດຂາຍ']);
        foreach ($rows as $r) fputcsv($out, [$r['fullname'], $r['role'], $r['bills'], $r['total']]);
    } elseif ($type === 'daily') {
        fputcsv($out, ['ວັນທີ', 'ຈຳນວນບິນ', 'ຍອດຂາຍ']);
        foreach ($rows as $r) fputcsv($out, [$r['d'], $r['bills'], $r['total']]);
    }
    fclose($out);
    exit;
}

$page = 'reports';
$title = 'ລາຍງານ ແລະ ການວິເຄາະ';
include __DIR__ . '/header.php';

$tabs = [
    'sales'    => ['receipt',     'ລາຍງານການຂາຍ'],
    'daily'    => ['calendar',    'ຍອດຂາຍລາຍວັນ'],
    'products' => ['award',       'ສິນຄ້າຂາຍດີ'],
    'staff'    => ['user-check',  'ຍອດຂາຍພະນັກງານ'],
];
?>
<div class="toolbar no-print">
  <form method="get">
    <input type="hidden" name="type" value="<?= h($type) ?>">
    <label style="font-size:13px;font-weight:700">ແຕ່ວັນທີ</label>
    <input type="date" name="from" value="<?= h($from) ?>" class="tabular">
    <label style="font-size:13px;font-weight:700">ຫາ</label>
    <input type="date" name="to" value="<?= h($to) ?>" class="tabular">
    <button class="btn btn-primary"><i data-lucide="filter"></i> ເບິ່ງລາຍງານ</button>
  </form>
  <div style="display:flex;gap:8px">
    <a class="btn btn-success" href="reports.php?type=<?= h($type) ?>&from=<?= h($from) ?>&to=<?= h($to) ?>&export=csv">
      <i data-lucide="download"></i> Export Excel (CSV)
    </a>
    <button class="btn btn-secondary" onclick="window.print()">
      <i data-lucide="printer"></i> ພິມ / PDF
    </button>
  </div>
</div>

<div class="toolbar no-print" style="justify-content:flex-start">
  <div class="segmented-tabs" role="tablist" aria-label="ປະເພດລາຍງານ">
    <?php foreach ($tabs as $k => [$icon, $label]): ?>
    <a class="segmented-tab <?= $type === $k ? 'active' : '' ?>" href="reports.php?type=<?= $k ?>&from=<?= h($from) ?>&to=<?= h($to) ?>">
      <i data-lucide="<?= $icon ?>" aria-hidden="true" style="width:15px;height:15px;"></i>
      <span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat-card" style="--stat-accent: var(--primary);">
    <div class="icon" style="color:var(--primary);background:var(--primary-50)"><i data-lucide="receipt" aria-hidden="true"></i></div>
    <div>
      <div class="value tabular"><?= number_format($summary['bills']) ?></div>
      <div class="label">ຈຳນວນບິນສຳເລັດ</div>
    </div>
  </div>
  <div class="stat-card" style="--stat-accent: var(--success);">
    <div class="icon" style="color:var(--success);background:var(--success-50)"><i data-lucide="banknote" aria-hidden="true"></i></div>
    <div>
      <div class="value tabular"><?= money($summary['total']) ?></div>
      <div class="label">ຍອດຂາຍສຸດທິ</div>
    </div>
  </div>
  <div class="stat-card" style="--stat-accent: var(--indigo);">
    <div class="icon" style="color:var(--indigo);background:var(--indigo-50)"><i data-lucide="package" aria-hidden="true"></i></div>
    <div>
      <div class="value tabular"><?= money($pl['cost']) ?></div>
      <div class="label">ຕົ້ນທຶນສິນຄ້າ</div>
    </div>
  </div>
  <div class="stat-card" style="--stat-accent: <?= $profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
    <div class="icon" style="color:<?= $profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;background:<?= $profit >= 0 ? 'var(--success-50)' : 'var(--danger-50)' ?>">
      <i data-lucide="trending-up" aria-hidden="true"></i>
    </div>
    <div>
      <div class="value tabular" style="color:<?= $profit >= 0 ? 'var(--success-dark)' : 'var(--danger)' ?>"><?= money($profit) ?></div>
      <div class="label">ກຳໄລ-ຂາດທຶນສຸດທິ</div>
    </div>
  </div>
</div>

<div class="card">
  <h3>
    <i data-lucide="<?= $tabs[$type][0] ?? 'file-text' ?>" aria-hidden="true"></i>
    <span><?= $tabs[$type][1] ?? '' ?> (<?= date('d/m/Y', strtotime($from)) ?> - <?= date('d/m/Y', strtotime($to)) ?>)</span>
  </h3>

  <?php if ($type === 'sales'): ?>
  <div class="table-wrap">
  <table class="table">
    <tr><th>ເລກບິນ</th><th>ວັນທີ</th><th>ພະນັກງານ</th><th>ລູກຄ້າ</th>
        <th class="num">ສ່ວນຫຼຸດ</th><th class="num">ຍອດຊຳລະ</th><th>ຊ່ອງທາງ</th><th>ສະຖານະ</th><th class="no-print" style="text-align:right">ຈັດການ</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td style="font-family:var(--font-mono);font-weight:700"><?= h($r['invoice_no']) ?></td>
      <td style="font-size:12.5px;color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
      <td><?= h($r['fullname']) ?></td>
      <td><?= h($r['customer_name'] ?? '-') ?></td>
      <td class="num"><?= number_format($r['discount_amount'] + $r['points_discount']) ?></td>
      <td class="num"><strong><?= number_format($r['total']) ?> ₭</strong></td>
      <td>
        <?php
          $pm = $r['payment_method'];
          if ($pm === 'cash') echo '<span class="badge">ເງິນສົດ</span>';
          elseif ($pm === 'qr') echo '<span class="badge badge-info">BCEL QR</span>';
          elseif ($pm === 'card') echo '<span class="badge badge-indigo">ບັດ</span>';
          else echo '-';
        ?>
      </td>
      <td><?php $b = ['completed'=>['badge-success','ສຳເລັດ'],'held'=>['badge-warning','ພັກບິນ'],'cancelled'=>['badge-danger','ຍົກເລີກ']][$r['status']] ?? ['badge',$r['status']];
          echo '<span class="badge '.$b[0].'">'.$b[1].'</span>'; ?></td>
      <td class="no-print" style="text-align:right">
        <a href="receipt.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="ພິມໃບບິນ"><i data-lucide="printer" aria-hidden="true"></i></a>
        <?php if ($r['status'] === 'completed'): ?>
        <form method="post" style="display:inline" onsubmit="return confirmAction(this, 'ຍົກເລີກບິນ <?= h($r['invoice_no']) ?> ບໍ່? ສະຕ໋ອກຈະຖືກຄືນ')">
          <input type="hidden" name="act" value="cancel_sale"><input type="hidden" name="sale_id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-danger"><i data-lucide="x-circle" aria-hidden="true"></i> ຍົກເລີກ</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php elseif ($type === 'products'): ?>
  <div class="table-wrap">
  <table class="table">
    <tr><th style="width:60px">#</th><th>ສິນຄ້າ</th><th class="num">ຈຳນວນຂາຍ</th><th class="num">ຍອດຂາຍ (₭)</th>
        <th class="num">ຕົ້ນທຶນ (₭)</th><th class="num">ກຳໄລ (₭)</th></tr>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td class="tabular" style="color:var(--text-muted)"><?= $i + 1 ?></td>
      <td><strong><?= h($r['product_name']) ?></strong></td>
      <td class="num"><strong><?= number_format($r['qty']) ?></strong></td>
      <td class="num"><?= number_format($r['revenue']) ?></td>
      <td class="num"><?= number_format($r['cost']) ?></td>
      <td class="num" style="font-weight:700;color:<?= $r['profit'] >= 0 ? 'var(--success-dark)' : 'var(--danger)' ?>"><?= number_format($r['profit']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php elseif ($type === 'staff'): ?>
  <div class="table-wrap">
  <table class="table">
    <tr><th>ພະນັກງານ</th><th>ສິດທິ</th><th class="num">ຈຳນວນບິນ</th><th class="num">ຍອດຂາຍລວມ</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><strong><?= h($r['fullname']) ?></strong></td>
      <td><span class="badge badge-info"><?= strtoupper(h($r['role'])) ?></span></td>
      <td class="num"><?= number_format($r['bills']) ?></td>
      <td class="num"><strong><?= money($r['total']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php elseif ($type === 'daily'): ?>
  <div style="position:relative; height:240px; margin-bottom:16px;" class="no-print">
    <canvas id="dailyChart"></canvas>
  </div>
  <div class="table-wrap">
  <table class="table">
    <tr><th>ວັນທີ</th><th class="num">ຈຳນວນບິນ</th><th class="num">ຍອດຂາຍລວມ (₭)</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td class="tabular"><?= date('d/m/Y', strtotime($r['d'])) ?></td>
        <td class="num"><?= number_format($r['bills']) ?></td>
        <td class="num"><strong><?= number_format($r['total']) ?> ₭</strong></td></tr>
    <?php endforeach; ?>
  </table>
  </div>
  <script src="assets/vendor/chart.umd.min.js?v=<?= asset_v('assets/vendor/chart.umd.min.js') ?>" defer></script>
  <script>
  (function () {
    function initDailyChart() {
      var canvas = document.getElementById('dailyChart');
      if (!canvas || typeof Chart === 'undefined') return;
      var labels = <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['d'])), $rows)) ?>;
      var data = <?= json_encode(array_map(fn($r) => (float)$r['total'], $rows)) ?>;
      var dailyChart = null;
      function buildDailyChart() {
        var cs = getComputedStyle(document.documentElement);
        var textMuted = cs.getPropertyValue('--text-muted').trim() || '#475569';
        var borderCol = cs.getPropertyValue('--border').trim() || '#e2e8f0';
        var primary = cs.getPropertyValue('--primary').trim() || '#2563eb';
        var ctx = canvas.getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, 220);
        grad.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
        grad.addColorStop(1, 'rgba(37, 99, 235, 0.01)');
        if (dailyChart) dailyChart.destroy();
        dailyChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [{
              label: 'ຍອດຂາຍ (₭)',
              data: data,
              borderColor: primary,
              backgroundColor: grad,
              borderWidth: 2.5,
              pointRadius: 4,
              fill: true,
              tension: 0.28
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, grid: { color: borderCol }, ticks: { color: textMuted } },
              x: { grid: { display: false }, ticks: { color: textMuted } }
            }
          }
        });
      }
      buildDailyChart();
      document.addEventListener('pos:themechange', buildDailyChart);
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initDailyChart);
    } else {
      initDailyChart();
    }
  })();
  </script>
  <?php endif; ?>

  <?php if (!$rows): ?><p style="color:var(--text-muted);text-align:center;padding:24px">ບໍ່ມີຂໍ້ມູນໃນຊ່ວງເວລານີ້</p><?php endif; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
