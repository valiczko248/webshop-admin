<?php
require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/kvikk_client.php';
require __DIR__ . '/../backend/stripe_client.php';
require_api_auth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    json_response(['lastWebshopCallAt' => get_setting('last_webshop_call_at') ?: null]);
}

if ($method !== 'POST') json_response(['error' => 'Ismeretlen kérés.'], 400);

$input = json_input();
$action = $input['action'] ?? '';

// Kvikk + Stripe kapcsolat valódi ellenőrzése — a "Rendszerteszt" modal "connect" lépéséhez.
if ($action === 'run-checks') {
    $mode = ($input['mode'] ?? 'test') === 'live' ? 'live' : 'test';

    $kvikkRes = kvikk_get_account();
    $kvikk = $kvikkRes['ok']
        ? ['ok' => true, 'couriers' => $kvikkRes['data']['data']['couriers'] ?? [], 'senders' => $kvikkRes['data']['data']['senders'] ?? []]
        : ['ok' => false, 'error' => $kvikkRes['error']];

    $stripeRes = stripe_verify_key($mode);
    $stripe = $stripeRes['ok']
        ? ['ok' => true]
        : ['ok' => false, 'error' => $stripeRes['error']];

    json_response(['kvikk' => $kvikk, 'stripe' => $stripe, 'mode' => $mode]);
}

// Valódi, 300 Ft-os Stripe Checkout Session indítása — a kártyaadatot a Stripe saját, hosztolt
// oldala kéri be, ide nem érkezik vissza semmilyen kártyaadat. A böngésző ide irányítjuk át.
if ($action === 'start-live-payment') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/admin/index.php';
    $successUrl = $base . '?system_test_session_id={CHECKOUT_SESSION_ID}&system_test=success';
    $cancelUrl = $base . '?system_test=cancelled';

    $res = stripe_create_checkout_session('live', 300, $successUrl, $cancelUrl);
    if (!$res['ok']) json_response(['error' => $res['error']], 502);

    json_response(['ok' => true, 'url' => $res['data']['url'], 'sessionId' => $res['data']['id']]);
}

// A böngésző erre hívja vissza magát, miután a Stripe visszairányította a success_url-re —
// ellenőrzi, hogy a fizetés ténylegesen sikeres volt-e (nem csak azt, hogy a redirect megtörtént).
if ($action === 'check-session') {
    $sessionId = $input['sessionId'] ?? '';
    if (!$sessionId) json_response(['error' => 'Hiányzó sessionId.'], 400);

    $res = stripe_get_checkout_session('live', $sessionId);
    if (!$res['ok']) json_response(['error' => $res['error']], 502);

    $data = $res['data'];
    json_response([
        'ok' => true,
        'paymentStatus' => $data['payment_status'] ?? null,
        'paymentIntentId' => $data['payment_intent'] ?? null,
        'amountTotal' => $data['amount_total'] ?? null,
    ]);
}

// A diagnosztikai terhelés visszatérítése — valódi Stripe-visszatérítés, hogy a teszt ne
// hagyjon valódi bevételt a fiókban.
if ($action === 'refund-session') {
    $sessionId = $input['sessionId'] ?? '';
    if (!$sessionId) json_response(['error' => 'Hiányzó sessionId.'], 400);

    $sessionRes = stripe_get_checkout_session('live', $sessionId);
    if (!$sessionRes['ok']) json_response(['error' => $sessionRes['error']], 502);
    $paymentIntentId = $sessionRes['data']['payment_intent'] ?? null;
    if (!$paymentIntentId) json_response(['error' => 'Nincs payment_intent ehhez a session-höz (még nem fejeződött be a fizetés?).'], 400);

    $refundRes = stripe_refund_payment_intent('live', $paymentIntentId);
    if (!$refundRes['ok']) json_response(['error' => $refundRes['error']], 502);

    json_response(['ok' => true, 'refundId' => $refundRes['data']['id'] ?? null]);
}

json_response(['error' => 'Ismeretlen action.'], 400);
