# Éles indulás — lépésről lépésre

Ez a checklist onnan indul, hogy a backend (ez a repo) és a webshop (storefront) is készen van a
gépeden, és odáig vezet, hogy éles forgalmat szolgáljanak ki. Fázisokra bontva, és minden
pontnál jelzem, hogy **valódi, működő ellenőrzés** az admin felületen (tényleg futtasd le), vagy
**fejlesztői feladat** (ezt nekem kell megépítenem/átírnom, nem egy gombnyomás), vagy **még csak
UI-szimuláció** (ne bízz benne, hogy tényleg tesztel valamit).

---

## 0. Fontos előzetes tisztázás — melyik ellenőrzés valódi?

A Beállítások/Szállítás/Fizetés oldalon több "teszt" gomb is van. Ezek nem egyformák:

| Gomb / felület | Valódi-e? |
|---|---|
| **Kvikk kapcsolat ellenőrzése** (Szállítás oldal) | ✅ Valódi — tényleges `GET /account-details` hívás a Kvikk API-hoz a `.env`-ben lévő kulccsal. |
| **Stripe teszt/éles mód kapcsoló + kulcsok mentése** (Fizetés oldal) | ✅ Valódi — a webhook signing secret, a secret key és a publishable key ténylegesen elmentődik szerver-oldalt (`stripe_test_*`/`stripe_live_*` beállítások), módonként külön. |
| **Webhook-titok mentése** (Kvikk és Stripe) | ✅ Valódi — a `webhook_auth.php` ténylegesen ez ellen ellenőriz. |
| **Weboldal API-kulcs generálása** (Beállítások) | ✅ Valódi — ezt kell a webshop kódjába másolni. |
| **Kapcsolat tesztelése** (a domain-es mező a Beállítások tetején) | ✅ **Valódi** (2026-09-23-tól) — ténylegesen lekérdezi, mikor érkezett utoljára sikeresen hitelesített hívás a `webshop.php`-ra (`settings.last_webshop_call_at`, minden érvényes API-kulcsos híváskor frissül). A domain-mező csak megjelenítés, magát a domaint nem ellenőrzi. |
| **Rendszerteszt modal → "connect" lépés** (Kvikk + Stripe kapcsolat) | ✅ **Valódi** (2026-09-23-tól) — tényleges Kvikk `account-details` és Stripe `GET /v1/balance` hívás az adott (teszt/éles) secret key-jel. |
| **Rendszerteszt modal, "Teszt mód"** — a payment/webhook/order/kvikk/email/cleanup lépések | ⚠️ **Nem hoznak létre valódi rendelést** — ez tudatos döntés, nem hiányosság: a checkout maga a webshopon történne, nem az adminban, ezért ezek a lépések most tisztán jelzik, hogy a teljes lánc teszteléséhez valódi teszt-rendelést kell küldeni (lásd 4. fázis). |
| **Rendszerteszt modal, "Éles teszt indítása" (300 Ft)** | ✅ **Valódi** (2026-09-23-tól) — létrehoz egy tényleges Stripe Checkout Session-t (fillérben számolt, 300 Ft = `30000`), a teljes lapot átirányítja a Stripe hosztolt fizetési oldalára (kártyaadat sosem érinti ezt a szervert), visszatéréskor ellenőrzi a fizetés állapotát, és egy gombbal valódi visszatérítést is tud indítani. |
| **AI automatikus alapadat-felismerés** | 🗑️ **Eltávolítva** (2026-09-23) — a hozzá tartozó Beállítások-kártya és a szimulált lépésenkénti modal is törölve. Ha egyszer mégis kellene (valódi AI-alapú weboldal-elemzés), az egy önálló, a nulláról megépítendő feature. |

---

## 1. Fázis — Szerver-előkészítés (Rackhost)

