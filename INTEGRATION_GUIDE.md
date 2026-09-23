# Webshop ↔ admin backend integrációs szerződés

**Ez a dokumentum a `webshop/` admin backend (Nyisd Ki Amikor) és egy hozzá kapcsolódó
webshop-frontend (storefront) közti szerződés.** Ha egy jövőbeli munkánál ezt a fájlt megkapom,
ezt veszem alapul a webshop fejlesztésekor — ez határozza meg, milyen adatot, milyen formában és
mikor kell a webshopnak elküldenie a backendnek ahhoz, hogy minden funkció (készlet, email,
kupon, Kvikk-csomagolás, Stripe-fizetés) valóban, hibamentesen működjön.

A backend forrása és a pontos mezőnevek mindig irányadóbbak, mint ez a leírás, ha eltérés van —
de ha ez a fájl és a kód szétdriftel, azt jelezni kell, nem csendben az egyiket követni.

---

## 1. Architektúra dióhéjban

A webshop (storefront) és ez az admin backend **két külön rendszer**, ami kizárólag HTTP-n
keresztül, előre meghatározott szerződések (ez a dokumentum) alapján kommunikál:

```
Vevő böngészője → Webshop (storefront + saját backend) → Admin backend (ez a repo)
                                                          ↘ Kvikk (szállítás)
                                                          ↘ Stripe (fizetés)
```

- A **webshop saját backendjének** kell hívnia ezt az admin backendet (sosem a vevő böngészőjének
  közvetlenül) — a hitelesítő API-kulcs kliens-oldalon SOHA nem szerepelhet.
- A **Stripe és a Kvikk közvetlenül** ezt az admin backendet hívja webhookon, a webshop kódja nem
  áll közéjük (kivéve a Stripe fizetés elindítását, ami a webshop oldalán történik).

---

## 2. Autentikáció

| Irány | Mechanizmus |
|---|---|
| Webshop → admin backend (`/api/webhooks/webshop.php`) | `X-API-Key` fejléc, értéke a Beállítások oldalon generált `site_api_key`. |
| Stripe → admin backend (`/api/webhooks/stripe.php`) | `Stripe-Signature` fejléc, a Stripe Dashboardon regisztrált webhook signing secret ellen ellenőrizve. |
| Kvikk → admin backend (`/api/webhooks/kvikk.php`) | `kvikk-webhook-signature` fejléc, a Kvikk fiókban generált webhook-titok ellen ellenőrizve. |

**Fontos**: helyi fejlesztéskor, ha a Stripe/Kvikk webhook-titok még üres a beállításokban, az
aláírás-ellenőrzés kihagyásra kerül (csak naplózva). Éles bevezetés előtt MINDKÉT titkot ki kell
tölteni a Szállítás / Fizetés oldalon — enélkül bárki, aki ismeri a végpont URL-jét, tudna hamis
eseményeket küldeni.

---

## 3. Új rendelés beküldése — `POST /api/webhooks/webshop.php`

Ez a webshop **saját, itt definiált** kontraktusa (nincs külső platform-elvárás, mint a
Stripe/Kvikk esetén) — ezt a webshop backendjének kell implementálnia, minden sikeres rendelés
leadásakor (kártyás és utánvétes rendelésnél egyaránt).

### Fejlécek
```
X-API-Key: <a Beállítások oldalon generált kulcs>
Content-Type: application/json
```

### Törzs (JSON mezők)

