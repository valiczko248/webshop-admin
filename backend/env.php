<?php
// Egyszerű .env-betöltő — a webshop/.env fájlt olvassa (KVIKK_API_KEY stb.), mert a sima
// PHP ezt magától nem teszi meg. Csak egyszerű KEY=VALUE sorokat kezel, # kommenteket kihagyja.

function load_env(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $path = __DIR__ . '/../.env';
    if (!is_file($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        [$key, $value] = $parts;
        $key = trim($key);
        $value = trim($value);
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function env(string $key, ?string $default = null): ?string {
    load_env();
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
