<?php
require_once __DIR__ . '/webhook_auth.php'; // get_setting()

const STRIPE_API_BASE = 'https://api.stripe.com/v1';

function stripe_secret_key(string $mode): ?string {
    $key = get_setting($mode === 'live' ? 'stripe_live_secret_key' : 'stripe_test_secret_key');
    return $key !== '' ? $key : null;
}

// Stripe alap-hitelesítése: a secret key HTTP Basic Auth felhasználónévként megy, jelszó nélkül.
// $params form-urlencoded törzsként megy (ezt várja a Stripe API, nem JSON-t) — a PHP
// http_build_query() automatikusan az e Stripe SDK-k által is használt "tömb[index][kulcs]"
// jelöléssel szerializálja a beágyazott tömböket (pl. line_items), amit a Stripe elfogad.
function stripe_request(string $mode, string $method, string $path, array $params = []): array {
    $secretKey = stripe_secret_key($mode);
    if (!$secretKey) {
        return ['ok' => false, 'error' => "Nincs elmentve Stripe secret key ehhez a módhoz ($mode) a Fizetés oldalon."];
    }

    $url = STRIPE_API_BASE . $path;
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_TIMEOUT => 15,
    ];
    if ($method === 'POST' && $params) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) return ['ok' => false, 'error' => "Nem sikerült elérni a Stripe API-t: {$curlError}"];
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'error' => $data['error']['message'] ?? "A Stripe API {$status} státusszal válaszolt.",
            'httpStatus' => $status,
        ];
    }
    return ['ok' => true, 'data' => $data];
}

// Olvasás-jellegű, ártalmatlan hívás — kizárólag azt igazolja, hogy a mentett secret key valódi
// és érvényes-e ehhez a fiókhoz (nem hoz létre, nem módosít semmit).
function stripe_verify_key(string $mode): array {
    return stripe_request($mode, 'GET', '/balance');
}

// Checkout Session — a kártyaadatot a Stripe saját, hosztolt oldala kéri be, sosem érinti ezt a
// szervert (PCI-szempontból ez a legbiztonságosabb integrációs mód).
//
// FONTOS: a HUF a Stripe API-ban a TERHELÉSEKNÉL (PaymentIntent/Checkout) két tizedesjegyes
// pénznemként viselkedik, annak ellenére, hogy a payout-dokumentáció zero-decimalnek mondja —
// https://docs.stripe.com/currencies (ellenőrizve 2026-09-23, "Hungarian Forint (HUF): ... even
// though you can charge two-decimal amounts"). Ezért az összeget FILLÉRBEN kell küldeni: 300 Ft
// töltéséhez unit_amount = 30000, nem 300. Ha ez elromlik, rossz összegű valódi terhelés lenne.
function stripe_create_checkout_session(string $mode, int $amountHuf, string $successUrl, string $cancelUrl): array {
    $params = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'line_items' => [[
            'price_data' => [
                'currency' => 'huf',
                'unit_amount' => $amountHuf * 100,
                'product_data' => ['name' => 'Rendszerteszt — admin backend Stripe-kapcsolat ellenőrzése'],
            ],
            'quantity' => 1,
        ]],
        'metadata' => ['purpose' => 'admin_system_test'],
    ];
    return stripe_request($mode, 'POST', '/checkout/sessions', $params);
}

function stripe_get_checkout_session(string $mode, string $sessionId): array {
    return stripe_request($mode, 'GET', '/checkout/sessions/' . urlencode($sessionId));
}

function stripe_refund_payment_intent(string $mode, string $paymentIntentId): array {
    return stripe_request($mode, 'POST', '/refunds', ['payment_intent' => $paymentIntentId]);
}