- [ ] FTPS hozzáférés ellenőrzése (ugyanaz a fiók, amit a Buildfolio-nál is használtunk).
- [ ] Új (al)domain kiválasztása/beállítása a backendnek, pl. `admin.nyisdkiamikor.hu`.
- [ ] MySQL adatbázis létrehozása a Rackhost cPanelen (Remote MySQL már megerősítve elérhető).
- [ ] SSL tanúsítvány aktiválása a domainre (Rackhost ingyenes SSL-je jó erre).
- [ ] PHP verzió ellenőrzése a cPanelen — a Buildfolio 8.3-at használ, ez a projekt lokálisan
      8.4-en fut; ha a szerveren csak 8.3 elérhető, ellenőrizni kell, hogy nincs 8.4-only szintaxis
      a kódban (valószínűleg nincs, de érdemes egy `php -l` futtatás a szerver PHP-verziójával is).

## 2. Fázis — Fejlesztői feladat: SQLite → MySQL átállás

**Ez nem gombnyomás, ezt nekem kell megcsinálnom, amikor idáig értünk:**

- [ ] `backend/db.php` bővítése: ha van `config.php` (a `.gitignore`-ban már fenntartott hely),
      MySQL-hez kapcsolódjon PDO-val, különben marad a jelenlegi SQLite (helyi fejlesztéshez).
- [ ] `backend/schema.sql` MySQL-kompatibilis változata — a jelenlegi SQLite-specifikus
      szintaxist (`INTEGER PRIMARY KEY AUTOINCREMENT`, `datetime('now')` összehasonlítások az
      `auth.php`-ban) át kell írni MySQL-megfelelőre.
- [ ] Első futtatáskor a `seed.php` NE fusson le éles adatbázison (jelenleg csak üres DB-n fut le
      automatikusan) — ellenőrizni, hogy ez így marad-e, vagy külön kell tiltani éles configban.

## 3. Fázis — Feltöltés és első indítás

- [ ] Fájlok feltöltése FTPS-en (a `backend/data/`, `.env`, `uploads/*` tartalma és a `node_modules`-
      szerű mappák természetesen NEM a gitből jönnek — ezeket a szerveren kell létrehozni/feltölteni).
- [ ] `.env` létrehozása a szerveren, benne a valódi `KVIKK_API_KEY` — **soha ne kerüljön git-be**.
- [ ] `.htaccess` védelem tényleges élesítése — ez az **első alkalom**, hogy ez ténylegesen
      érvényesül (helyi `php -S` figyelmen kívül hagyja): ellenőrizd, hogy a `backend/`,
      `uploads/invoices/`, `uploads/labels/` közvetlen böngészős elérése valóban 403-at ad.
- [ ] `setup.php` megnyitása böngészőben → admin fiók létrehozása (ez maga a beépített,
      valódi első-indítási ellenőrzés: ha ez sikerül, a session/DB-kapcsolat működik).
- [ ] Bejelentkezés ellenőrzése, admin felület betöltése.

## 4. Fázis — A backend saját ellenőrzéseinek lefuttatása (ezek már most is valódiak)

- [ ] **Beállítások** → Weboldal API-kulcs másolása (ez kerül majd a webshop kódjába,
      `X-API-Key` fejlécként).
- [ ] **Fizetés** oldal → Stripe secret/publishable key mentése **teszt módban** (a Rendszerteszt
      "connect" lépése és az "Éles teszt" is ezt olvassa a beállított módnak megfelelően).
- [ ] **Szállítás** → "Kvikk kapcsolat ellenőrzése" gomb — várt eredmény: aktív futárok +
      feladó adatok valódi listája.
- [ ] **Beállítások** → "Rendszerteszt" (Teszt mód) — most már valódi Kvikk + Stripe
      kulcs-ellenőrzést futtat (nem szimuláció). Zöld pipa mindkettőnél, mielőtt tovább mész.
- [ ] Kvikk fiókban webhook létrehozása → titok bemásolása a Szállítás oldal
      "Kvikk webhook-titok" mezőjébe, Mentés.
- [ ] Stripe Dashboardon (előbb **teszt módban**) webhook endpoint regisztrálása
      (`.../api/webhooks/stripe.php`, a `payment_intent.succeeded`/`payment_intent.payment_failed`/
      `charge.refunded` eseményekre) → signing secret bemásolása a Fizetés oldalra, Mentés.