| Mező | Típus | Kötelező | Megjegyzés |
|---|---|---|---|
| `orderNumber` | string | igen | Egyedi rendelésazonosító. **Case-sensitive** — legyen mindig konzisztens formátumú (pl. mindig nagybetűs vagy mindig ugyanaz a prefix), mert két eltérő írásmód két külön rendelésnek számít. |
| `customerName` | string | igen | Teljes név. |
| `amount` | **egész szám** | igen | Forintban, **tizedesjegy és ezres elválasztó NÉLKÜL** (`12900`, nem `"12.900"` vagy `"12,900"`). A backend elutasítja, ha nem egész szám — korábban ez csendben csonkolt (`"12.900"` → 12 Ft-ra), ez már ki van javítva, de a webshop oldalán is mindig tiszta egész számot kell küldeni. |
| `paymentMethod` | `"card"` \| `"cod"` | igen | Bármi más `"card"`-ként lesz kezelve. |
| `items` | array | igen | Legalább 1 elem. Lásd lent. |
| `customerEmail` | string | ajánlott | Enélkül **nem megy ki semmilyen automatikus email** (rendelés-visszaigazolás, feladva, kézbesítve stb.), és Automata Kvikk-mód esetén a csomag sem jön létre (a Kvikk kötelezően kéri). |
| `customerPhone` | string | ajánlott | Ugyanígy kötelező az Automata Kvikk-módhoz. |
| `address` | string | ajánlott | Egybefűzött, megjelenítésre szánt cím (ez jelenik meg az admin felületen). |
| `addressLine`, `city`, `postcode`, `country` | string | **csak Automata Kvikk-módhoz kötelező** | Tagolt cím — lásd 6. pont. |
| `deliveryPointType`, `deliveryPointID` | string | opcionális | Csomagpontos kiszállításhoz, ha a webshop támogatja. |
| `courier` | string | opcionális | Az egyik Kvikk futár-slug: `mpl`, `foxpost`, `packeta`, `famafutar`, `dpd`, `gls`. Ha hiányzik vagy érvénytelen, az Automata Kvikk-mód a Szállítás oldalon beállított alapértelmezett futárt próbálja használni. |
| `stripePaymentIntentId` | string | kártyás rendelésnél ajánlott | Lásd 5. pont — enélkül a Stripe-webhook nehezebben találja meg a rendelést. |
| `couponCode` | string | opcionális | Lásd 7. pont. |

### `items[]` elemek

| Mező | Típus | Kötelező |
|---|---|---|
| `name` | string | igen — **lásd 4. pont, ez a legkritikusabb rész** |
| `qty` | pozitív egész szám | igen |
| `unit` | nem negatív egész szám (Ft, tizedesjegy nélkül) | igen |

### Válaszok

| Státusz | Jelentés |
|---|---|
| `201 {"ok":true,"orderId":N}` | Sikeresen létrehozva. |
| `200 {"ok":true,"note":"..."}` | A rendelésszám már létezik — **ezt a webshopnak sikerként kell kezelnie**, ez a retry-idempotencia része, nem hiba. |
| `400 {"error":"..."}` | Hiányzó/hibás mező — a webshopnak logolnia kell, mert ez azt jelenti, a rendelés NEM jött létre a backendben. |
| `401 {"error":"..."}` | Hibás/hiányzó API-kulcs. |

**Idempotencia**: a rendelés-létrehozás biztonságosan újraküldhető ugyanazzal az `orderNumber`-rel
(pl. hálózati hiba miatti retry) — nem hoz létre duplikátumot.

---

## 4. Termék-név egyezés — a legkritikusabb integrációs pont

Az `items[].name` értékének **karakterre pontosan** (kis/nagybetű, ékezet, szóköz is számít) meg
kell egyeznie a Termékek oldalon rögzített termék `name` mezőjével. Ez alapján történik:

- a csomag **súlyának** kiszámítása (Kvikk-díj becslés, EPR-export, Automata csomag-létrehozás),
- a **készlet lefoglalása/levonása** (`reserved`/`stock` a Termékeknél).

**Ha nem egyezik, a rendszer nem hibázik — csendben kihagyja azt a tételt** a súly- és
készletszámításból. Ez azt jelenti: egy elgépelt vagy variánsnevet tartalmazó `items[].name` miatt
a készlet sosem fogy, és az Automata Kvikk-csomag "hiányzó súly" hibával utasítja el a rendelést.

**Teendő webshop-fejlesztéskor**: a webshop terméklistáját (vagy legalább a termékneveket) a
Termékek oldallal szinkronban kell tartani, vagy — ha a webshop platform saját, eltérő
terméknévkonvenciót használ — egy explicit név-megfeleltetési réteget kell építeni a webshop
oldalán, mielőtt elküldi az `items[].name` értéket.

