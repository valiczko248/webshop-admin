<?php
require_once __DIR__ . '/env.php';

const KVIKK_API_BASE = 'https://api.kvikk.hu/v1';

// Alacsony szintű, nyers hívás a Kvikk API-hoz — X-API-KEY fejléccel, JSON törzzsel (ha van).
// A hívó mindig kap egy {ok: bool, ...} tömböt, sosem dob kivételt hálózati/API hibára, hogy egy
// webhook-feldolgozás soha ne szálljon el emiatt.
function kvikk_request(string $method, string $path, ?array $body = null): array {
    $apiKey = env('KVIKK_API_KEY');
    if (!$apiKey) return ['ok' => false, 'error' => 'Nincs beállítva KVIKK_API_KEY a .env fájlban.'];

    $headers = ['X-API-KEY: ' . $apiKey, 'Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;

    $ch = curl_init(KVIKK_API_BASE . $path);
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) return ['ok' => false, 'error' => "Nem sikerült elérni a Kvikk API-t: {$curlError}"];
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'error' => $data['message'] ?? "A Kvikk API {$status} státusszal válaszolt.",
            'code' => $data['code'] ?? null,
            'httpStatus' => $status,
        ];
    }
    return ['ok' => true, 'data' => $data];
}

function kvikk_get_account(): array {
    return kvikk_request('GET', '/account-details');
}

// A shipment létrehozásához kötelező senderID — az account-details "senders" listájának első
// eleme (ennél a fióknál eddig mindig egyetlen feladó volt regisztrálva, lásd a Szállítás oldal
// élő kapcsolat-tesztjét). Ha a jövőben több feladó lenne, ezt kellene bővíteni választóval.
function kvikk_pick_sender_id(): ?string {
    $res = kvikk_get_account();
    if (!$res['ok']) return null;
    $senders = $res['data']['data']['senders'] ?? [];
    return $senders[0]['_id'] ?? null;
}

function kvikk_active_courier_slugs(): array {
    $res = kvikk_get_account();
    if (!$res['ok']) return [];
    $couriers = $res['data']['data']['couriers'] ?? [];
    return array_values(array_map(
        fn($c) => $c['slug'],
        array_filter($couriers, fn($c) => ($c['status'] ?? '') === 'active')
    ));
}

// POST /v1/shipment — https://support.kvikk.hu/docs/api/create-shipment (ellenőrizve 2026-09-23).
function kvikk_create_shipment(array $payload): array {
    return kvikk_request('POST', '/shipment', $payload);
}
