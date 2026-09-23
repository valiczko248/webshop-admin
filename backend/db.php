<?php
// Lokális fejlesztés: SQLite fájl, nincs külön szerver-telepítés.
// Éles (Rackhost) környezetben majd MySQL-re áll át egy config.php hozzáadásával.

// Megjelenítésre szánt dátumok/idők (rendelés dátuma, visszaküldés dátuma stb.) magyar idő
// szerint készüljenek, ne UTC-ben — a biztonsági tokenek lejárati ideje (session, "emlékezz
// rám", jelszó-visszaállítás) viszont szándékosan gmdate()-tel, explicit UTC-ben marad, mert
// az SQLite datetime('now') is UTC-ben számol, és azokkal kell összevetni.
date_default_timezone_set('Europe/Budapest');

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

    $isNew = !file_exists($dataDir . '/app.sqlite');

    $pdo = new PDO('sqlite:' . $dataDir . '/app.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

    if ($isNew) {
        require __DIR__ . '/seed.php';
        $pdo->beginTransaction();
        try {
            seed_database($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        // Egy már létező adatbázison a séma bővülhetett azóta — ilyenkor kell csak pótolni.
        // Friss (most seedelt) adatbázison ez kihagyható, mert a fenti seed_database() már
        // mindent az aktuális schema.sql/seed.php szerint hoz létre.
        run_migrations($pdo);
    }

    return $pdo;
}

// Könnyűsúlyú migráció: a CREATE TABLE IF NOT EXISTS egy már létező (korábbi séma szerinti)
// sqlite fájlon nem ad hozzá új oszlopot, ezért ezeket itt, ALTER TABLE-lel pótoljuk — a "már
// létezik" hibát elnyeljük, így ez minden kérésnél lefuthat, a második hívástól kezdve no-op.
function run_migrations(PDO $pdo): void {
    $alterations = [
        'orders' => [
            'coupon_code TEXT',
            'stock_released INTEGER NOT NULL DEFAULT 0',
            'ship_address TEXT',
            'ship_city TEXT',
            'ship_postcode TEXT',
            'ship_country TEXT',
            'delivery_point_type TEXT',
            'delivery_point_id TEXT',
            'kvikk_shipment_status TEXT',
            'kvikk_tracking_number TEXT',
            'kvikk_tracking_link TEXT',
            'kvikk_label_stored_name TEXT',
            'kvikk_shipment_error TEXT',
        ],
    ];
    foreach ($alterations as $table => $columns) {
        foreach ($columns as $columnDef) {
            try {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $columnDef");
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'duplicate column name')) throw $e;
            }
        }
    }

    // Új beállítás-kulcsok alapértékkel — csak akkor íródnak, ha még nincsenek meg (egy már
    // futó, korábbi seed_settings()-szel létrejött adatbázison is legyen belőlük valami).
    $defaults = [
        'field_mapping' => '',
        'kvikk_auto_mode' => '0',
        'kvikk_default_courier' => '',
        'storefront_url' => '',
        'stripe_test_secret_key' => '',
        'stripe_test_publishable_key' => '',
        'stripe_live_secret_key' => '',
        'stripe_live_publishable_key' => '',
        'last_webshop_call_at' => '',
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

// Több táblát érintő írásokhoz (pl. rendelés + tételei) — vagy mindegyik insert/update
// lefut, vagy egyik sem, hogy sose maradjon félkész rekord.
function with_transaction(PDO $pdo, callable $fn) {
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