**Ismert korlát**: a rendszer nem kezel variáns-szintű (pl. "Alaptermék – Piros") vagy extra
(addon) szintű készlet-/súlykövetést — csak a felső szintű termékkel egyezik. Ha ez éles
probléma lenne egy adott shopnál, ezt a backend oldalán kellene bővíteni, nem workaroundolni a
webshop oldalán.

---

## 5. Stripe-integráció (kártyás fizetés)

1. A webshop a saját backendjén hozza létre a Stripe PaymentIntentet a fizetés indításakor.
2. **Kötelezően állítsd be** a PaymentIntent `metadata.order_number` mezőjét a mi
   `orderNumber`-ünkre — enélkül a `stripe.php` webhook csak a `stripe_ref` (payment intent ID)
   alapján tud párosítani, ami törékenyebb (csak akkor működik, ha a `stripePaymentIntentId`
   mezőt is pontosan elküldted a rendelés-webhookban).
3. A webshop UI-nak a Stripe Elements/Checkout segítségével kell lebonyolítania a fizetést — ez a
   backend nem ad Stripe-frontend-integrációt, csak a webhook-fogadást.
4. Webhook endpoint, amit a Stripe Dashboardon regisztrálni kell:
   `https://<admin-domain>/api/webhooks/stripe.php`, az alábbi eseményekre feliratkozva:
   `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`.
5. A signing secretet a Beállítások → Fizetés oldalon kell elmenteni.

**Fontos, amit a webshopnak NEM kell tennie**: a rendelés-visszaigazoló emailt, a készlet-
lefoglalást és (ha Automata mód aktív) a Kvikk-csomag létrehozását a `payment_intent.succeeded`
esemény már automatikusan elindítja a backend oldalán — a webshopnak nem kell ezekre külön
hívást indítania sikeres fizetés után.

---

## 6. Kvikk-integráció (szállítás)

### Bejövő (státusz-frissítés) — nincs teendő a webshop oldalán
A Kvikk fiókban regisztrált webhook (`https://<admin-domain>/api/webhooks/kvikk.php`) automatikusan
frissíti a rendelés szállítási állapotát — ez teljesen a backend és a Kvikk közt zajlik.

### Kimenő (Automata csomag-létrehozás) — előfeltételek, ha ezt a módot használjátok
Ha a Szállítás oldalon az "Automata" mód be van kapcsolva, a backend a fizetés (kártya) vagy a
rendelés-felvétel (utánvét) után **automatikusan létrehoz egy valódi, fizetős Kvikk-csomagot**.
Ehhez a webshopnak KÖTELEZŐEN küldenie kell:
- `customerEmail` és `customerPhone` (Kvikk kötelező mezők),
- **vagy** `addressLine` + `city` + `postcode` (+ opcionális `country`, alapértelmezett `HU`) —
  a puszta `address` egybefűzött string NEM elég ehhez,
- **vagy** `deliveryPointType` + `deliveryPointID`, ha csomagpontra megy a kiszállítás,
- egy érvényes `courier` értéket (lásd 3. pont táblázata), vagy legyen beállítva alapértelmezett
  futár a Szállítás oldalon.

Ha bármelyik hiányzik, a backend NEM próbál hibás adattal csomagot létrehozni — a rendelést
`kvikk_shipment_error` mezővel jelöli, ami megjelenik az admin Szállítás oldalán és a rendelés
részletnézetében. A webshopnak ilyenkor nincs teendője, ezt az admin kézzel pótolja a Kvikk
felületén.

**Ha a webshop nem tud tagolt címet küldeni**, az Automata mód nem használható megbízhatóan — ez
esetben a Kézi mód (admin manuálisan ad fel a Kvikk oldalán) az ajánlott, ne próbáljuk kényszerrel
az egybefűzött címből tagolt címet kinyerni (cím-elemzés/parse-olás megbízhatatlan, rossz cím
esetén valós, téves szállítást eredményezhet).

---

## 7. Kuponok

Opcionális `couponCode` mező a rendelés-webhookban — ha a webshop maga validálja a kupont
checkoutkor (érvényesség, limit), a beküldött kód csak a `used_count` számláló növelésére és
megjelenítésre szolgál a backend oldalán. **A backend nem validálja újra a kupont** (nem utasítja
el a rendelést érvénytelen/lejárt kód esetén) — ezt a webshop saját checkout-logikájának kell
elvégeznie, mielőtt elküldi a rendelést.

