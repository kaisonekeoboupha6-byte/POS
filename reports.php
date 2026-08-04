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
        // ຄືນສະຕ໋ອກ
        $items = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id=?');
        $items->execute([$sid]);
        $stBack = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?');
        foreach ($items->fetchAll() as $it) {
            if ($it['product_id']) $stBack->execute([$it['qty'], $it['product_id']]);
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

$tabs = ['sales' => '🧾 ລາຍງານການຂາຍ', 'daily' => '📅 ຍອດຂາຍລາຍວັນ',
         'products' => '🏆 ສິນຄ້າຂາຍດີ', 'staff' => '👤 ຍອດຂາຍພະນັກງານ'];
?>
<div class="toolbar no-print">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="type" value="<?= h($type) ?>">
    <label>ແຕ່ວັນທີ</label><input type="date" name="from" value="<?= h($from) ?>">
    <label>ຫາ</label><input type="date" name="to" value="<?= h($to) ?>">
    <button class="btn btn-primary">ເບິ່ງລາຍງານ</button>
  </form>
  <a class="btn btn-success" href="reports.php?type=<?= h($type) ?>&from=<?= h($from) ?>&to=<?= h($to) ?>&export=csv">⬇ Export Excel (CSV)</a>
  <button class="btn btn-warning" onclick="window.print()">🖨 ພິມ / PDF</button>
</div>

<div class="toolbar no-print">
  <?php foreach ($tabs as $k => $label): ?>
  <a class="btn <?= $type === $k ? 'btn-primary' : '' ?>" href="reports.php?type=<?= $k ?>&from=<?= h($from) ?>&to=<?= h($to) ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="grid grid-4">
  <div class="card stat-card"><span class="icon">🧾</span><div>
    <div class="value"><?= number_format($summary['bills']) ?></div><div class="label">ຈຳນວນບິນ</div></div></div>
  <div class="card stat-card"><span class="icon">💰</span><div>
    <div class="value"><?= money($summary['total']) ?></div><div class="label">ຍອດຂາຍສຸດທິ</div></div></div>
  <div class="card stat-card"><span class="icon">📦</span><div>
    <div class="value"><?= money($pl['cost']) ?></div><div class="label">ຕົ້ນທຶນສິນຄ້າ</div></div></div>
  <div class="card stat-card"><span class="icon">📈</span><div>
    <div class="value" style="color:<?= $profit >= 0 ? '#16a34a' : '#dc2626' ?>"><?= money($profit) ?></div>
    <div class="label">ກຳໄລ-ຂາດທຶນ</div></div></div>
</div>

<div class="card">
  <h3><?= $tabs[$type] ?? '' ?> (<?= date('d/m/Y', strtotime($from)) ?> - <?= date('d/m/Y', strtotime($to)) ?>)</h3>

  <?php if ($type === 'sales'): ?>
  <table class="table">
    <tr><th>ເລກບິນ</th><th>ວັນທີ</th><th>ພະນັກງານ</th><th>ລູກຄ້າ</th>
        <th class="num">ຫຼຸດ</th><th class="num">ຍອດຈ່າຍ</th><th>ຈ່າຍ</th><th>ສະຖານະ</th><th class="no-print">ຈັດການ</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= h($r['invoice_no']) ?></td>
      <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
      <td><?= h($r['fullname']) ?></td>
      <td><?= h($r['customer_name'] ?? '-') ?></td>
      <td class="num"><?= number_format($r['discount_amount'] + $r['points_discount']) ?></td>
      <td class="num"><strong><?= number_format($r['total']) ?></strong></td>
      <td><?= ['cash'=>'💵','card'=>'💳','qr'=>'📱'][$r['payment_method']] ?? '-' ?></td>
      <td><?php $b = ['completed'=>['badge-success','ສຳເລັດ'],'held'=>['badge-warning','ພັກ'],'cancelled'=>['badge-danger','ຍົກເລີກ']][$r['status']];
          echo '<span class="badge '.$b[0].'">'.$b[1].'</span>'; ?></td>
      <td class="no-print">
        <a href="receipt.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm">🖨</a>
        <?php if ($r['status'] === 'completed'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('ຍົກເລີກບິນ <?= h($r['invoice_no']) ?> ບໍ່? ສະຕ໋ອກຈະຖືກຄືນ')">
          <input type="hidden" name="act" value="cancel_sale"><input type="hidden" name="sale_id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-danger">❌ ຍົກເລີກ</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php elseif ($type === 'products'): ?>
  <table class="table">
    <tr><th>#</th><th>ສິນຄ້າ</th><th class="num">ຈຳນວນຂາຍ</th><th class="num">ຍອດຂາຍ</th>
        <th class="num">ຕົ້ນທຶນ</th><th class="num">ກຳໄລ</th></tr>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= h($r['product_name']) ?></td>
      <td class="num"><strong><?= number_format($r['qty']) ?></strong></td>
      <td class="num"><?= number_format($r['revenue']) ?></td>
      <td class="num"><?= number_format($r['cost']) ?></td>
      <td class="num" style="color:<?= $r['profit'] >= 0 ? '#16a34a' : '#dc2626' ?>"><?= number_format($r['profit']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php elseif ($type === 'staff'): ?>
  <table class="table">
    <tr><th>ພະນັກງານ</th><th>ສິດທິ</th><th class="num">ຈຳນວນບິນ</th><th class="num">ຍອດຂາຍ</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= h($r['fullname']) ?></td>
      <td><?= h($r['role']) ?></td>
      <td class="num"><?= number_format($r['bills']) ?></td>
      <td class="num"><strong><?= money($r['total']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php elseif ($type === 'daily'): ?>
  <canvas id="dailyChart" height="90" class="no-print"></canvas>
  <table class="table" style="margin-top:16px">
    <tr><th>ວັນທີ</th><th class="num">ຈຳນວນບິນ</th><th class="num">ຍອດຂາຍ</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= date('d/m/Y', strtotime($r['d'])) ?></td>
        <td class="num"><?= number_format($r['bills']) ?></td>
        <td class="num"><strong><?= number_format($r['total']) ?></strong></td></tr>
    <?php endforeach; ?>
  </table>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
  new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: { labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['d'])), $rows)) ?>,
      datasets: [{ label: 'ຍອດຂາຍ (₭)', data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $rows)) ?>,
        borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .3 }] },
    options: { scales: { y: { beginAtZero: true } } }
  });
  </script>
  <?php endif; ?>

  <?php if (!$rows): ?><p style="color:#94a3b8;text-align:center;padding:12px">ບໍ່ມີຂໍ້ມູນໃນຊ່ວງເວລານີ້</p><?php endif; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
