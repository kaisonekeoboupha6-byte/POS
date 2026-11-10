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
?>
<!DOCTYPE html>
<html lang="lo">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ເຂົ້າສູ່ລະບົບ - <?= h(get_setting('store_name', 'POS')) ?></title>
<link rel="stylesheet" href="style.css">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="login-page">
<div class="login-box">
  <div style="width:48px; height:48px; background:var(--text); color:#fff; border-radius:var(--radius); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-weight:800; font-size:22px;">P</div>
  <h1>ລະບົບຂາຍ POS</h1>
  <p class="sub"><?= h(get_setting('store_name', 'Modern Retail Point of Sale')) ?></p>
  <?php if ($error): ?><div class="alert alert-danger"><i data-lucide="alert-circle" style="width:16px; height:16px;"></i> <?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <div class="form-group">
      <label>ຊື່ຜູ້ໃຊ້ (Username)</label>
      <input type="text" name="username" required autofocus placeholder="ປ້ອນຊື່ຜູ້ໃຊ້...">
    </div>
    <div class="form-group">
      <label>ລະຫັດຜ່ານ (Password)</label>
      <input type="password" name="password" required placeholder="ປ້ອນລະຫັດຜ່ານ...">
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:14px;">
      <i data-lucide="log-in" style="width:18px; height:18px;"></i>
      <span>ເຂົ້າສູ່ລະບົບ</span>
    </button>
  </form>
  <p class="hint">ບັນຊີເລີ່ມຕົ້ນ: admin / admin</p>
</div>
<script>
  if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
