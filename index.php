<?php
require_once __DIR__ . '/config.php';
if (!is_logged_in()) {
    header('Location: login.php');
} else {
    header('Location: ' . ($_SESSION['role'] === 'cashier' ? 'pos.php' : 'dashboard.php'));
}
exit;
