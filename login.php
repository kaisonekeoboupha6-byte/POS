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
<title>ເຂົ້າສູ່ລະບົບ - POS</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<div class="login-box">
  <h1>🛒 ລະບົບຂາຍ POS</h1>
  <p class="sub"><?= h(get_setting('store_name')) ?></p>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>ຊື່ຜູ້ໃຊ້</label>
    <input type="text" name="username" required autofocus>
    <label>ລະຫັດຜ່ານ</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn btn-primary btn-block">ເຂົ້າສູ່ລະບົບ</button>
  </form>
  <p class="hint">ຜູ້ດູແລເລີ່ມຕົ້ນ: admin / admin</p>
</div>
</body>
</html>
