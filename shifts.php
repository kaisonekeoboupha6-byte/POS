<?php
/**
 * shifts.php - ກະການເຮັດວຽກ (Shift Management)
 * - ເປີດ/ປິດກະ + ເງິນລີ້ນຊັກ + ສະຫຼຸບຍອດຂາຍຂອງກະ
 */
require_once __DIR__ . '/config.php';
require_login();
$page = 'shifts';
$title = 'ກະການເຮັດວຽກ (Shift)';
$u = current_user();
$is_manager = in_array($u['role'], ['admin', 'manager']);

$my_shift = get_open_shift($u['id']);

// ຍອດຂາຍໃນກະປັດຈຸບັນ
$shift_summary = null;
if ($my_shift) {
    $st = $pdo->prepare(
        "SELECT COUNT(*) bills, COALESCE(SUM(total),0) total,
            COALESCE(SUM(CASE WHEN payment_method='cash' THEN total ELSE 0 END),0) cash,
            COALESCE(SUM(CASE WHEN payment_method='card' THEN total ELSE 0 END),0) card,
            COALESCE(SUM(CASE WHEN payment_method='qr'   THEN total ELSE 0 END),0) qr
         FROM sales WHERE shift_id=? AND status='completed'");
    $st->execute([$my_shift['id']]);
    $shift_summary = $st->fetch();
}

// ປະຫວັດກະ: manager ເຫັນທຸກຄົນ, cashier ເຫັນແຕ່ຂອງຕົນເອງ
$sql = "SELECT sh.*, u.fullname,
          (SELECT COUNT(*) FROM sales s WHERE s.shift_id=sh.id AND s.status='completed') bills,
          (SELECT COALESCE(SUM(total),0) FROM sales s WHERE s.shift_id=sh.id AND s.status='completed') sales_total
        FROM shifts sh JOIN users u ON u.id=sh.user_id";
if (!$is_manager) $sql .= ' WHERE sh.user_id = ' . (int)$u['id'];
$sql .= ' ORDER BY sh.id DESC LIMIT 50';
$shifts = $pdo->query($sql)->fetchAll();

include __DIR__ . '/header.php';
?>
<?php if ($my_shift): ?>
<div class="card">
  <h3>🟢 ກະປັດຈຸບັນຂອງທ່ານ (ກະ #<?= $my_shift['id'] ?> ເປີດ <?= date('d/m/Y H:i', strtotime($my_shift['opened_at'])) ?>)</h3>
  <div class="grid grid-4">
    <div class="stat-card"><span class="icon">🏦</span><div>
      <div class="value"><?= money($my_shift['opening_cash']) ?></div><div class="label">ເງິນທອນເປີດກະ</div></div></div>
    <div class="stat-card"><span class="icon">🧾</span><div>
      <div class="value"><?= $shift_summary['bills'] ?></div><div class="label">ຈຳນວນບິນ</div></div></div>
    <div class="stat-card"><span class="icon">💰</span><div>
      <div class="value"><?= money($shift_summary['total']) ?></div><div class="label">ຍອດຂາຍລວມ</div></div></div>
    <div class="stat-card"><span class="icon">💵</span><div>
      <div class="value"><?= money($my_shift['opening_cash'] + $shift_summary['cash']) ?></div>
      <div class="label">ເງິນສົດທີ່ຄວນມີໃນລີ້ນຊັກ</div></div></div>
  </div>
  <p style="margin:10px 0;color:#64748b">
    💵 ເງິນສົດ: <?= money($shift_summary['cash']) ?> |
    💳 ບັດ: <?= money($shift_summary['card']) ?> |
    📱 QR: <?= money($shift_summary['qr']) ?></p>
  <button class="btn btn-danger" onclick="document.getElementById('closeModal').classList.add('show')">🔴 ປິດກະ</button>
</div>

<div class="modal-bg" id="closeModal">
  <div class="modal">
    <h3>🔴 ປິດກະ — ນັບເງິນລີ້ນຊັກ</h3>
    <p style="margin-bottom:10px;color:#64748b">ເງິນສົດທີ່ຄວນມີ: <strong><?= money($my_shift['opening_cash'] + $shift_summary['cash']) ?></strong></p>
    <div class="form-group"><label>ເງິນສົດນັບໄດ້ຕົວຈິງ (₭)</label>
      <input type="number" id="closingCash" min="0" style="font-size:18px"></div>
    <div style="display:flex;gap:10px;margin-top:14px">
      <button class="btn btn-block" onclick="document.getElementById('closeModal').classList.remove('show')">ຍົກເລີກ</button>
      <button class="btn btn-danger btn-block" onclick="closeShift()">ຢືນຢັນປິດກະ</button>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <h3>⏰ ທ່ານຍັງບໍ່ໄດ້ເປີດກະ</h3>
  <div class="form-row" style="align-items:flex-end">
    <div class="form-group" style="max-width:260px"><label>ເງິນທອນເລີ່ມຕົ້ນໃນລີ້ນຊັກ (₭)</label>
      <input type="number" id="openingCash" value="0" min="0"></div>
    <button class="btn btn-success" onclick="openShift()">🟢 ເປີດກະ</button>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3>📜 ປະຫວັດກະ<?= $is_manager ? ' (ທຸກຄົນ)' : '' ?></h3>
  <table class="table">
    <tr><th>#</th><th>ພະນັກງານ</th><th>ເປີດ</th><th>ປິດ</th>
        <th class="num">ເງິນເປີດກະ</th><th class="num">ບິນ</th><th class="num">ຍອດຂາຍ</th>
        <th class="num">ເງິນຄວນມີ</th><th class="num">ນັບໄດ້</th><th class="num">ຕ່າງ</th><th>ສະຖານະ</th></tr>
    <?php foreach ($shifts as $sh): ?>
    <tr>
      <td><?= $sh['id'] ?></td>
      <td><?= h($sh['fullname']) ?></td>
      <td><?= date('d/m/Y H:i', strtotime($sh['opened_at'])) ?></td>
      <td><?= $sh['closed_at'] ? date('d/m/Y H:i', strtotime($sh['closed_at'])) : '-' ?></td>
      <td class="num"><?= money($sh['opening_cash']) ?></td>
      <td class="num"><?= $sh['bills'] ?></td>
      <td class="num"><?= money($sh['sales_total']) ?></td>
      <td class="num"><?= $sh['expected_cash'] !== null ? money($sh['expected_cash']) : '-' ?></td>
      <td class="num"><?= $sh['closing_cash'] !== null ? money($sh['closing_cash']) : '-' ?></td>
      <td class="num" style="color:<?= ($sh['difference'] ?? 0) < 0 ? '#dc2626' : '#16a34a' ?>">
        <?= $sh['difference'] !== null ? money($sh['difference']) : '-' ?></td>
      <td><span class="badge <?= $sh['status'] === 'open' ? 'badge-success' : 'badge-info' ?>">
        <?= $sh['status'] === 'open' ? 'ເປີດຢູ່' : 'ປິດແລ້ວ' ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
async function api(action, data) {
  const res = await fetch('api.php?action=' + action, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data) });
  return res.json();
}
async function openShift() {
  const res = await api('open_shift', { opening_cash: parseFloat(document.getElementById('openingCash').value) || 0 });
  if (res.ok) location.reload(); else alert(res.error);
}
async function closeShift() {
  const val = document.getElementById('closingCash').value;
  if (val === '') { alert('ກະລຸນາປ້ອນເງິນນັບໄດ້'); return; }
  const res = await api('close_shift', { closing_cash: parseFloat(val) || 0 });
  if (res.ok) {
    alert('ປິດກະສຳເລັດ\nເງິນຄວນມີ: ' + res.expected.toLocaleString() + ' ₭\nສ່ວນຕ່າງ: ' + res.difference.toLocaleString() + ' ₭');
    location.reload();
  } else alert(res.error);
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
