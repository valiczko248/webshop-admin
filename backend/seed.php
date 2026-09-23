<?php
// Csak első indításkor fut le (amikor még nincs adatbázis-fájl), hogy a backend
// ugyanazokkal a demó-termékekkel induljon, mint korábban a statikus mockup.

function seed_database(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO products
            (type, name, price, weight, stock, reserved, deduction, linked_name, linked_qty, linked_weight, gallery, variants, addons, archived)
        VALUES
            (:type, :name, :price, :weight, :stock, :reserved, :deduction, :linked_name, :linked_qty, :linked_weight, :gallery, :variants, :addons, 0)
    ');

    $products = [
        [
            'type' => 'regular',
            'name' => 'Alaptermék',
            'price' => 12900,
            'weight' => 250,
            'stock' => 48,
            'reserved' => 7,
            'deduction' => 1,
            'linked_name' => 'Doboz',
            'linked_qty' => 1,
            'linked_weight' => 35,
            'gallery' => '[]',
            'variants' => '[]',
            'addons' => '[]',
        ],
        [
            'type' => 'regular',
            'name' => 'Nyisd Ki Amikor — alapcsomag',
            'price' => 4990,
            'weight' => 15,
            'stock' => 43,
            'reserved' => 5,
            'deduction' => 1,
            'linked_name' => null,
            'linked_qty' => null,
            'linked_weight' => null,
            'gallery' => '[]',
            'variants' => json_encode([
                ['name' => 'Piros boríték', 'stock' => 15, 'weight' => 15],
                ['name' => 'Fehér boríték', 'stock' => 20, 'weight' => 15],
                ['name' => 'Fekete boríték', 'stock' => 8, 'weight' => 15],
            ], JSON_UNESCAPED_UNICODE),
            'addons' => json_encode([
                ['name' => 'QR-kódos hangüzenet', 'price' => 990, 'weight' => '', 'linked' => '', 'linkedQty' => ''],
                ['name' => 'Masnikötés', 'price' => 390, 'weight' => 5, 'linked' => 'Doboz', 'linkedQty' => 1],
            ], JSON_UNESCAPED_UNICODE),
        ],
        [
            'type' => 'component',
            'name' => 'Doboz',
            'price' => null,
            'weight' => 35,
            'stock' => 120,
            'reserved' => 7,
            'deduction' => 1,
            'linked_name' => null,
            'linked_qty' => null,
            'linked_weight' => null,
            'gallery' => '[]',
            'variants' => '[]',
            'addons' => '[]',
        ],
    ];

    foreach ($products as $p) {
        $stmt->execute($p);
    }
}
