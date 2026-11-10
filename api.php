<?php
/**
 * api.php - AJAX API ຂອງໜ້າຂາຍ POS
 * ທຸກການຂາຍຈະຕັດສະຕ໋ອກແບບ Real-time ພາຍໃນ transaction
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

$u      = current_user();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

function out($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {

    /* ---------- ດຶງສິນຄ້າທັງໝົດ (refresh ຫຼັງຂາຍ) ---------- */
    case 'all_products':
    case 'products': {
        $products = $pdo->query(
            "SELECT p.*, c.name AS category_name FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 1 ORDER BY p.name"
        )->fetchAll();
        out(['ok' => true, 'products' => $products]);
    }

    /* ---------- ຄົ້ນຫາສະມາຊິກ ---------- */
    case 'search_customers':
    case 'customer_search': {
        $q = '%' . ($input['q'] ?? '') . '%';
        $st = $pdo->prepare(
            'SELECT id, code, name, phone, points FROM customers
             WHERE name LIKE ? OR phone LIKE ? OR code LIKE ? LIMIT 10'
        );
        $st->execute([$q, $q, $q]);
        out(['ok' => true, 'customers' => $st->fetchAll()]);
    }

    /* ---------- ຂາຍ / ພັກບິນ (Checkout) ---------- */
    case 'checkout': {
        $items = $input['items'] ?? [];
        $hold  = !empty($input['hold']);
        if (!$items) out(['ok' => false, 'error' => 'ບໍ່ມີສິນຄ້າໃນບິນ']);

        $shift = get_open_shift($u['id']);
        if (!$shift) out(['ok' => false, 'error' => 'ກະລຸນາເປີດກະກ່ອນຂາຍ']);

        $tax_rate    = (float)get_setting('tax_rate', 0);
        $point_rate  = max(1, (int)get_setting('point_rate', 10000));
        $point_value = (int)get_setting('point_value', 100);

        try {
            $pdo->beginTransaction();

            // ຖ້າດຶງບິນພັກມາຈ່າຍ -> ລຶບບິນພັກເກົ່າອອກກ່ອນ
            if (!empty($input['resume_id'])) {
                $st = $pdo->prepare("DELETE FROM sales WHERE id = ? AND status = 'held'");
                $st->execute([(int)$input['resume_id']]);
            }

            // ອ່ານລາຄາ/ຕົ້ນທຶນ/ສະຕ໋ອກ ຈາກຖານຂໍ້ມູນ (ບໍ່ເຊື່ອລາຄາຈາກ client)
            $subtotal = 0;
            $lines = [];
            $stLock = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            foreach ($items as $it) {
                $qty = max(1, (int)$it['qty']);
                $stLock->execute([(int)$it['id']]);
                $p = $stLock->fetch();
                if (!$p) throw new Exception('ບໍ່ພົບສິນຄ້າ ID ' . (int)$it['id']);
                if (!$hold && $p['stock_qty'] < $qty) {
                    throw new Exception('ສະຕ໋ອກບໍ່ພຽງພໍ: ' . $p['name'] . ' (ເຫຼືອ ' . $p['stock_qty'] . ')');
                }
                $line_total = $p['sell_price'] * $qty;
                $subtotal  += $line_total;
                $lines[] = ['p' => $p, 'qty' => $qty, 'total' => $line_total];
            }

            // ສ່ວນຫຼຸດ % ຫຼື ຈຳນວນເງິນ (ປັດເສດເປັນຈຳນວນເຕັມກີບ)
            $dType = in_array($input['discount_type'] ?? '', ['percent', 'amount']) ? $input['discount_type'] : 'amount';
            $dVal  = max(0, (float)($input['discount_value'] ?? 0));
            $discount = $dType === 'percent'
                ? round($subtotal * min($dVal, 100) / 100)
                : round(min($dVal, $subtotal));

            // ຄະແນນສະມາຊິກແລກສ່ວນຫຼຸດ
            $customer_id = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;
            $points_used = 0; $points_discount = 0;
            if (!$hold && $customer_id && (int)($input['points_used'] ?? 0) > 0) {
                $st = $pdo->prepare('SELECT points FROM customers WHERE id = ? FOR UPDATE');
                $st->execute([$customer_id]);
                $cust = $st->fetch();
                if ($cust) {
                    $points_used = min((int)$input['points_used'], (int)$cust['points']);
                    $points_discount = min($points_used * $point_value, max(0, $subtotal - $discount));
                    $points_used = $point_value > 0 ? (int)floor($points_discount / $point_value) : 0;
                    $points_discount = $points_used * $point_value;
                }
            }

            $after_discount = max(0, $subtotal - $discount - $points_discount);
            $tax_amount = round($after_discount * $tax_rate / 100);
            $total = round($after_discount + $tax_amount);

            $method = $hold ? null : ($input['payment_method'] ?? 'cash');
            if (!$hold && !in_array($method, ['cash', 'card', 'qr'])) $method = 'cash';
            $paid_input = round((float)($input['paid_amount'] ?? 0));
            if (!$hold && $method === 'cash' && $paid_input < $total) {
                throw new Exception('ເງິນທີ່ຮັບມາບໍ່ພຽງພໍ (' . number_format($paid_input) . ' / ' . number_format($total) . ' ₭)');
            }
            $paid   = $hold ? 0 : ($method === 'cash' ? $paid_input : $total);
            $change = $hold ? 0 : ($method === 'cash' ? max(0, $paid - $total) : 0);

            // ຄະແນນທີ່ໄດ້ຮັບ
            $points_earned = (!$hold && $customer_id) ? (int)floor($total / $point_rate) : 0;

            // ເລກບິນ: INV + ວັນທີ + ລຳດັບສູງສຸດ (ປ້ອງກັນເລກບິນຊ້ຳເມື່ອລຶບບິນພັກ)
            $today = date('Ymd');
            $st = $pdo->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_no, '-', -1) AS UNSIGNED)), 0) m
                 FROM sales WHERE invoice_no LIKE ? FOR UPDATE"
            );
            $st->execute(['INV' . $today . '-%']);
            $seq = (int)$st->fetch()['m'] + 1;
            $invoice_no = 'INV' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $st = $pdo->prepare(
                'INSERT INTO sales (invoice_no, user_id, shift_id, customer_id, subtotal,
                    discount_type, discount_value, discount_amount, points_used, points_discount,
                    tax_rate, tax_amount, total, payment_method, paid_amount, change_amount,
                    points_earned, status, note)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $invoice_no, $u['id'], $shift['id'], $customer_id, $subtotal,
                $dType, $dVal, $discount, $points_used, $points_discount,
                $tax_rate, $tax_amount, $total, $method, $paid, $change,
                $points_earned, $hold ? 'held' : 'completed',
                trim($input['note'] ?? '') ?: null,
            ]);
            $sale_id = (int)$pdo->lastInsertId();

            $stItem  = $pdo->prepare(
                'INSERT INTO sale_items (sale_id, product_id, product_name, qty, price, cost, total)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $stStock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?');
            foreach ($lines as $ln) {
                $stItem->execute([
                    $sale_id, $ln['p']['id'], $ln['p']['name'], $ln['qty'],
                    $ln['p']['sell_price'], $ln['p']['cost_price'], $ln['total'],
                ]);
                // ຕັດສະຕ໋ອກ Real-time ສະເພາະບິນທີ່ຈ່າຍສຳເລັດ (ບິນພັກຍັງບໍ່ຕັດ)
                if (!$hold) $stStock->execute([$ln['qty'], $ln['p']['id']]);
            }

            // ບັນທຶກຄະແນນ
            if ($points_used > 0) {
                $pdo->prepare('UPDATE customers SET points = points - ? WHERE id = ?')
                    ->execute([$points_used, $customer_id]);
                $pdo->prepare("INSERT INTO point_transactions (customer_id, sale_id, points, type, note)
                               VALUES (?,?,?,'redeem','ແລກຄະແນນເປັນສ່ວນຫຼຸດ')")
                    ->execute([$customer_id, $sale_id, -$points_used]);
            }
            if ($points_earned > 0) {
                $pdo->prepare('UPDATE customers SET points = points + ? WHERE id = ?')
                    ->execute([$points_earned, $customer_id]);
                $pdo->prepare("INSERT INTO point_transactions (customer_id, sale_id, points, type, note)
                               VALUES (?,?,?,'earn','ຄະແນນຈາກການຊື້')")
                    ->execute([$customer_id, $sale_id, $points_earned]);
            }

            $pdo->commit();
            out(['ok' => true, 'sale_id' => $sale_id, 'invoice_no' => $invoice_no,
                 'total' => $total, 'change' => $change]);
        } catch (Exception $e) {
            $pdo->rollBack();
            out(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ---------- ລາຍການບິນທີ່ພັກ ---------- */
    case 'held_list': {
        $st = $pdo->prepare(
            "SELECT id, invoice_no, subtotal, total, note, DATE_FORMAT(created_at,'%d/%m/%Y %H:%i') created_at
             FROM sales WHERE status = 'held' ORDER BY id DESC"
        );
        $st->execute();
        out(['ok' => true, 'sales' => $st->fetchAll()]);
    }

    /* ---------- ລຶບບິນທີ່ພັກໄວ້ ---------- */
    case 'delete_held': {
        $id = (int)($input['id'] ?? 0);
        $st = $pdo->prepare("DELETE FROM sales WHERE id = ? AND status = 'held'");
        $st->execute([$id]);
        out(['ok' => true]);
    }

    /* ---------- ດຶງຍອດເງິນສົດທີ່ຄວນມີໃນກະປັດຈຸບັນ (Real-time) ---------- */
    case 'shift_status': {
        $shift = get_open_shift($u['id']);
        if (!$shift) out(['ok' => false, 'error' => 'ບໍ່ມີກະທີ່ເປີດຢູ່']);
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(total),0) cash_sales FROM sales
             WHERE shift_id = ? AND status = 'completed' AND payment_method = 'cash'"
        );
        $st->execute([$shift['id']]);
        $expected = (float)$shift['opening_cash'] + (float)$st->fetch()['cash_sales'];
        out(['ok' => true, 'shift_id' => $shift['id'], 'expected_cash' => $expected]);
    }

    /* ---------- ດຶງບິນພັກກັບມາຂາຍຕໍ່ ---------- */
    case 'resume_held': {
        $id = (int)($input['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND status = 'held'");
        $st->execute([$id]);
        $sale = $st->fetch();
        if (!$sale) out(['ok' => false, 'error' => 'ບໍ່ພົບບິນພັກນີ້']);
        $st = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ?');
        $st->execute([$id]);
        $items = $st->fetchAll();
        $customer = null;
        if ($sale['customer_id']) {
            $st = $pdo->prepare('SELECT id, code, name, phone, points FROM customers WHERE id = ?');
            $st->execute([$sale['customer_id']]);
            $customer = $st->fetch() ?: null;
        }
        out(['ok' => true, 'sale' => $sale, 'items' => $items, 'customer' => $customer]);
    }

    /* ---------- ເປີດກະ ---------- */
    case 'open_shift': {
        if (get_open_shift($u['id'])) out(['ok' => false, 'error' => 'ທ່ານມີກະທີ່ເປີດຢູ່ແລ້ວ']);
        $st = $pdo->prepare('INSERT INTO shifts (user_id, opening_cash) VALUES (?, ?)');
        $st->execute([$u['id'], max(0, (float)($input['opening_cash'] ?? 0))]);
        out(['ok' => true, 'shift_id' => $pdo->lastInsertId()]);
    }

    /* ---------- ປິດກະ ---------- */
    case 'close_shift': {
        $shift = get_open_shift($u['id']);
        if (!$shift) out(['ok' => false, 'error' => 'ບໍ່ມີກະທີ່ເປີດຢູ່']);
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(total),0) cash_sales FROM sales
             WHERE shift_id = ? AND status = 'completed' AND payment_method = 'cash'"
        );
        $st->execute([$shift['id']]);
        $cash_sales = (float)$st->fetch()['cash_sales'];
        $closing  = max(0, (float)($input['closing_cash'] ?? 0));
        $expected = $shift['opening_cash'] + $cash_sales;
        $st = $pdo->prepare(
            "UPDATE shifts SET closing_cash = ?, expected_cash = ?, difference = ?,
             status = 'closed', closed_at = NOW() WHERE id = ?"
        );
        $st->execute([$closing, $expected, $closing - $expected, $shift['id']]);
        $diff = $closing - $expected;
        out(['ok' => true, 'expected' => $expected, 'difference' => $diff, 'diff' => $diff]);
    }

    default:
        out(['ok' => false, 'error' => 'ບໍ່ຮູ້ຈັກຄຳສັ່ງ: ' . $action]);
}
