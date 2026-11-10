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
  <h3>
    <span style="width:10px;height:10px;border-radius:50%;background:var(--success);display:inline-block"></span>
    <span>ກະປັດຈຸບັນຂອງທ່ານ (ກະ #<?= $my_shift['id'] ?> — ເປີດເມື່ອ <?= date('d/m/Y H:i', strtotime($my_shift['opened_at'])) ?>)</span>
  </h3>
  <div class="grid grid-4">
    <div class="stat-card">
      <div class="icon" style="color:var(--primary);background:var(--primary-50)"><i data-lucide="landmark"></i></div>
      <div>
        <div class="value tabular"><?= money($my_shift['opening_cash']) ?></div>
        <div class="label">ເງິນທອນເປີດກະ</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:#6366f1;background:#eef2ff"><i data-lucide="receipt"></i></div>
      <div>
        <div class="value tabular"><?= number_format($shift_summary['bills']) ?></div>
        <div class="label">ຈຳນວນບິນຂາຍສຳເລັດ</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:var(--success);background:var(--success-50)"><i data-lucide="trending-up"></i></div>
      <div>
        <div class="value tabular"><?= money($shift_summary['total']) ?></div>
        <div class="label">ຍອດຂາຍລວມທັງໝົດ</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="icon" style="color:var(--warning);background:var(--warning-50)"><i data-lucide="wallet"></i></div>
      <div>
        <div class="value tabular"><?= money($my_shift['opening_cash'] + $shift_summary['cash']) ?></div>
        <div class="label">ເງິນສົດທີ່ຄວນມີໃນລີ້ນຊັກ</div>
      </div>
    </div>
  </div>
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div style="display:flex;gap:16px;font-size:13.5px;color:var(--text-muted);font-weight:600">
      <span style="display:inline-flex;align-items:center;gap:6px"><i data-lucide="banknote" style="width:16px;height:16px;color:var(--success)"></i> ເງິນສົດ: <strong class="tabular" style="color:var(--text)"><?= money($shift_summary['cash']) ?></strong></span>
      <span style="display:inline-flex;align-items:center;gap:6px"><i data-lucide="qr-code" style="width:16px;height:16px;color:var(--primary)"></i> BCEL QR: <strong class="tabular" style="color:var(--text)"><?= money($shift_summary['qr']) ?></strong></span>
      <span style="display:inline-flex;align-items:center;gap:6px"><i data-lucide="credit-card" style="width:16px;height:16px;color:#6366f1"></i> ບັດ: <strong class="tabular" style="color:var(--text)"><?= money($shift_summary['card']) ?></strong></span>
    </div>
    <button class="btn btn-danger" onclick="document.getElementById('closeModal').classList.add('show')">
      <i data-lucide="lock"></i> ປິດກະການເຮັດວຽກ
    </button>
  </div>
</div>

<div class="modal-bg" id="closeModal">
  <div class="modal">
    <h3><i data-lucide="lock" style="color:var(--danger)"></i> <span>ປິດກະ — ນັບເງິນລີ້ນຊັກ</span></h3>
    <p style="margin-bottom:14px;color:var(--text-muted)">ເງິນສົດທີ່ຄວນມີໃນລີ້ນຊັກ: <strong class="tabular" style="color:var(--text);font-size:16px"><?= money($my_shift['opening_cash'] + $shift_summary['cash']) ?></strong></p>
    <div class="form-group">
      <label>ເງິນສົດນັບໄດ້ຕົວຈິງ (₭)</label>
      <input type="number" id="closingCash" min="0" value="<?= (float)($my_shift['opening_cash'] + $shift_summary['cash']) ?>" class="tabular" style="font-size:18px;font-weight:700;height:48px">
    </div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button class="btn btn-secondary btn-block" onclick="document.getElementById('closeModal').classList.remove('show')">ຍົກເລີກ</button>
      <button class="btn btn-danger btn-block" onclick="closeShift()"><i data-lucide="check"></i> ຢືນຢັນປິດກະ</button>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <h3><i data-lucide="clock"></i> <span>ທ່ານຍັງບໍ່ໄດ້ເປີດກະການເຮັດວຽກ</span></h3>
  <div class="form-row" style="align-items:flex-end">
    <div class="form-group" style="max-width:280px">
      <label>ເງິນທອນເລີ່ມຕົ້ນໃນລີ້ນຊັກ (₭)</label>
      <input type="number" id="openingCash" value="0" min="0" class="tabular">
    </div>
    <div style="margin-bottom:16px">
      <button class="btn btn-success" onclick="openShift()"><i data-lucide="play"></i> ເປີດກະດຽວນີ້</button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3><i data-lucide="history"></i> <span>ປະຫວັດກະການເຮັດວຽກ<?= $is_manager ? ' (ພະນັກງານທຸກຄົນ)' : '' ?></span></h3>
  <table class="table">
    <tr><th>#</th><th>ພະນັກງານ</th><th>ເວລາເປີດ</th><th>ເວລາປິດ</th>
        <th class="num">ເງິນເປີດກະ</th><th class="num">ບິນ</th><th class="num">ຍອດຂາຍລວມ</th>
        <th class="num">ເງິນຄວນມີ</th><th class="num">ນັບໄດ້ຕົວຈິງ</th><th class="num">ສ່ວນຕ່າງ</th><th>ສະຖານະ</th></tr>
    <?php foreach ($shifts as $sh): ?>
    <tr>
      <td class="tabular" style="font-family:var(--font-mono)">#<?= $sh['id'] ?></td>
      <td><strong><?= h($sh['fullname']) ?></strong></td>
      <td style="font-size:12.5px;color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($sh['opened_at'])) ?></td>
      <td style="font-size:12.5px;color:var(--text-muted)"><?= $sh['closed_at'] ? date('d/m/Y H:i', strtotime($sh['closed_at'])) : '-' ?></td>
      <td class="num"><?= money($sh['opening_cash']) ?></td>
      <td class="num"><?= number_format($sh['bills']) ?></td>
      <td class="num"><strong><?= money($sh['sales_total']) ?></strong></td>
      <td class="num"><?= $sh['expected_cash'] !== null ? money($sh['expected_cash']) : '-' ?></td>
      <td class="num"><?= $sh['closing_cash'] !== null ? money($sh['closing_cash']) : '-' ?></td>
      <td class="num" style="font-weight:700;color:<?= ($sh['difference'] ?? 0) < 0 ? 'var(--danger)' : 'var(--success-dark)' ?>">
        <?= $sh['difference'] !== null ? (($sh['difference'] > 0 ? '+' : '') . money($sh['difference'])) : '-' ?>
      </td>
      <td>
        <span class="badge <?= $sh['status'] === 'open' ? 'badge-success' : 'badge-info' ?>">
          <?= $sh['status'] === 'open' ? 'ເປີດຢູ່' : 'ປິດແລ້ວ' ?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$shifts): ?>
      <tr><td colspan="11" style="color:var(--text-muted);text-align:center;padding:30px">ຍັງບໍ່ມີປະຫວັດກະການເຮັດວຽກ</td></tr>
    <?php endif; ?>
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
    alert('ປິດກະສຳເລັດ\nເງິນຄວນມີ: ' + Number(res.expected).toLocaleString() + ' ₭\nສ່ວນຕ່າງ: ' + Number(res.difference).toLocaleString() + ' ₭');
    location.reload();
  } else alert(res.error);
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
