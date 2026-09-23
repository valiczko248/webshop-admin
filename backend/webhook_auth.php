<?php
require_once __DIR__ . '/db.php';

// A saját weboldalunk (storefront) ezt küldi az X-API-Key fejlécben — ugyanaz a kulcs,
// amit a Beállítások oldalon generálunk/másolunk a webshop kódjába.
function require_site_api_key(): void {
    $header = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $stmt = db()->prepare("SELECT value FROM settings WHERE key = 'site_api_key'");
    $stmt->execute();
    $expected = $stmt->fetchColumn();
    if (!$expected || !hash_equals((string) $expected, $header)) {
        json_response(['error' => 'Érvénytelen vagy hiányzó API-kulcs.'], 401);
    }
}

function get_setting(string $key): string {
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    return (string) ($stmt->fetchColumn() ?: '');
}

// Stripe a Stripe-Signature fejlécben küldi: "t=<unix-idő>,v1=<hmac-sha256>".
// https://docs.stripe.com/webhooks#verify-manually — nálunk még nincs éles Stripe-webhook
// bekötve, ezért ha a titok üres (stripe_webhook_secret beállítás), az ellenőrzést kihagyjuk
// és csak naplózzuk — helyi/teszt módban ez szándékos, éles bekötéskor a titkot ki kell tölteni.
function verify_stripe_signature(string $payload, string $sigHeader, string $secret): bool {
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        $kv = explode('=', $pair, 2);
        if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
    }
    if (empty($parts['t']) || empty($parts['v1'])) return false;
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);
    return hash_equals($expected, $parts['v1']);
}

function require_stripe_signature(string $payload): void {
    $secret = get_setting('stripe_webhook_secret');
    if ($secret === '') {
        error_log('[webhook/stripe] Nincs beállítva stripe_webhook_secret — aláírás-ellenőrzés kihagyva (csak fejlesztéskor rendben).');
        return;
    }
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    if (!verify_stripe_signature($payload, $sigHeader, $secret)) {
        json_response(['error' => 'Érvénytelen Stripe aláírás.'], 401);
    }
}

// Kvikk a kvikk-webhook-signature fejlécben küldi: HMAC-SHA256(webhook-titok, nyers body), hex.
// Forrás: https://support.kvikk.hu/docs/webhooks/signature (2026-09-23-án ellenőrizve) — nincs
// időbélyeg-komponens, a Stripe-tól eltérően közvetlenül a body hash-eléséről van szó.
function require_kvikk_signature(string $payload): void {
    $secret = get_setting('kvikk_webhook_secret');
    if ($secret === '') {
        error_log('[webhook/kvikk] Nincs beállítva kvikk_webhook_secret — aláírás-ellenőrzés kihagyva (csak fejlesztéskor rendben).');
        return;
    }
    $received = $_SERVER['HTTP_KVIKK_WEBHOOK_SIGNATURE'] ?? '';
    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $received)) {
        json_response(['error' => 'Érvénytelen Kvikk aláírás.'], 401);
    }
}