---

## 8. Adat-mezőleképezés — ha a webshop más mezőneveket használ

Ha a webshop platformja natívan más mezőneveket ad (pl. `order_id` az `orderNumber` helyett), NEM
kell a webshop oldalán átalakító réteget írni — a Beállítások oldal "Adat-mezőleképezés" táblája
ezt a backend oldalán oldja meg 6 mezőre: vevőnév, rendelésszám, összeg, email, telefon, cím.
**Csak akkor éri meg ezt használni, ha a webshop platform natívan, fixen más neveket ad** — ha a
webshopot mi írjuk/kontrolláljuk, egyszerűbb egyből a natív neveket (3. pont táblázata) küldeni.

A mezőleképezés **nem terjed ki** az `items`, `paymentMethod`, `courier`, `couponCode` mezőkre —
ezeket mindig a natív néven kell küldeni.

---

## 9. Email-sablon változók

A 6 automatikusan kiküldött sablon (Emailek oldal) az alábbi placeholdereket használja — ha a
webshop-integráció során egyedi sablon-szöveget kér az ügyfél, ezekkel a változónevekkel dolgozz:

`{{vevo_nev}}`, `{{rendelesszam}}`, `{{osszeg}}`, `{{nyomkovetesi_szam}}`, `{{fizetes_link}}`
(csak a "Fizetés sikertelen" sablonnál, a Beállítások → Fizetés oldalon megadott
`storefront_url`-ből jön).

Egy sablon csak akkor megy ki, ha az Emailek oldalon "Aktív" — ha egy webshop-integrációnál egy
adott értesítést nem szeretne az ügyfél, elég inaktiválni a sablont, nem kell kódot módosítani.

**"Kosár-emlékeztető" sablon**: ez NINCS bekötve semmilyen automatizmushoz (nincs cron/időzített
feladat a rendszerben, és a webshopnak sem kell/tud erről jelenteni). ha ez a funkció valódi
igény, azt külön kell megtervezni (időzített feladat + kosár-állapot webhook a webshop oldaláról).

---

## 10. Amit a webshop frontendje SOSEM tehet

- **Sosem hívhatja közvetlenül, böngészőből** az admin backend `/api/webhooks/webshop.php`
  végpontját — az `X-API-Key` kliens-oldali kódban kompromittálódna. Ezt mindig a webshop saját,
  szerver-oldali kódjának kell hívnia, a rendelés szerver-oldali feldolgozásakor.
- **Sosem tartalmazhatja** az admin backend admin-jelszavát vagy session-cookie-ját.
- **Sosem küldhet formázott (elválasztós/tizedesjegyes) pénzösszeget** — lásd 3. pont, a backend
  most már elutasítja, de a webshop oldalán is helyesen kell generálni.

---

## 11. Fejlesztési/tesztelési tanácsok jövőbeli munkához

- Helyi teszteléskor **kerüld az ékezetes karaktereket `curl -d` parancssori argumentumban** Git
  Bash/Windows környezetben — ismert, hogy ez a kombináció csendben tönkreteheti a JSON törzs
  UTF-8 kódolását (a PHP oldal ilyenkor üres `$input`-ot lát). Használj fájlból beolvasott `-d
  @payload.json` formát, vagy PHP/Node/Python HTTP klienst ékezetes teszt-adatokhoz.
- A Kvikk `POST /shipment` (Automata csomag-létrehozás) **valódi, fizetős művelet** a Kvikk
  fiókon — sosem szabad ezt automatizált teszttel, éles API-kulccsal kipróbálni anélkül, hogy a
  felhasználó tudja és jóváhagyja. A hibaágak (hiányzó súly/cím/futár) biztonságosan tesztelhetők,
  mert azok sosem jutnak el a valódi API-hívásig.
- Webhook-újraküldés (Stripe/Kvikk retry) mindig lehetséges — minden állapotváltó webhook-ágnak
  idempotensnek kell lennie (state-átmenet ellenőrzése email-küldés előtt, ahogy ez a backend is
  teszi), különben a vevő duplikált emaileket kap.
