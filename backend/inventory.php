<?php
require_once __DIR__ . '/db.php';

function get_order_items(PDO $pdo, int $orderId): array {
    $stmt = $pdo->prepare('SELECT product_name, qty FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

// Termék-név => mennyiség delták egy rendelés tételeihez: a fő termék "deduction" (db/rendelt
// egység) szorzóval, a hozzá kapcsolt csomagolóanyag "linked_qty" szorzóval — pontosan úgy, ahogy
// a Termékek oldal stockSubHtml() felirata is mutatja ("3 db/rendelés", "+2× Doboz/rendelés").
function stock_deltas_for_items(PDO $pdo, array $items): array {
    $deltas = [];
    $stmt = $pdo->prepare('SELECT name, deduction, linked_name, linked_qty FROM products WHERE name = ?');
    foreach ($items as $item) {
        $stmt->execute([$item['product_name']]);
        $product = $stmt->fetch();
        if (!$product) continue; // ismeretlen terméknév — csendben kihagyjuk, ahogy a súlyszámítás is
        $qty = (int) $item['qty'];
        $deduction = (int) ($product['deduction'] ?: 1);
        $deltas[$product['name']] = ($deltas[$product['name']] ?? 0) + $qty * $deduction;
        if (!empty($product['linked_name'])) {
            $linkedQty = (int) ($product['linked_qty'] ?: 1);
            $deltas[$product['linked_name']] = ($deltas[$product['linked_name']] ?? 0) + $qty * $linkedQty;
        }
    }
    return $deltas;
}

// Rendelés beérkezésekor: a készletet nem csökkentjük azonnal (fizikailag még a polcon van),
// csak lefoglaljuk (reserved), hogy más rendelés ne "adja el" kétszer ugyanazt a darabot.
function reserve_stock_for_order(PDO $pdo, array $items): void {
    $deltas = stock_deltas_for_items($pdo, $items);
    $stmt = $pdo->prepare('UPDATE products SET reserved = reserved + ? WHERE name = ?');
    foreach ($deltas as $name => $qty) {
        $stmt->execute([$qty, $name]);
    }
}

// Feladáskor (Kvikk "dispatched"/"shipped"): a foglalás fizikai készletcsökkenéssé válik — a
// stock ÉS a reserved is csökken ugyanannyival, így az elérhető (stock-reserved) mennyiség nem
// változik, csak a fizikai készlet apad. Idempotens: az orders.stock_released jelzi, hogy ez az
// adott rendelésnél már lefutott, így egy megismételt Kvikk-webhook nem vonja le kétszer.
function release_stock_for_order(PDO $pdo, int $orderId): void {
    $stmt = $pdo->prepare('SELECT stock_released FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    if ((int) $stmt->fetchColumn() === 1) return;

    $deltas = stock_deltas_for_items($pdo, get_order_items($pdo, $orderId));
    $stmt = $pdo->prepare('UPDATE products SET stock = MAX(0, stock - ?), reserved = MAX(0, reserved - ?) WHERE name = ?');
    foreach ($deltas as $name => $qty) {
        $stmt->execute([$qty, $qty, $name]);
    }
    $pdo->prepare('UPDATE orders SET stock_released = 1 WHERE id = ?')->execute([$orderId]);
}

// A rendelés össz-súlya grammban, a Termékeknél megadott súlyok + kapcsolt csomagolóanyag alapján —
// szándékosan ugyanazt a képletet követi, mint az admin/script.js computeOrderWeightBreakdown()
// függvénye (deduction NÉLKÜL, mert az csak a raktárkészlet-könyvelés belső szorzója, a csomag
// tényleges súlyát nem befolyásolja). Null, ha bármelyik tételnél hiányzik a súly — ez jelzi a
// hívónak, hogy a Kvikk API missing_weight hibát adna.
function compute_order_weight_grams(PDO $pdo, int $orderId): ?int {
    $items = get_order_items($pdo, $orderId);
    $stmt = $pdo->prepare('SELECT name, weight, linked_name, linked_qty, linked_weight FROM products WHERE name = ?');
    $linkedStmt = $pdo->prepare('SELECT weight FROM products WHERE name = ?');
    $total = 0;
    foreach ($items as $item) {
        $stmt->execute([$item['product_name']]);
        $product = $stmt->fetch();
        $qty = (int) $item['qty'];
        if (!$product || $product['weight'] === null) return null;
        $total += $qty * (int) $product['weight'];

        if (!empty($product['linked_name'])) {
            $linkedQty = (int) ($product['linked_qty'] ?: 1);
            $linkedUnitWeight = $product['linked_weight'];
            if ($linkedUnitWeight === null) {
                $linkedStmt->execute([$product['linked_name']]);
                $linkedUnitWeight = $linkedStmt->fetchColumn();
            }
            if ($linkedUnitWeight === null || $linkedUnitWeight === false) return null;
            $total += $qty * $linkedQty * (int) $linkedUnitWeight;
        }
    }
    return $total > 0 ? $total : null;
}
