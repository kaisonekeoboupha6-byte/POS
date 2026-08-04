<?php
/**
 * config.php - ການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ ແລະ ຟັງຊັນຊ່ວຍ
 */
session_start();
date_default_timezone_set('Asia/Vientiane');

define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('ເຊື່ອມຕໍ່ຖານຂໍ້ມູນບໍ່ໄດ້: ' . $e->getMessage() . ' — ກະລຸນາ import ໄຟລ໌ database.sql ກ່ອນ');
}

/* ---------- Auth ---------- */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_user() {
    return [
        'id'       => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'fullname' => $_SESSION['fullname'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
    ];
}

/** admin ເຮັດໄດ້ທຸກຢ່າງ, manager ຈັດການຫຼັງບ້ານ, cashier ຂາຍເທົ່ານັ້ນ */
function require_role($roles) {
    require_login();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center">
             <h2>ບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້</h2>
             <a href="pos.php">ກັບໄປໜ້າຂາຍ</a></div>');
    }
}

/* ---------- Settings ---------- */
function get_setting($key, $default = '') {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

/* ---------- Helpers ---------- */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function money($n) {
    return number_format((float)$n) . ' ₭';
}

function flash_set($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_show() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . h($f['type']) . '">' . h($f['msg']) . '</div>';
    }
}

/** ກວດລະຫັດຜ່ານ: ຮອງຮັບທັງ password_hash ແລະ md5 (ບັນຊີ admin ເລີ່ມຕົ້ນ) */
function check_password($input, $stored) {
    if (password_verify($input, $stored)) return true;
    return md5($input) === $stored;
}

/** ອັບໂຫຼດຮູບສິນຄ້າຈາກຄອມພິວເຕີ -> ໂຟນເດີ uploads/ */
function upload_image($file) {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return null;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
    $name = 'p' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        return $name;
    }
    return null;
}

/** ຫາກະທີ່ຍັງເປີດຢູ່ຂອງຜູ້ໃຊ້ */
function get_open_shift($user_id) {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $st->execute([$user_id]);
    return $st->fetch() ?: null;
}