- [ ] **Valódi teszt-rendelés küldése** a `webshop.php` végpontnak (curl-lel vagy a webshop
      checkout-folyamatán keresztül, ha az már kész) — ez a teljes rendelési lánc igazi tesztje
      (a Rendszerteszt "Teszt mód"-ja csak a Kvikk/Stripe kapcsolatot ellenőrzi, rendelést nem hoz
      létre). Ellenőrizd az admin Rendelések oldalán, hogy megjelenik.
- [ ] Stripe teszt-fizetés végigfuttatása (`4242 4242 4242 4242` kártyaszám) → ellenőrizd, hogy a
      rendelés "Fizetve" állapotba kerül, és kimegy a visszaigazoló email.
- [ ] Kvikk teszt-webhook küldése (vagy egy valódi, kis rendelés kézi feladása a Kvikk oldalán) →
      ellenőrizd, hogy "Feladva"/"Kézbesítve" frissül.
- [ ] **Amikor élesbe váltasz** (6. fázis): Fizetés oldal → **Éles** mód → éles secret/publishable
      key mentése → **Rendszerteszt "Éles teszt indítása"** — ez egy valódi, 300 Ft-os terhelést
      indít Stripe Checkouton keresztül (kártyaadat sosem érinti a szervert). Visszatéréskor
      megmutatja az eredményt, és egy gombbal azonnal vissza is térítheted.

## 5. Fázis — Tartalom, amit MOST placeholder (kötelező kicserélni éles indulás előtt)

- [ ] **Adatvédelem oldal jogi dokumentumai** — jelenleg demo-cégadatokkal vannak feltöltve
      ("Webshop Kft.", "1000 Budapest, Példa utca 1.", kitalált adószám) — ezeket valódi
      cégadatokra kell cserélni (Impresszum, ÁSZF, Adatkezelési tájékoztató, Cookie szabályzat,
      Elállási nyilatkozat), ideális esetben ügyvéd/könyvelő átnézésével.
- [ ] **Email sablonok** (7 db) — tartalmilag átnézni, feladó név/cím valós legyen.
- [ ] Admin jelszó — erős, egyedi jelszó, ne a teszteléshez használt.
- [ ] `storefront_url` beállítása (Fizetés oldal) — enélkül a "Fizetés sikertelen" email
      link nélkül megy ki.

## 6. Fázis — Váltás élesre

- [ ] Stripe: teszt → éles kulcsok, éles webhook endpoint regisztrálása (külön endpoint a Stripe
      Dashboardon, külön signing secret).
- [ ] Email küldés valódi tesztje a szerveren — a Rackhost cPanel PHP `mail()`-je általában
      működik SMTP-konfiguráció nélkül is, de ezt **ki kell próbálni élesben**, mert helyi gépen
      (Windows, nincs MTA) ez sosem küldött ki semmit — ez eddig sosem lett élesben tesztelve.
- [ ] `kvikk_default_courier` beállítása, ha Automata módot terveztek használni.
- [ ] **Kvikk Automata mód bekapcsolása csak ezután, óvatosan**: első alkalommal egy valódi, olcsó
      teszt-rendeléssel, utána a Kvikk fiókban ellenőrizve, hogy tényleg a szándékolt csomag jött
      létre — ez valódi, fizetős művelet.
- [ ] Nyitva marad (a korábbi átvilágításból): készlet nem véges pontossággal követett
      (termék-név pontos egyezés szükséges), kosár-emlékeztető email nincs bekötve, kimenő
      mezőleképezés csak a dokumentált 6 mezőre terjed ki — ezekről lásd `INTEGRATION_GUIDE.md`.

---

**Ha idáig eljutottunk**, szólj, és sorban végigmegyünk a fázisokon — a 2. fázis (MySQL-átállás)
és a webshop tényleges felépítése azok, amik tényleges fejlesztői munkát igényelnek tőlem, a többi
inkább hozzáférés/beállítás-jellegű lépés, amit együtt, valós idejű visszajelzéssel tudunk
végigcsinálni.
