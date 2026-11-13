<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: ' . ($_SESSION['role'] === 'cashier' ? 'pos.php' : 'dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND status = 1');
    $st->execute([$username]);
    $user = $st->fetch();
    if ($user && check_password($password, $user['password'])) {
        session_regenerate_id(true);
        // ອັບເກຣດລະຫັດຜ່ານ MD5 ເກົ່າໃຫ້ເປັນ bcrypt ອັດຕະໂນມັດ
        if (strlen($user['password']) === 32) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role']     = $user['role'];
        header('Location: ' . ($user['role'] === 'cashier' ? 'pos.php' : 'dashboard.php'));
        exit;
    }
    $error = 'ຊື່ຜູ້ໃຊ້ ຫຼື ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ';
}
$store_name = get_setting('store_name', 'POS Retail Terminal');
?>
<!DOCTYPE html>
<html lang="lo" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ເຂົ້າສູ່ລະບົບ - <?= h($store_name) ?></title>
<script>
  (function () {
    try {
      var t = localStorage.getItem('pos_theme');
      if (t === 'dark' || t === 'light') {
        document.documentElement.setAttribute('data-theme', t);
      }
    } catch (e) {}
  })();
</script>
<link rel="stylesheet" href="style.css">
<script src="assets/vendor/lucide.min.js"></script>
</head>
<body class="login-page">
<div class="login-shell">
  <!-- LEFT: Commercial Store Showcase -->
  <section class="login-showcase" aria-label="ຂໍ້ມູນລະບົບ">
    <div class="login-showcase-inner">
      <div class="login-brand-pill">
        <div class="login-brand-mark" aria-hidden="true">P</div>
        <div>
          <strong style="display:block;font-size:14px;color:#fff;line-height:1.2;"><?= h($store_name) ?></strong>
          <span style="font-size:11.5px;color:#94a3b8;">Retail Point of Sale Suite</span>
        </div>
      </div>

      <h2>ລະບົບຂາຍໜ້າຮ້ານ &amp; ຄຸ້ມຄອງສາງຄົບວົງຈອນ</h2>
      <p class="lead">ອອກແບບເພື່ອຮ້ານຄ້າຂະໜາດກາງ — ຄິດເງິນໄວດ້ວຍບາໂຄດ, ຕັດສະຕ໋ອກອັດຕະໂນມັດ, ຮອງຮັບ BCEL QR ແລະ ສະຫຼຸບກຳໄລແບບ Real-time.</p>

      <div class="login-features">
        <div class="login-feature-card">
          <div class="login-feature-icon"><i data-lucide="scan-barcode" aria-hidden="true"></i></div>
          <div>
            <strong>ຂາຍໜ້າຮ້ານໄວທັນໃຈ (Cashier Terminal)</strong>
            <span>ຮອງຮັບເຄື່ອງສະແກນບາໂຄດ, ພັກບິນ F2, ສະສົມຄະແນນສະມາຊິກ ແລະ ພິມບິນ 80mm</span>
          </div>
        </div>
        <div class="login-feature-card">
          <div class="login-feature-icon" style="background:rgba(16,185,129,0.18);color:#6ee7b7;"><i data-lucide="package-check" aria-hidden="true"></i></div>
          <div>
            <strong>ຄຸ້ມຄອງສາງ &amp; ແຈ້ງເຕືອນສະຕ໋ອກ</strong>
            <span>ຕິດຕາມສິນຄ້າໃກ້ໝົດ, ຮັບສິນຄ້າເຂົ້າຈາກຜູ້ສະໜອງ ແລະ ພິມສະຕິກເກີບາໂຄດ</span>
          </div>
        </div>
        <div class="login-feature-card">
          <div class="login-feature-icon" style="background:rgba(129,140,248,0.18);color:#c7d2fe;"><i data-lucide="bar-chart-3" aria-hidden="true"></i></div>
          <div>
            <strong>ລາຍງານຍອດຂາຍ, ກະ ແລະ ກຳໄລ-ຂາດທຶນ</strong>
            <span>ກວດສອບເງິນລີ້ນຊັກປະຈຳກະ ພ້ອມສົ່ງອອກ Excel CSV ແລະ ກຣາຟສະຫຼຸບ</span>
          </div>
        </div>
      </div>
    </div>

    <div class="login-showcase-foot">
      <span>Medium Retail Store Standard</span>
      <span>BCEL QR • Cash • Card • Thermal 80mm</span>
    </div>
  </section>

  <!-- RIGHT: Authentication Card -->
  <section class="login-auth">
    <button type="button" class="btn btn-secondary icon-btn" id="loginThemeBtn" onclick="toggleLoginTheme()" aria-label="ສະຫຼັບໂໝດແສງ/ມືດ" style="position:absolute;top:20px;right:20px;">
      <i data-lucide="moon" id="loginThemeIcon" aria-hidden="true"></i>
    </button>

    <div class="login-box">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <span class="badge badge-info"><i data-lucide="shield-check" aria-hidden="true"></i> ລະບົບຢືນຢັນຕົວຕົນ</span>
      </div>
      <h1>ຍິນດີຕ້ອນຮັບກັບຄືນ</h1>
      <p class="sub">ເຂົ້າສູ່ລະບົບ <?= h($store_name) ?> ເພື່ອເລີ່ມການຂາຍ ຫຼື ຈັດການຮ້ານ</p>

      <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <i data-lucide="alert-circle" aria-hidden="true" style="width:17px;height:17px;flex-shrink:0;"></i>
        <span><?= h($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="form-group">
          <label for="usernameInput">ຊື່ຜູ້ໃຊ້ (Username)</label>
          <div class="input-icon-wrap">
            <i data-lucide="user" class="field-icon" aria-hidden="true"></i>
            <input type="text" id="usernameInput" name="username" required autofocus placeholder="ປ້ອນຊື່ຜູ້ໃຊ້...">
          </div>
        </div>

        <div class="form-group">
          <label for="passwordInput">ລະຫັດຜ່ານ (Password)</label>
          <div class="input-icon-wrap pw-wrap">
            <i data-lucide="lock" class="field-icon" aria-hidden="true"></i>
            <input type="password" id="passwordInput" name="password" required placeholder="ປ້ອນລະຫັດຜ່ານ...">
            <button type="button" class="pw-toggle" id="pwToggleBtn" onclick="togglePassword()" aria-label="ສະແດງ/ເຊື່ອງລະຫັດຜ່ານ" aria-pressed="false">
              <i data-lucide="eye" id="pwEyeIcon" aria-hidden="true" style="width:17px;height:17px;"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
          <i data-lucide="log-in" aria-hidden="true"></i>
          <span>ເຂົ້າສູ່ລະບົບ</span>
        </button>
      </form>

      <div class="demo-hint-card">
        <div>
          <strong style="color:var(--text);display:block;">ບັນຊີເລີ່ມຕົ້ນ (Admin)</strong>
          <span class="tabular" style="font-family:var(--font-mono);font-size:12px;">admin / admin</span>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemoLogin()">
          <i data-lucide="sparkles" aria-hidden="true"></i>
          <span>ໃສ່ຄ່າທົດລອງ</span>
        </button>
      </div>
    </div>
  </section>
</div>

<script>
  function syncLoginThemeIcon(theme) {
    var icon = document.getElementById('loginThemeIcon');
    if (icon) icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
    if (window.lucide) lucide.createIcons();
  }

  function toggleLoginTheme() {
    var root = document.documentElement;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('pos_theme', next); } catch (e) {}
    syncLoginThemeIcon(next);
  }

  function togglePassword() {
    var input = document.getElementById('passwordInput');
    var btn = document.getElementById('pwToggleBtn');
    var icon = document.getElementById('pwEyeIcon');
    if (!input || !btn || !icon) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
    if (window.lucide) lucide.createIcons();
  }

  function fillDemoLogin() {
    document.getElementById('usernameInput').value = 'admin';
    document.getElementById('passwordInput').value = 'admin';
    document.getElementById('passwordInput').focus();
  }

  syncLoginThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');
</script>
</body>
</html>
