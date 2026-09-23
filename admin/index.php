<?php
require_once __DIR__ . '/../backend/auth.php';
require_login();
$stmt = db()->prepare('SELECT email FROM admin_users WHERE id = ?');
$stmt->execute([$_SESSION['admin_id']]);
$adminEmail = $stmt->fetchColumn() ?: '';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vezérlőpult</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">

  <nav class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-mark">W</div>
      <div class="brand-name">Webshop</div>
    </div>
    <button class="nav-item active" data-view="orders">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
      Rendelések
    </button>
    <button class="nav-item" data-view="coupons">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6a2 2 0 0 0 0-4V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4Z"/><path d="M9 6v12" stroke-dasharray="2 2"/></svg>
      Kuponok
    </button>
    <button class="nav-item" data-view="products">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.5 12.7 12.7 20.5a1.5 1.5 0 0 1-2.1 0l-7.1-7.1a1.5 1.5 0 0 1 0-2.1L11.3 3.5H18a2.5 2.5 0 0 1 2.5 2.5v6.7Z"/><circle cx="15.5" cy="8.5" r="1.3"/></svg>
      Termékek
    </button>
    <button class="nav-item" data-view="customers">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 8.5a3 3 0 1 1 3.8 2.9"/><path d="M21 20c0-2.6-1.7-4.8-4-5.6"/></svg>
      Vásárlók
    </button>
    <button class="nav-item" data-view="shipping">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="7" width="12" height="9"/><path d="M14.5 10h4l3 3v3h-7v-6Z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg>
      Szállítás
    </button>
    <button class="nav-item" data-view="payment">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="5.5" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><path d="M6 14.5h4"/></svg>
      Fizetés
    </button>
    <button class="nav-item" data-view="emails">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6.5 8.3 6.2a1.5 1.5 0 0 0 1.8 0L21.4 6.5"/></svg>
      Emailek
    </button>
    <button class="nav-item" data-view="returns">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 9h11a4.5 4.5 0 0 1 0 9H10"/><path d="m7 5-3 4 3 4"/></svg>
      Visszaküldések
    </button>
    <button class="nav-item" data-view="stats">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10"/><path d="M11 20V4"/><path d="M18 20v-7"/></svg>
      Statisztika
    </button>
    <button class="nav-item" data-view="analytics">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
      Analitika
    </button>
    <button class="nav-item" data-view="privacy">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2.5 4 5.5v6c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10v-6L12 2.5Z"/><path d="m9 12 2 2 4-4"/></svg>
      Adatvédelem
    </button>
    <button class="nav-item" data-view="settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a7.97 7.97 0 0 0 0-3l2-1.5-2-3.5-2.3.9a8.1 8.1 0 0 0-2.6-1.5L14 2h-4l-.5 2.4a8.1 8.1 0 0 0-2.6 1.5l-2.3-.9-2 3.5 2 1.5a7.97 7.97 0 0 0 0 3l-2 1.5 2 3.5 2.3-.9c.77.66 1.65 1.17 2.6 1.5L10 22h4l.5-2.4c.95-.33 1.83-.84 2.6-1.5l2.3.9 2-3.5-2-1.5Z"/></svg>
      Beállítások
    </button>
  </nav>

  <div class="main">
    <header class="topbar">
      <div class="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="global-search-input" placeholder="Keresés rendelések, vásárlók közt…" autocomplete="off">
        <div class="search-dropdown" id="search-dropdown"></div>
      </div>
      <div class="topbar-right">
        <button type="button" class="btn btn--ghost btn--small" id="open-export-btn" style="display:flex; align-items:center; gap:6px; white-space:nowrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:15px; height:15px; flex-shrink:0"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 19h16"/></svg>
          Excel export
        </button>
        <span class="topbar-date">2026.09.21.</span>
        <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Sötét/világos téma váltása">
          <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.5M12 19v2.5M4.5 12H2M22 12h-2.5M5.4 5.4l1.8 1.8M16.8 16.8l1.8 1.8M18.6 5.4l-1.8 1.8M7.2 16.8l-1.8 1.8"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.8 6.8 0 0 0 10.5 10.5Z"/></svg>
        </button>
        <div class="notif-wrap">
          <button type="button" class="bell has-unread" id="notif-bell" aria-label="Értesítések"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg></button>
          <div class="notif-dropdown" id="notif-dropdown">
            <div class="notif-head">Értesítések</div>
            <div class="notif-item">
              <span class="notif-dot pill--success"></span>
              <div><div class="notif-text">Új rendelés érkezett — #0231, Kovács Anna</div><div class="notif-time">2 órája</div></div>
            </div>
            <div class="notif-item">
              <span class="notif-dot pill--warn"></span>
              <div><div class="notif-text">Alacsony készlet: Alaptermék (7 db maradt)</div><div class="notif-time">5 órája</div></div>
            </div>
            <div class="notif-item">
              <span class="notif-dot pill--warn"></span>
              <div><div class="notif-text">Új GDPR-kérelem: kovacs.anna@email.hu</div><div class="notif-time">tegnap</div></div>
            </div>
            <button type="button" class="notif-view-all" id="notif-view-all-btn">Összes értesítés megtekintése →</button>
          </div>
        </div>
        <div class="notif-wrap">
          <button type="button" class="avatar" id="account-menu-btn" aria-label="Fiók"><?= htmlspecialchars(mb_strtoupper(mb_substr($adminEmail, 0, 1))) ?></button>
          <div class="notif-dropdown" id="account-menu-dropdown" style="right:0; width:220px;">
            <div class="notif-head" style="text-transform:none; letter-spacing:normal; font-size:12.5px;"><?= htmlspecialchars($adminEmail) ?></div>
            <a href="/logout.php" class="notif-view-all" style="display:block; text-decoration:none;">Kijelentkezés</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <div class="banner">Helyi demo nézet — a menüpontok szerkezete, még nincs backend-kapcsolat.</div>

      <!-- RENDELÉSEK -->
      <section id="orders" class="view active">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">7 nyitott</span>
            <h1 class="view-title">Rendelések</h1>
          </div>
          <div class="view-meta">összesen 231 rendelés</div>
        </div>
        <div class="card">
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Rendelés</th><th>Vevő</th><th class="num">Összeg</th><th>Fizetés</th><th>Szállítás</th><th>Számla</th><th>Dátum</th></tr>
            </thead>
            <tbody id="orders-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">Kattints egy sorra a rendelés részleteiért. Az egyes jelölők jelentését lásd lent.</p>
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-top:20px">
          <h2 style="font-size:15px; font-weight:700; margin:0 0 4px">Az állapotok jelentése</h2>
          <p class="empty-note" style="padding:0 0 18px">Melyik jelzés mit jelent, és mikor jelenik meg egy rendelésnél.</p>

          <div class="status-legend-grid">
            <div>
              <p class="modal-section-label">Fizetés</p>
              <div class="status-legend-item">
                <span class="pill pill--success">Fizetve</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--stripe">Stripe</span>
                  <span>Kártyás rendelésnél: sikeres Stripe-fizetés érkezett.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--warn">Függőben</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--stripe">Stripe</span>
                  <span>Kártyás rendelésnél: a vevő még nem fizetett.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--danger">Lemondva</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>A rendelést törölték, nincs vele tovább teendő.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--warn">Utánvét — kézbesítéskor</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>Utánvétes rendelésnél: a pénz még nem érkezett be, a futár majd kézbesítéskor szedi be.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--success">Utánvét beszedve</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kvikk">Kvikk</span>
                  <span>Utánvétes rendelésnél: a futár átvette a pénzt kézbesítéskor — a Kvikk "delivered" eseménye igazolja.</span>
                </div>
              </div>
            </div>

            <div>
              <p class="modal-section-label">Szállítás</p>
              <div class="status-legend-item">
                <span class="pill pill--warn">Csomagolás alatt</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>A rendelés ki van fizetve, de még nem küldted el a csomagadatokat a Kvikknek.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--warn">Vár fizetésre</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--stripe">Stripe</span>
                  <span>Még nincs fizetve, ezért a szállítás sem indulhat el.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--neutral">Feladva</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kvikk">Kvikk</span>
                  <span>A futár átvette a csomagot — automatikusan frissül majd a Kvikk webhookjából.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--success">Kézbesítve</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kvikk">Kvikk</span>
                  <span>Megérkezett a vevőhöz, a rendelés lezárult — szintén Kvikk-vezérelt.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--danger">—</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>Lemondott rendelésnél nincs értelme szállítási állapotnak.</span>
                </div>
              </div>
            </div>

            <div>
              <p class="modal-section-label">Számla</p>
              <div class="status-legend-item">
                <span class="pill pill--success">Kiállítva</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>A rendelés részletnézetében bejelölted és elmentetted, hogy a kézi számla elkészült és a csomagba került.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--warn">Nincs kiállítva</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>Még nincs megjelölve — nyisd meg a rendelést, és pipáld be, ha kész a számla.</span>
                </div>
              </div>
              <div class="status-legend-item">
                <span class="pill pill--neutral">—</span>
                <div class="legend-text">
                  <span class="source-tag source-tag--kezi">Kézi</span>
                  <span>Lemondott rendelésnél nem kell számla.</span>
                </div>
              </div>
            </div>
          </div>

          <p class="empty-note" style="margin-top:18px; padding:12px 14px; border:1px dashed var(--border); border-radius:8px; display:flex; flex-direction:column; gap:6px">
            <span><span class="source-tag source-tag--kvikk">Kvikk</span> — a szállítási és a Kvikk fiókból induló webhook automatikusan frissíti majd, amint elkészül a valódi backend (feladás, kézbesítés, utánvét-beszedés).</span>
            <span><span class="source-tag source-tag--stripe">Stripe</span> — kártyás rendelésnél a fizetési állapotot a Stripe visszaigazolása állítja automatikusan (sikeres fizetés / még nem fizetett).</span>
            <span><span class="source-tag source-tag--kezi">Kézi</span> — te magad állítod be az adminban (pl. lemondás, kézi számla kiállítása, utánvét-csomagolás elindítása) — ezekhez nincs automatikus külső jelzés.</span>
          </p>
        </div>
      </section>

      <!-- KUPONOK -->
      <section id="coupons" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">3 aktív</span>
            <h1 class="view-title">Kuponok</h1>
          </div>
          <button class="btn" id="new-coupon-btn">+ Új kupon</button>
        </div>
        <div class="card">
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Kód</th><th>Kedvezmény</th><th>Érvényes</th><th class="num">Felhasználva</th><th>Állapot</th><th></th></tr>
            </thead>
            <tbody id="coupons-tbody">
            </tbody>
          </table></div>
        </div>

        <div class="modal-overlay" id="coupon-modal">
          <div class="modal-card">
            <div class="modal-head">
              <h2 class="modal-title">Új kupon</h2>
              <button type="button" class="modal-close" id="coupon-modal-close" aria-label="Bezárás">&times;</button>
            </div>
            <form id="coupon-form" class="modal-form">
              <div class="modal-body">
                <div class="field-grid" style="max-width:none">
                  <div class="field">
                    <label for="coupon-code">Kód</label>
                    <input type="text" id="coupon-code" placeholder="pl. NYAR20" required style="text-transform:uppercase">
                  </div>
                  <div class="field">
                    <label for="coupon-discount-type">Kedvezmény típusa</label>
                    <select id="coupon-discount-type">
                      <option value="percent">Százalék — minden termékre</option>
                      <option value="fixed">Fix összeg (Ft)</option>
                    </select>
                  </div>
                  <div class="field">
                    <label for="coupon-discount-value" id="coupon-discount-value-label">Mérték (%)</label>
                    <input type="number" id="coupon-discount-value" placeholder="pl. 10" min="1" required>
                  </div>
                  <div class="field">
                    <label for="coupon-from">Érvényes ettől</label>
                    <input type="date" id="coupon-from">
                    <label class="checkbox-item" style="border:none; padding:6px 0 0; margin:0;">
                      <input type="checkbox" id="coupon-start-now" checked>
                      <span style="font-size:12.5px; color:var(--text-soft); cursor:pointer;">Azonnal induljon (mai naptól)</span>
                    </label>
                  </div>
                  <div class="field">
                    <label for="coupon-until">Érvényes eddig</label>
                    <input type="date" id="coupon-until">
                  </div>
                  <div class="field">
                    <label for="coupon-limit">Felhasználási limit (összesen)</label>
                    <input type="number" id="coupon-limit" placeholder="üresen = korlátlan" min="1">
                  </div>
                  <div class="field">
                    <label for="coupon-limit-per-email">Felhasználás email-enként</label>
                    <input type="number" id="coupon-limit-per-email" placeholder="üresen = korlátlan" min="1">
                  </div>
                  <div class="field">
                    <label for="coupon-status">Állapot</label>
                    <select id="coupon-status">
                      <option value="success">Aktív</option>
                      <option value="danger">Lejárt</option>
                      <option value="neutral">Inaktív</option>
                    </select>
                  </div>
                </div>
                <div class="field" style="margin-bottom:16px">
                  <label for="coupon-note">Belső megjegyzés (csak neked, a vevő nem látja)</label>
                  <textarea id="coupon-note" rows="2" placeholder="pl. kinek/miért hoztad létre, meddig szánod használni…"></textarea>
                </div>
                <p class="modal-section-label">Termékkizárás</p>
                <p class="empty-note" style="padding:0 0 8px">A kijelölt termékekre nem érvényes ez a kupon (pl. akciós vagy alacsony árréses termékek kizárása).</p>
                <div class="checkbox-list" id="coupon-exclude-list"></div>
              </div>
              <div class="modal-actions">
                <button type="button" class="btn btn--ghost" id="coupon-cancel">Mégsem</button>
                <button type="submit" class="btn" id="coupon-submit-btn">Kupon mentése</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- TERMÉKEK (katalógus + raktár egyben) -->
      <section id="products" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">termék + raktárkészlet egy helyen</span>
            <h1 class="view-title">Termékek</h1>
          </div>
          <button class="btn" id="new-product-btn">+ Új termék</button>
        </div>
        <div class="card">
          <div style="display:flex; justify-content:flex-end; padding:14px 20px 0">
            <label class="checkbox-item" style="border:none; padding:0; width:auto;">
              <input type="checkbox" id="show-archived-toggle">
              <span style="font-size:12.5px; color:var(--text-soft); cursor:pointer;">Archivált termékek mutatása</span>
            </label>
          </div>
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Kép</th><th>Termék</th><th class="num">Ár</th><th class="num">Készleten</th><th class="num">Foglalt</th><th class="num">Elérhető</th><th>Módosítás</th><th></th></tr>
            </thead>
            <tbody id="inventory-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">A "Foglalt" oszlop a fizetésre váró és csomagolás alatt lévő rendelésekből számolódik. A "Csomagolóanyag" típusú tételek (pl. Doboz) nem jelennek meg eladható termékként a shopban, áruk sem kötelező — csak a raktárkészletüket követjük, mert más termékek rendelésekor automatikusan fogynak.</p>
        </div>
      </section>

      <!-- VÁSÁRLÓK -->
      <section id="customers" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">89 vásárló</span>
            <h1 class="view-title">Vásárlók</h1>
          </div>
        </div>
        <div class="card">
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Név</th><th>Email</th><th>Telefon</th><th class="num">Rendelés</th><th>Utolsó vásárlás</th></tr>
            </thead>
            <tbody id="customers-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">Kattints egy vásárlóra a rendelési előzményeinek megtekintéséhez.</p>
        </div>
      </section>

      <!-- SZÁLLÍTÁS -->
      <section id="shipping" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">külső rendszer — Kvikk API</span>
            <h1 class="view-title">Szállítás</h1>
          </div>
          <div class="mode-toggle" id="shipping-mode-toggle">
            <button type="button" class="mode-btn" data-mode="manual">Kézi</button>
            <button type="button" class="mode-btn" data-mode="auto">Automata</button>
          </div>
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-bottom:20px">
          <p class="modal-section-label" style="margin:0 0 10px">Kézi vs. Automata — mi a különbség?</p>
          <div class="two-col-compare">
            <div>
              <p style="font-weight:700; font-size:13.5px; margin:0 0 8px">Kézi mód</p>
              <p class="empty-note" style="padding:0">Minden egyes rendelésnél Te magad lépsz be a Kvikk oldalára, ott hozod létre a csomagot (futár kiválasztása, súly, cím megadása), és onnan nyomtatod ki a címkét. Az admin csak annyit mutat, hogy egy kifizetett rendelés "Csomagolás alatt" van — a tényleges feladás a Kvikk oldalán történik, kézzel, egyesével.</p>
            </div>
            <div>
              <p style="font-weight:700; font-size:13.5px; margin:0 0 8px">Automata mód</p>
              <p class="empty-note" style="padding:0">A backend magától elküldi az adatokat a Kvikknek, amint egy rendelés kifizetésre kerül (kártyás fizetésnél) vagy beérkezik (utánvétnél) — nincs kézi másolgatás, a csomag és a nyomkövetési szám azonnal létrejön. Feltétele, hogy MINDEN eladható terméknek legyen megadva a súlya a Termékeknél, és hogy a webshop tagolt címet (utca/város/irányítószám) is küldjön, különben a Kvikk API hibát ad.</p>
            </div>
          </div>
          <p class="empty-note" style="margin-top:16px; padding:12px 14px; border:1px dashed var(--border); border-radius:8px">A jobb fenti kapcsolóval bármikor válthatsz — a váltás nem érint már elküldött csomagokat, csak azt dönti el, hogy az <strong>ezután</strong> érkező új rendeléseket ki adja fel a Kvikknek: Te (Kézi) vagy a rendszer (Automata). A kettő akár keverve is előfordulhat, ha menet közben váltasz.</p>
        </div>

        <div class="card" style="padding:18px 22px; margin-bottom:20px">
          <p class="modal-section-label" style="margin:0 0 10px">Gyorselérés a Kvikk felületén</p>
          <div style="display:flex; gap:10px; flex-wrap:wrap">
            <a class="btn btn--ghost" href="https://app.kvikk.hu" target="_blank" rel="noopener">+ Új csomag/rendelés a Kvikknél →</a>
            <a class="btn btn--ghost" href="https://app.kvikk.hu" target="_blank" rel="noopener">Csomagok listája →</a>
            <a class="btn btn--ghost" href="https://app.kvikk.hu" target="_blank" rel="noopener">Címkék / nyomtatás →</a>
            <a class="btn btn--ghost" href="https://app.kvikk.hu" target="_blank" rel="noopener">Visszaküldések portál →</a>
          </div>
          <p class="empty-note" style="padding:8px 0 0">Ezek egyelőre mind a Kvikk főoldalára visznek — a saját fiókodon belül onnan éred el a konkrét almenüt. Ha a Kvikk ad közvetlen linket egy-egy funkcióhoz, ide be tudjuk illeszteni pontosan.</p>
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-bottom:20px">
          <div class="view-head" style="margin-bottom:8px">
            <div>
              <span class="view-eyebrow">valódi API-hívás — nem szimuláció</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Kvikk kapcsolat ellenőrzése</h2>
            </div>
            <span class="pill pill--neutral" id="kvikk-connection-pill">Még nem tesztelve</span>
          </div>
          <p class="empty-note" style="padding:0 0 10px">Ez ténylegesen lekérdezi a Kvikk API-t a szerveren tárolt API-kulccsal (<span class="mono">GET /v1/account-details</span>) — visszaadja az aktív futárokat és a feladó adataidat, hogy lásd, valóban működik-e a kapcsolat.</p>
          <div class="order-detail-box" id="kvikk-connection-detail">Még nem futott le ellenőrzés.</div>
          <div class="settings-save-row" style="margin-top:10px">
            <button type="button" class="btn btn--ghost btn--small" id="test-kvikk-connection-btn">Kapcsolat tesztelése</button>
          </div>
          <div class="field" style="max-width:420px; margin-top:16px">
            <label for="kvikk-webhook-secret">Kvikk webhook-titok (a Kvikk fiókodban, webhook létrehozásakor kapod)</label>
            <input type="password" id="kvikk-webhook-secret" placeholder="csak egyszer mutatja a Kvikk, mentsd el biztonságos helyre is">
          </div>
          <div class="settings-save-row" style="margin-top:8px">
            <span class="save-status" id="kvikk-webhook-secret-save-status"></span>
            <button type="button" class="btn btn--ghost btn--small" id="kvikk-webhook-secret-save-btn">Webhook-titok mentése</button>
          </div>
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-bottom:20px">
          <div class="view-head" style="margin-bottom:8px">
            <div>
              <span class="view-eyebrow">a feltöltött Kvikk-árlistából, 2026.09.01–2026.09.30</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Kvikk szállítási díjak — házhoz szállítás</h2>
            </div>
            <span class="pill pill--success">Aktív szolgáltatók</span>
          </div>
          <p class="empty-note" style="padding:0 0 6px">Az első két oszlop (Súly, Az én áram) rögzített, jobbra görgetve látod az összes Kvikk-féle kiszállítási módot (házhoz, csomagpont, automata, postán maradó stb.) a tényleges költséggel — zölddel, ha ennél olcsóbban adod a szállítást a vevőnek mint amennyibe neked kerül (nyereség), pirossal, ha drágábban kerül neked, mint amennyit a vevőtől kérsz (veszteség). Az "Az én áram" mezőket setupkor a webshopodból húzzuk be automatikusan, de itt bármikor módosíthatod. A FámaFutár nálad még nincs aktiválva a Kvikk fiókban, ezért nem szerepel itt.</p>
          <div class="table-scroll">
            <table class="kvikk-price-table">
              <thead>
                <tr>
                  <th>Súly</th>
                  <th>Az én áram (Ft)</th>
                  <th class="num">MPL házhoz</th>
                  <th class="num">MPL Postán maradó</th>
                  <th class="num">MPL Postapont</th>
                  <th class="num">MPL Automata</th>
                  <th class="num">Foxpost házhoz</th>
                  <th class="num">Foxpost Z-Pont/Z-Box</th>
                  <th class="num">DPD házhoz</th>
                  <th class="num">DPD Csomagpont</th>
                  <th class="num">GLS házhoz</th>
                  <th class="num">GLS CsomagPont</th>
                  <th class="num">GLS Automata</th>
                </tr>
              </thead>
              <tbody id="kvikk-weight-tiers-tbody"></tbody>
            </table>
          </div>
          <p class="empty-note" style="padding:10px 0 0">A Packeta aktív a Kvikk fiókodban, de a feltöltött árlistában nincs hozzá belföldi "házhoz" tétel (csak nemzetközi) — add meg kézzel, ha van rá konkrét díjad:</p>
          <div class="field" style="max-width:260px; margin-top:8px">
            <label for="kvikk-price-packeta">Packeta (Ft / csomag, kézzel)</label>
            <input type="number" id="kvikk-price-packeta" min="0" value="890">
          </div>
          <div class="settings-save-row" style="margin-top:10px">
            <span class="save-status" id="kvikk-prices-save-status"></span>
            <button type="button" class="btn btn--ghost btn--small" id="kvikk-prices-save-btn">Packeta-ár mentése</button>
          </div>
          <p class="modal-section-label" style="margin-top:18px">Utánvét-díj (a rendelés értékéből számolva)</p>
          <p class="empty-note" style="padding:0 0 8px">Az utánvét díja nem fix összeg, hanem alapdíj + a rendelés értékének egy százaléka, futáronként eltérő — ez is a feltöltött árlistából jön, és automatikusan számolódik a rendelés végösszege alapján.</p>
          <div class="table-scroll">
            <table>
              <thead><tr><th>Rendelésérték</th><th class="num">MPL</th><th class="num">Foxpost</th><th class="num">DPD</th><th class="num">GLS</th></tr></thead>
              <tbody id="kvikk-cod-tiers-tbody"></tbody>
            </table>
          </div>
          <p class="empty-note" style="margin-top:14px; padding:12px 14px; border:1px dashed var(--border); border-radius:8px">Igen, ez a fenti két táblázat pontosan kiolvasható a Kvikk API-jából élesben — nem kell kézzel átvezetni. Az, hogy melyik futár <strong>aktív</strong> a fiókodban, biztosan lekérdezhető API-n; magát az <strong>árlistát</strong> egyes futárszolgáltatóknál (pl. Kvikk) rendszeresen frissítik és letölthető PDF/export formában adják ki — ha az API közvetlenül is adja az aktuális árakat, azt is automatikusan idehúzzuk, ha nem, akkor időszakonként (pl. havonta) frissítjük ugyanígy, ahogy most is: feltöltöd az új árlistát, mi beépítjük.</p>
        </div>

        <div class="card kvikk-panel shipping-panel active" id="shipping-panel-manual">
          <div class="kvikk-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="7" width="12" height="9"/><path d="M14.5 10h4l3 3v3h-7v-6Z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg>
          </div>
          <h2 class="kvikk-title">Csomagfeladás a Kvikk-en, kézzel</h2>
          <p class="kvikk-desc">Amikor becsomagoltad és lemérted a rendelést, a Kvikk saját felületén hozod létre a csomagot (futárválasztás, címke, nyomkövetés) — mi csak átküldjük oda a rendelés adatait, nem építjük újra itt.</p>
          <a class="btn" href="https://app.kvikk.hu" target="_blank" rel="noopener">Tovább a Kvikkre →</a>
        </div>

        <div class="card shipping-panel" id="shipping-panel-auto" style="padding:22px 24px 26px">
          <h2 style="font-size:15px; font-weight:700; margin:0 0 10px">Automata csomag-létrehozás fizetés után</h2>
          <p class="kvikk-desc" style="margin-bottom:16px">Sikeres Stripe-fizetés után a backend azonnal meghívja a Kvikk <span class="mono" style="background:var(--bg); padding:1px 6px; border-radius:4px">POST /v1/shipment</span> végpontját, és létrehozza a csomagot — nincs kézi lépés, a nyomkövetési szám és a címke (PDF) azonnal elérhető lesz a rendelésnél.</p>
          <div class="test-mode-banner active" style="background:var(--info-bg); color:var(--info-text)">
            <strong>Fontos:</strong> a Kvikk API-ban a <strong>súly kötelező mező</strong> minden csomagnál (<span class="mono">missing_weight</span> hibát ad, ha hiányzik). Automata módhoz minden eladható terméknél meg kell adni egy súlyt a Termékeknél — <span id="shipping-missing-weight-count">ellenőrzés…</span>
          </div>
          <p class="modal-section-label" style="margin-top:18px">Amit a backend automatikusan átad a Kvikknek</p>
          <ul style="margin:0; padding-left:20px; font-size:13px; color:var(--text-soft); line-height:1.9">
            <li>Vevő neve, telefonszáma, email címe, szállítási címe — a rendelésből (a webshopnak <span class="mono">addressLine</span>/<span class="mono">city</span>/<span class="mono">postcode</span> mezőkben tagoltan is el kell küldenie, az egybefűzött cím-szöveg ehhez nem elég)</li>
            <li>Rendelésazonosító (<span class="mono">orderID</span>) — így párosítható vissza a mi rendelésünkhöz</li>
            <li>Csomag súlya — a megrendelt termék(ek) Termékeknél megadott súlya alapján, összesítve</li>
            <li>Utánvét összege (<span class="mono">cod</span>) — 0, ha kártyával fizetett Stripe-on</li>
            <li>Futár — a rendelésben megadott futár, vagy ha az nem érvényes Kvikk-futárkód, az alábbi alapértelmezett</li>
          </ul>
          <div class="field" style="max-width:260px; margin-top:16px">
            <label for="kvikk-default-courier">Alapértelmezett futár (ha a rendelés nem ad meg érvényeset)</label>
            <select id="kvikk-default-courier">
              <option value="">— nincs megadva —</option>
              <option value="mpl">MPL</option>
              <option value="foxpost">Foxpost</option>
              <option value="packeta">Packeta</option>
              <option value="famafutar">FámaFutár</option>
              <option value="dpd">DPD</option>
              <option value="gls">GLS</option>
            </select>
          </div>
          <div class="settings-save-row" style="margin-top:8px">
            <span class="save-status" id="kvikk-default-courier-save-status"></span>
            <button type="button" class="btn btn--ghost btn--small" id="kvikk-default-courier-save-btn">Mentés</button>
          </div>
          <p class="modal-section-label" style="margin-top:18px">Legutóbbi automata csomag-létrehozási hibák</p>
          <p class="empty-note" style="padding:0 0 8px">Ha egy rendelésnél nem sikerül automatikusan létrehozni a Kvikk-csomagot (pl. hiányzó súly vagy cím), itt jelenik meg — a rendelést ilyenkor kézzel kell feladni a Kvikk oldalán.</p>
          <div id="kvikk-shipment-errors-list"><p class="empty-note">Nincs hibás automata csomag-létrehozás.</p></div>
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-top:20px">
          <div class="view-head" style="margin-bottom:14px">
            <div>
              <span class="view-eyebrow">kézi és automata módban is fontos</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Egyeztetés: rendelések ↔ Kvikk csomagok</h2>
            </div>
            <button type="button" class="btn btn--ghost btn--small" id="shipping-reconcile-refresh">Frissítés</button>
          </div>
          <p class="empty-note" style="padding:0 0 6px">Akár kézi, akár automata módban vagy, időnként érdemes leellenőrizni, hogy minden aktív rendeléshez tartozik-e ténylegesen létrehozott csomag a Kvikknél — hogy ne maradjon le véletlenül egy feladás.</p>
          <p class="empty-note" style="padding:0 0 14px; font-style:italic">Az "Aktív rendelés" a másik három szám összege: még nem fizetve + még nincs csomagolva + már feladva/kézbesítve.</p>
          <div class="stat-row" style="margin-bottom:0" id="shipping-reconcile-stats"></div>
          <div id="shipping-reconcile-note"></div>
        </div>
      </section>

      <!-- VISSZAKÜLDÉSEK -->
      <section id="returns" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">külső rendszer + Stripe</span>
            <h1 class="view-title">Visszaküldések</h1>
          </div>
        </div>
        <div class="card kvikk-panel">
          <div class="kvikk-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 9h11a4.5 4.5 0 0 1 0 9H10"/><path d="m7 5-3 4 3 4"/></svg>
          </div>
          <h2 class="kvikk-title">Visszaküldés a Kvikk portálján</h2>
          <p class="kvikk-desc">A vevő a Kvikk saját, testreszabható visszaküldési portálján kezdeményezi a visszaküldést, a visszáru-címkét is ő generálja — ezt nem építjük újra. A pénz visszautalása viszont a mi dolgunk: ha megérkezett a visszaküldött csomag, a rendelés részletnézetében a "Visszatérítés a Stripe-nál" gombbal indítható az összeg visszautalása.</p>
          <a class="btn" href="https://app.kvikk.hu" target="_blank" rel="noopener">Tovább a Kvikk visszaküldési portáljára →</a>
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-top:20px">
          <div class="view-head" style="margin-bottom:14px">
            <div>
              <span class="view-eyebrow">Kvikk + Stripe egyeztetés</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Egyeztetés: Kvikk visszaküldés ↔ Stripe visszatérítés</h2>
            </div>
            <button type="button" class="btn btn--ghost btn--small" id="returns-reconcile-refresh">Frissítés</button>
          </div>
          <p class="empty-note" style="padding:0 0 6px">Amikor a Kvikknél megérkezik egy visszaküldött csomag, nekünk kell elindítani a Stripe-visszatérítést — itt látszik, hogy a kettő ténylegesen egyezik-e, vagy van-e olyan visszaküldés, aminél elmaradt a pénz visszautalása.</p>
          <p class="empty-note" style="padding:0 0 14px; font-style:italic">Az "Összes visszaküldés" a másik három szám összege: még úton van + megérkezett, de nincs visszatérítve + már visszatérítve.</p>
          <div class="stat-row" style="margin-bottom:0" id="returns-reconcile-stats"></div>
          <div id="returns-reconcile-note"></div>
        </div>

        <div class="card">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">Visszaküldések listája</h2>
          </div>
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Dátum</th><th>Rendelés</th><th>Vevő</th><th class="num">Összeg</th><th>Kvikk állapot</th><th>Stripe visszatérítés</th></tr>
            </thead>
            <tbody id="returns-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">Ez a lista majd a Kvikk visszaküldés-webhookjából töltődik automatikusan — amint egy csomag "visszaküldve" állapotba kerül, itt megjelenik, és amíg nincs hozzá Stripe-visszatérítés, a fenti egyeztetés jelzi.</p>
        </div>
      </section>

      <!-- FIZETÉS -->
      <section id="payment" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">Stripe</span>
            <h1 class="view-title">Fizetés</h1>
          </div>
          <div class="mode-toggle" id="stripe-mode-toggle">
            <button type="button" class="mode-btn" data-mode="test">Teszt mód</button>
            <button type="button" class="mode-btn" data-mode="live">Éles mód</button>
          </div>
        </div>

        <div class="test-mode-banner" id="test-mode-banner">
          <strong>Teszt mód aktív</strong> — nem történik valódi pénzmozgás. Próbáld ki Stripe teszt-kártyával: <span class="mono">4242 4242 4242 4242</span>, bármilyen jövőbeli lejárat és CVC.
        </div>

        <div class="card" style="padding:22px 24px 26px; margin-bottom:20px">
          <div class="view-head" style="margin-bottom:14px">
            <div>
              <span class="view-eyebrow" id="stripe-keys-mode-label">Éles kulcsok</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Stripe API-kulcsok</h2>
            </div>
            <span class="pill pill--warn" id="stripe-connection-pill">Még nincs API-kulcs beállítva</span>
          </div>
          <div class="field-grid" style="max-width:none">
            <div class="field">
              <label for="stripe-publishable-key" id="stripe-pub-label">Publishable key</label>
              <input type="text" id="stripe-publishable-key" class="mono" placeholder="pk_live_...">
              <p class="empty-note" style="padding:4px 0 0">Hol találom? <a class="stripe-help-link" data-target="apikeys" href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripe Dashboard → Fejlesztők → API-kulcsok →</a></p>
            </div>
            <div class="field">
              <label for="stripe-secret-key" id="stripe-secret-label">Secret key</label>
              <input type="password" id="stripe-secret-key" placeholder="sk_live_...">
              <p class="empty-note" style="padding:4px 0 0">Hol találom? Ugyanott, mint a Publishable key — de ezt csak egyszer mutatja meg, utána "Reveal key" gombbal kell újra előhívni. <a class="stripe-help-link" data-target="apikeys" href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Megnyitás →</a></p>
            </div>
            <div class="field">
              <label for="stripe-webhook-secret">Webhook signing secret</label>
              <input type="password" id="stripe-webhook-secret" placeholder="whsec_...">
              <p class="empty-note" style="padding:4px 0 0">Hol találom? Csak azután jelenik meg, ha már létrehoztad a webhook endpointot a lenti URL-lel — ott a "Signing secret" melletti "Reveal" gombbal. <a class="stripe-help-link" data-target="webhooks" href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Megnyitás →</a></p>
            </div>
            <div class="field">
              <label for="payment-webhook-url">Webhook endpoint URL (ezt add meg a Stripe-nál)</label>
              <input type="text" id="payment-webhook-url" class="mono" value="https://webshop.hu/webhook/stripe.php" readonly>
              <p class="empty-note" style="padding:4px 0 0">Ezt te adod meg a Stripe-nál, nem tőlük kapod: <a class="stripe-help-link" data-target="webhooks" href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe Dashboard → Fejlesztők → Webhookok → + Endpoint hozzáadása →</a></p>
            </div>
            <div class="field">
              <label for="storefront-url">Webshop URL (a "Fizetés sikertelen" emailben a újrapróbálkozás-linkhez)</label>
              <input type="text" id="storefront-url" class="mono" placeholder="https://webshop.hu/penztar">
              <p class="empty-note" style="padding:4px 0 0">Ha üresen marad, a sikertelen fizetésről szóló email is kimegy, csak a linksor üres marad benne.</p>
            </div>
          </div>
          <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap">
            <a class="btn btn--ghost" id="stripe-dashboard-link" href="https://dashboard.stripe.com/test/payments" target="_blank" rel="noopener">Megnyitás a Stripe Dashboardon →</a>
            <button type="button" class="btn btn--ghost" id="open-stripe-cli-wizard-btn">CLI csatlakozás indítása →</button>
          </div>
          <p class="empty-note" style="padding-left:0">A teszt és éles kulcsok külön vannak tárolva — a fenti kapcsoló dönti el, melyik számít most. Élesítés előtt győződj meg, hogy az Éles kulcsokat is kitöltötted, és a webhook végpontot élesben is regisztráltad a Stripe-nál.</p>
          <div class="settings-save-row">
            <span class="save-status" id="stripe-save-status"></span>
            <button type="button" class="btn" id="stripe-save-btn">Mentés</button>
          </div>
        </div>

        <div class="card">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">Legutóbbi tranzakciók</h2>
          </div>
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Dátum</th><th>Rendelés</th><th>Vevő</th><th class="num">Összeg</th><th>Mód</th><th>Státusz</th></tr>
            </thead>
            <tbody id="payment-transactions-tbody">
              <tr data-mode="live" data-stripe-ref="pi_3PliveAB12cKovacs">
                <td class="mono">09.20.</td><td class="mono"><a class="stripe-ref-link" href="https://dashboard.stripe.com/payments/pi_3PliveAB12cKovacs" target="_blank" rel="noopener">#0231 →</a></td><td>Kovács Anna</td><td class="num">12 900 Ft</td>
                <td><span class="pill pill--neutral">Éles</span></td>
                <td><span class="pill pill--success">Sikeres</span></td>
              </tr>
              <tr data-mode="live" data-stripe-ref="pi_3PliveCD34eNagyB">
                <td class="mono">09.19.</td><td class="mono"><a class="stripe-ref-link" href="https://dashboard.stripe.com/payments/pi_3PliveCD34eNagyB" target="_blank" rel="noopener">#0230 →</a></td><td>Nagy Bence</td><td class="num">9 400 Ft</td>
                <td><span class="pill pill--neutral">Éles</span></td>
                <td><span class="pill pill--success">Sikeres</span></td>
              </tr>
              <tr data-mode="live" data-stripe-ref="pi_3PliveEF56gSzaboP">
                <td class="mono">09.19.</td><td class="mono"><a class="stripe-ref-link" href="https://dashboard.stripe.com/payments/pi_3PliveEF56gSzaboP" target="_blank" rel="noopener">#0229 →</a></td><td>Szabó Petra</td><td class="num">21 800 Ft</td>
                <td><span class="pill pill--neutral">Éles</span></td>
                <td><span class="pill pill--warn">Függőben</span></td>
              </tr>
              <tr data-mode="live" data-stripe-ref="pi_3PliveGH78iHorvathE">
                <td class="mono">09.17.</td><td class="mono"><a class="stripe-ref-link" href="https://dashboard.stripe.com/payments/pi_3PliveGH78iHorvathE" target="_blank" rel="noopener">#0227 →</a></td><td>Horváth Eszter</td><td class="num">6 200 Ft</td>
                <td><span class="pill pill--neutral">Éles</span></td>
                <td><span class="pill pill--danger">Sikertelen</span></td>
              </tr>
              <tr data-mode="test" data-stripe-ref="pi_3PtestJK90lTesztE">
                <td class="mono">09.21.</td><td class="mono"><a class="stripe-ref-link" href="https://dashboard.stripe.com/test/payments/pi_3PtestJK90lTesztE" target="_blank" rel="noopener">#TEST-08 →</a></td><td>Teszt Elemér</td><td class="num">4 200 Ft</td>
                <td><span class="pill pill--warn">Teszt</span></td>
                <td><span class="pill pill--success">Sikeres</span></td>
              </tr>
              <tr data-mode="test" data-stripe-ref="pi_3PtestMN12oTesztI">
                <td class="mono">09.21.</td><td class="mono"><a class="stripe-ref-link" href="https://dashboard.stripe.com/test/payments/pi_3PtestMN12oTesztI" target="_blank" rel="noopener">#TEST-07 →</a></td><td>Teszt Ibolya</td><td class="num">2 000 Ft</td>
                <td><span class="pill pill--warn">Teszt</span></td>
                <td><span class="pill pill--danger">Sikertelen (elutasított kártya)</span></td>
              </tr>
            </tbody>
          </table></div>
          <p class="empty-note" id="payment-tx-empty" style="display:none">Nincs tranzakció ebben a módban.</p>
          <p class="empty-note">Ez a lista majd a Stripe webhookjából töltődik automatikusan, rendelésenként egy sorral.</p>
        </div>
      </section>

      <!-- EMAILEK -->
      <section id="emails" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">automatikus vásárlói értesítések</span>
            <h1 class="view-title">Emailek</h1>
          </div>
        </div>
        <div class="card" style="padding:22px 24px 26px; margin-bottom:20px">
          <div class="field-grid" style="max-width:none">
            <div class="field">
              <label for="email-sender-name">Feladó neve</label>
              <input type="text" id="email-sender-name" value="Webshop">
            </div>
            <div class="field">
              <label for="email-sender-address">Feladó email címe</label>
              <input type="email" id="email-sender-address" value="rendeles@webshop.hu">
            </div>
            <div class="field">
              <label>Küldési mód</label>
              <div class="order-detail-box">Automatikus — a szerver saját PHP <span class="mono">mail()</span> funkciója</div>
            </div>
          </div>
          <p class="empty-note" style="padding-left:0">Mivel a backend a weboldal mellé kerül fel egy cPanel-es PHP tárhelyre, a levelek automatikusan a tárhely saját levélküldő rendszerén mennek ki — nincs szükség külön SMTP-fiókra vagy API-kulcsra (Mailgun, SendGrid stb.). Ha később mégis külső levélküldő szolgáltatásra váltanátok (pl. nagyobb kiküldési mennyiség miatt), ide egy új beállítás kerülhet.</p>
          <div class="settings-save-row">
            <span class="save-status" id="email-settings-save-status"></span>
            <button type="button" class="btn" id="email-settings-save-btn">Mentés</button>
          </div>
        </div>
        <div class="card">
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Sablon</th><th>Küldés alkalma</th><th>Állapot</th><th></th></tr>
            </thead>
            <tbody id="emails-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">A {{vevo_nev}}, {{rendelesszam}}, {{osszeg}}, {{nyomkovetesi_szam}}, {{fizetes_link}} jelölők automatikusan kitöltődnek majd az adott rendelés adataival.</p>
        </div>

        <div class="modal-overlay" id="email-modal">
          <div class="modal-card modal-card--wide">
            <div class="modal-head">
              <h2 class="modal-title" id="email-modal-title">Sablon szerkesztése</h2>
              <button type="button" class="modal-close" id="email-modal-close" aria-label="Bezárás">&times;</button>
            </div>
            <form id="email-form" class="modal-form">
              <div class="modal-body">
                <div class="field" style="margin-bottom:14px">
                  <label for="email-active">Állapot</label>
                  <select id="email-active">
                    <option value="success">Aktív</option>
                    <option value="neutral">Inaktív</option>
                  </select>
                </div>
                <div class="field" style="margin-bottom:14px">
                  <label for="email-subject">Tárgy</label>
                  <input type="text" id="email-subject" required>
                </div>
                <div class="field">
                  <label for="email-body">Szöveg</label>
                  <textarea id="email-body" rows="8" required></textarea>
                </div>
                <p class="empty-note" style="padding:6px 0 0">Használható jelölők: {{vevo_nev}}, {{rendelesszam}}, {{osszeg}}, {{nyomkovetesi_szam}}, {{fizetes_link}} — ezek helyére a rendelés valódi adata kerül majd kiküldéskor.</p>
              </div>
              <div class="modal-actions">
                <button type="button" class="btn btn--ghost" id="email-cancel">Mégsem</button>
                <button type="submit" class="btn">Módosítások mentése</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- STATISZTIKA -->
      <section id="stats" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">2026 szeptember</span>
            <h1 class="view-title">Statisztika</h1>
          </div>
        </div>
        <p class="empty-note" style="padding:0 0 14px">A lenti számok élőben, a jelen demóban látható rendelésekből (#0226–#0231) és a Szállítás oldalon lévő Kvikk-árlistából számolódnak — lemondott rendelés nélkül. Élesben ugyanígy, de a teljes valódi rendelésállományból.</p>

        <div class="mode-toggle" id="stats-range-toggle" style="flex-wrap:wrap; width:fit-content; margin-bottom:14px">
          <button type="button" class="mode-btn" data-range="1d">1 nap</button>
          <button type="button" class="mode-btn" data-range="7d">1 hét</button>
          <button type="button" class="mode-btn" data-range="30d">1 hó</button>
          <button type="button" class="mode-btn" data-range="90d">3 hó</button>
          <button type="button" class="mode-btn" data-range="180d">6 hó</button>
          <button type="button" class="mode-btn" data-range="365d">1 év</button>
          <button type="button" class="mode-btn active" data-range="all">Minden idő</button>
          <button type="button" class="mode-btn" data-range="custom">Egyéni időszak</button>
        </div>
        <div class="field-grid" id="stats-range-fields" style="max-width:none; margin-bottom:14px; display:none">
          <div class="field"><label for="stats-range-from">Ettől</label><input type="date" id="stats-range-from"></div>
          <div class="field"><label for="stats-range-to">Eddig</label><input type="date" id="stats-range-to"></div>
        </div>

        <div class="stat-row" id="stats-main-row"></div>

        <p class="modal-section-label" style="margin-top:4px">Profit — a szállítási költség hatása</p>
        <div class="stat-row" id="stats-profit-row"></div>
        <p class="empty-note" id="stats-shipping-note" style="padding:0 0 20px"></p>

        <div class="card" style="margin-bottom:20px">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">Top termékek</h2>
          </div>
          <div class="table-scroll"><table>
            <thead><tr><th>Termék</th><th class="num">Eladott db</th><th class="num">Bevétel</th></tr></thead>
            <tbody id="stats-top-products-tbody"></tbody>
          </table></div>
        </div>

        <div class="card" style="margin-bottom:20px">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">Bevétel időszakonként (naponta)</h2>
          </div>
          <div class="bar-chart" id="stats-daily-chart" style="padding:16px 20px 0"></div>
          <div class="table-scroll"><table>
            <thead><tr><th>Dátum</th><th class="num">Rendelés</th><th class="num">Bevétel</th></tr></thead>
            <tbody id="stats-daily-tbody"></tbody>
          </table></div>
        </div>

        <div class="card">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">Mikor rendelnek — órás bontás</h2>
          </div>
          <p class="empty-note" style="padding:0 20px 10px">Melyik órában, melyik terméket, hányat rendeltek — segít eldönteni, mikor érdemes pl. hirdetést vagy kupont időzíteni.</p>
          <div class="bar-chart bar-chart--hourly" id="stats-hourly-chart" style="padding:16px 20px 0"></div>
          <div class="table-scroll"><table>
            <thead><tr><th>Óra</th><th>Rendelés</th><th>Termék</th><th class="num">Db</th></tr></thead>
            <tbody id="stats-hourly-tbody"></tbody>
          </table></div>
          <p class="empty-note" id="stats-peak-hour-note"></p>
        </div>
      </section>

      <!-- ANALITIKA -->
      <section id="analytics" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">követő kódok</span>
            <h1 class="view-title">Analitika</h1>
          </div>
        </div>
        <div class="card" style="padding:22px 24px 26px">
          <div class="field-grid" style="max-width:none">
            <div class="field">
              <label for="analytics-ga">Google Analytics (GA4) mérési azonosító</label>
              <input type="text" id="analytics-ga" class="mono" placeholder="G-XXXXXXXXXX" data-pill="analytics-ga-pill">
              <span class="pill pill--warn analytics-pill" id="analytics-ga-pill">Nincs beállítva</span>
            </div>
            <div class="field">
              <label for="analytics-gtm">Google Tag Manager konténer ID</label>
              <input type="text" id="analytics-gtm" class="mono" placeholder="GTM-XXXXXXX" data-pill="analytics-gtm-pill">
              <span class="pill pill--warn analytics-pill" id="analytics-gtm-pill">Nincs beállítva</span>
            </div>
            <div class="field">
              <label for="analytics-pixel">Meta (Facebook) Pixel ID</label>
              <input type="text" id="analytics-pixel" class="mono" placeholder="123456789012345" data-pill="analytics-pixel-pill">
              <span class="pill pill--warn analytics-pill" id="analytics-pixel-pill">Nincs beállítva</span>
            </div>
            <div class="field">
              <label for="analytics-clarity">Microsoft Clarity azonosító</label>
              <input type="text" id="analytics-clarity" class="mono" placeholder="abcdefghij" data-pill="analytics-clarity-pill">
              <span class="pill pill--warn analytics-pill" id="analytics-clarity-pill">Nincs beállítva</span>
            </div>
          </div>
          <p class="empty-note" style="padding-left:0">A megadott azonosítók alapján a backend automatikusan beilleszti a megfelelő követő kódrészletet a shop minden oldalának &lt;head&gt;-jébe — nem kell kézzel kódot másolgatni. Cookie-tájékoztatás szükséges hozzájuk, lásd az Adatvédelem menüpontot.</p>
          <div class="settings-save-row">
            <span class="save-status" id="analytics-save-status"></span>
            <button type="button" class="btn" id="analytics-save-btn">Mentés</button>
          </div>
        </div>
      </section>

      <!-- ADATVÉDELEM -->
      <section id="privacy" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">jogi dokumentumok + GDPR</span>
            <h1 class="view-title">Adatvédelem</h1>
          </div>
        </div>

        <div class="card" style="margin-bottom:20px">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">Jogi dokumentumok</h2>
            <button class="btn" id="new-legal-doc-btn">+ Új dokumentum</button>
          </div>
          <p class="empty-note" style="padding:0 20px 10px">Ezek a szövegek a webshop rendszerével együtt kész sablonként érkeznek majd (Impresszum, ÁSZF, stb.) — a tényleges, boltra szabott szöveget ide, ebbe a szerkesztőbe kell majd bemásolni. A <strong>"Publikálva"</strong> állapotú dokumentum szövege azonnal élesben van: amit itt módosítasz és mentesz, az ugyanabban a pillanatban változik a shop oldalán is — nincs külön közzététel-gomb.</p>
          <div style="display:flex; justify-content:flex-end; padding:0 20px 10px">
            <label class="checkbox-item" style="border:none; padding:0; width:auto;">
              <input type="checkbox" id="show-archived-docs-toggle">
              <span style="font-size:12.5px; color:var(--text-soft); cursor:pointer;">Archivált dokumentumok mutatása</span>
            </label>
          </div>
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Dokumentum</th><th>Hatályos ettől</th><th>Utolsó mentés</th><th>Állapot</th><th></th></tr>
            </thead>
            <tbody id="legal-docs-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">Ezek a szövegek jelennek majd meg a shop lábléc-linkjein (Impresszum, ÁSZF stb.). PDF-et is feltölthetsz szöveg helyett a szerkesztő ablakban. Az "Elállási nyilatkozat" a jogszabály szerinti tájékoztató szövege — ez nem azonos a Visszaküldések oldallal: ott a ténylegesen elindított visszaküldés fizikai/pénzügyi ügyintézése történik (Kvikk + Stripe), itt csak a vevőnek szóló jogi tájékoztató szövege él.</p>
        </div>

        <div class="card">
          <div class="view-head" style="padding:18px 20px 0; margin-bottom:8px">
            <h2 style="font-size:15px; font-weight:700; margin:0">GDPR-kérelmek</h2>
            <button class="btn" id="new-gdpr-request-btn">+ Új kérelem rögzítése</button>
          </div>
          <p class="empty-note" style="padding:0 20px 10px">A vevők jellemzően emailben jelzik, ha adatexportot vagy törlést kérnek a róluk tárolt adatokra — ilyenkor te magad rögzíted ide a kérést a fenti gombbal (vevő email, kérés típusa, beérkezés dátuma), majd miután teljesítetted (pl. elküldted az adatexportot vagy törölted a vevőt), a "Teljesítve" gombbal zárod le. Ez nem automatikus — nincs olyan felület a shopban, ahonnan a vevő ezt magától elindítaná.</p>
          <div class="table-scroll"><table>
            <thead>
              <tr><th>Vevő email</th><th>Kérés típusa</th><th>Beérkezett</th><th>Állapot</th><th></th></tr>
            </thead>
            <tbody id="gdpr-requests-tbody">
            </tbody>
          </table></div>
          <p class="empty-note">A GDPR biztosítja a vásárlók jogát, hogy kikérjék vagy töröltessék a róluk tárolt adatokat — ezek a kérések itt futnak be, 30 napon belül kell reagálni rájuk.</p>
        </div>

        <div class="modal-overlay" id="gdpr-request-modal">
          <div class="modal-card">
            <div class="modal-head">
              <h2 class="modal-title">Új GDPR-kérelem rögzítése</h2>
              <button type="button" class="modal-close" id="gdpr-request-modal-close" aria-label="Bezárás">&times;</button>
            </div>
            <form id="gdpr-request-form" class="modal-form">
              <div class="modal-body">
                <div class="field">
                  <label for="gdpr-request-email">Vevő email címe</label>
                  <input type="email" id="gdpr-request-email" placeholder="pl. vevo@email.hu" required>
                </div>
                <div class="field">
                  <label for="gdpr-request-type">Kérés típusa</label>
                  <select id="gdpr-request-type">
                    <option value="Adatexport">Adatexport</option>
                    <option value="Törlési kérelem">Törlési kérelem</option>
                  </select>
                </div>
                <div class="field">
                  <label for="gdpr-request-date">Beérkezett</label>
                  <input type="date" id="gdpr-request-date" required>
                </div>
              </div>
              <div class="modal-actions">
                <button type="button" class="btn btn--ghost" id="gdpr-request-cancel">Mégsem</button>
                <button type="submit" class="btn">Kérelem rögzítése</button>
              </div>
            </form>
          </div>
        </div>

        <div class="modal-overlay" id="legal-doc-modal">
          <div class="modal-card modal-card--wide">
            <div class="modal-head">
              <h2 class="modal-title" id="legal-doc-modal-title">Dokumentum szerkesztése</h2>
              <button type="button" class="modal-close" id="legal-doc-modal-close" aria-label="Bezárás">&times;</button>
            </div>
            <form id="legal-doc-form" class="modal-form">
              <div class="modal-body">
                <div class="field" style="margin-bottom:14px">
                  <label for="legal-doc-name">Dokumentum neve</label>
                  <input type="text" id="legal-doc-name" placeholder="pl. Szállítási tájékoztató" required>
                </div>
                <div class="field-grid" style="max-width:none; margin-top:0; margin-bottom:14px">
                  <div class="field">
                    <label for="legal-doc-status">Állapot</label>
                    <select id="legal-doc-status">
                      <option value="success">Publikálva</option>
                      <option value="neutral">Piszkozat</option>
                    </select>
                  </div>
                  <div class="field">
                    <label for="legal-doc-effective">Hatályos ettől</label>
                    <input type="date" id="legal-doc-effective">
                  </div>
                  <div class="field" style="grid-column:1 / -1">
                    <label for="legal-doc-url">Megjelenik itt (URL az oldalon)</label>
                    <input type="text" id="legal-doc-url" class="mono" placeholder="pl. https://webshop.hu/aszf">
                    <p class="empty-note" style="padding:6px 0 0" id="legal-doc-url-view"></p>
                  </div>
                </div>
                <p class="empty-note" style="padding:0 0 14px"><strong>Publikálva</strong> = a szöveg azonnal élesben van a fenti URL-en, amint mentesz. <strong>Piszkozat</strong> = még nem jelenik meg a shopban, csak itt szerkeszted elő.</p>
                <div class="field" style="margin-bottom:14px">
                  <label for="legal-doc-content">Szöveg</label>
                  <textarea id="legal-doc-content" rows="9" placeholder="A dokumentum teljes szövege…"></textarea>
                </div>
                <div class="field">
                  <label for="legal-doc-file">…vagy tölts fel PDF-et szöveg helyett</label>
                  <input type="file" id="legal-doc-file" accept="application/pdf">
                  <p class="empty-note" style="padding:6px 0 0" id="legal-doc-file-name"></p>
                </div>
              </div>
              <div class="modal-actions">
                <button type="button" class="btn btn--ghost" id="legal-doc-cancel">Mégsem</button>
                <button type="submit" class="btn">Dokumentum mentése</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- BEÁLLÍTÁSOK -->
      <section id="settings" class="view">
        <div class="view-head">
          <div>
            <span class="view-eyebrow">admin</span>
            <h1 class="view-title">Beállítások</h1>
          </div>
        </div>
        <div class="placeholder-panel">
          <strong>Bolt adatai, admin fiók, GDPR</strong> — jelszóváltás, boltnév/cím, adatkezelési tájékoztató szövege és a vásárlói adatok törlési/lekérési folyamata itt lesz elérhető.
        </div>
        <div class="field-grid">
          <div class="field"><label for="settings-shop-name">Bolt neve</label><input type="text" id="settings-shop-name" value="Webshop"></div>
          <div class="field"><label for="settings-admin-email">Admin email</label><input type="email" id="settings-admin-email" value="valiczkomate@gmail.com"></div>
          <div class="field"><label for="settings-new-password">Új jelszó</label><input type="password" id="settings-new-password" placeholder="••••••••"></div>
          <div class="field"><label for="settings-domain">Domain neve</label><input type="text" id="settings-domain" placeholder="pl. webshop.hu"></div>
          <div class="field"><label>Kvikk API kulcs</label><input type="password" value="••••••••••••••••••••••••••••••" readonly></div>
        </div>
        <p class="empty-note" style="padding-left:0">A Kvikk API-kulcs a szerveroldali .env fájlban van tárolva, ide csak maszkolva kerül majd ki — sosem a böngésző oldalára. A Stripe-beállítások (API-kulcsok, teszt/éles mód) külön, a Fizetés menüpontban vannak.</p>
        <div class="settings-save-row">
          <span class="save-status" id="settings-save-status"></span>
          <button type="button" class="btn" id="settings-save-btn">Mentés</button>
        </div>

        <div class="placeholder-panel" style="margin-top:22px">
          <strong>Backend hozzáférési kulcs a shop-kapcsolathoz</strong> — ezt a kulcsot ITT generálod, és a weboldalad (storefront) kódjába kell beillesztened. Nem egy külső szolgáltatótól kapod, hanem a mi rendszerünk állítja elő — ez igazolja a backend felé, hogy a kérés tényleg a te shopodtól jön, nem egy illetéktelentől.
        </div>
        <div class="field" style="max-width:620px; margin-top:14px">
          <label for="settings-site-api-key">API kulcs</label>
          <div style="display:flex; gap:8px">
            <input type="text" id="settings-site-api-key" class="mono" value="whs_live_9f2ac47e0d1b4c6a8e3f5b2c" readonly style="flex:1">
            <button type="button" class="btn btn--ghost btn--small" id="copy-site-key-btn" style="white-space:nowrap">Másolás</button>
            <button type="button" class="btn btn--ghost btn--small" id="regenerate-site-key-btn" style="white-space:nowrap">Új kulcs generálása</button>
          </div>
          <p class="empty-note" style="padding:8px 0 0">Másold ki, és a weboldal kódjában (config fájl vagy környezeti változó) mentsd el — minden API-hívásnál ezt kell mellékelnie egy fejlécben (<span class="mono" style="background:var(--bg); padding:1px 6px; border-radius:4px">X-API-Key</span>), különben a backend elutasítja a kérést.</p>
        </div>

        <div class="card" style="max-width:620px; margin-top:22px; padding:22px 24px 26px">
          <div class="view-head" style="margin-bottom:8px">
            <div>
              <span class="view-eyebrow">bekötéskor és teszteléskor fontos</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Adat-mezőleképezés a webshoppal</h2>
            </div>
          </div>
          <p class="empty-note" style="padding:0 0 12px">A rendszer (pl. az email sablonok) fix változóneveket használ, mint <span class="mono">{{vevo_nev}}</span> vagy <span class="mono">{{rendelesszam}}</span> — de előre nem tudjuk, a te webshop-rendszered (pl. Shoprenter, WooCommerce, saját fejlesztés) pontosan milyen mezőnéven küldi ugyanezt az adatot a backendnek (lehet <span class="mono">customer_name</span>, vagy külön <span class="mono">first_name</span> + <span class="mono">last_name</span>, stb.). Amikor bekötöd és leteszteled a kapcsolatot, itt add meg, melyik saját mezőnév felel meg melyik admin-változónak — így nem számít, hogyan nevezte el a webshop rendszere, a backend mindig tudni fogja, honnan olvassa ki az adatot.</p>
          <div class="table-scroll">
            <table>
              <thead><tr><th>Admin-változó</th><th>A te webshopod mezőneve</th></tr></thead>
              <tbody id="field-mapping-tbody">
                <tr><td class="mono">{{vevo_nev}}</td><td><input type="text" class="mono" data-field="vevo_nev" placeholder="pl. customer_name vagy first_name+last_name" value="name"></td></tr>
                <tr><td class="mono">{{rendelesszam}}</td><td><input type="text" class="mono" data-field="rendelesszam" placeholder="pl. order_id" value="order_id"></td></tr>
                <tr><td class="mono">{{osszeg}}</td><td><input type="text" class="mono" data-field="osszeg" placeholder="pl. total" value="total"></td></tr>
                <tr><td class="mono">{{email}}</td><td><input type="text" class="mono" data-field="email" placeholder="pl. customer_email" value="email"></td></tr>
                <tr><td class="mono">{{telefon}}</td><td><input type="text" class="mono" data-field="telefon" placeholder="pl. customer_phone" value="phone"></td></tr>
                <tr><td class="mono">{{cim}}</td><td><input type="text" class="mono" data-field="cim" placeholder="pl. shipping_address" value="shipping_address"></td></tr>
                <tr><td class="mono">{{nyomkovetesi_szam}}</td><td><input type="text" class="mono" data-field="nyomkovetesi_szam" placeholder="ezt inkább a Kvikk adja majd, nem a webshop" value=""></td></tr>
              </tbody>
            </table>
          </div>
          <div class="settings-save-row">
            <span class="save-status" id="field-mapping-save-status"></span>
            <button type="button" class="btn" id="field-mapping-save-btn">Mentés</button>
          </div>
          <p class="empty-note" style="margin-top:14px; padding:12px 14px; border:1px dashed var(--border); border-radius:8px">Ha nem vagy biztos benne, hogyan küldi a webshopod ezeket az adatokat: az első valódi teszt-rendelésnél (lásd lent a "Rendszerteszt" gombot) meg tudjuk nézni a ténylegesen beérkező nyers adatot, és abból pontosan kiderül, mit hogyan kell leképezni — utána már csak ide kell beírni.</p>
        </div>

        <div class="field" style="max-width:620px; margin-top:22px">
          <div class="view-head" style="margin-bottom:8px">
            <label style="margin:0; font-size:12px; font-weight:600; color:var(--text-soft)">Kapcsolat állapota</label>
            <span class="pill pill--neutral" id="site-connection-pill">Még nem tesztelve</span>
          </div>
          <div class="order-detail-box" id="site-connection-detail">Még nem futott le kapcsolat-ellenőrzés. Kattints a "Kapcsolat tesztelése" gombra, miután megadtad a domain nevet fent és beillesztetted az API kulcsot a weboldal kódjába.</div>
          <div style="margin-top:10px">
            <button type="button" class="btn btn--ghost btn--small" id="test-site-connection-btn">Kapcsolat tesztelése</button>
          </div>
          <p class="empty-note" style="padding:8px 0 0">Ez majd a valódi backendnél ténylegesen lekérdezi, hogy a megadott domainről érkezett-e már sikeres, érvényes kulccsal hitelesített API-hívás — itt egy demó-szimuláció, hogy lásd, hogyan fog kinézni és viselkedni.</p>
        </div>

        <div class="field" id="ai-setup-card" style="max-width:620px; margin-top:22px">
          <div class="view-head" style="margin-bottom:8px">
            <label style="margin:0; font-size:12px; font-weight:600; color:var(--text-soft)">Automatikus alapadat-felismerés</label>
            <span class="pill pill--warn" id="ai-setup-status-pill">Előbb teszteld a kapcsolatot</span>
          </div>
          <div class="order-detail-box" id="ai-setup-detail">Ha fent a kapcsolat sikeresen csatlakozott, itt elindíthatod az automatikus felismerést: végignézi a weboldaladat, és megpróbálja kitölteni a terméklistát, a jogi dokumentumokat és a többi alapadatot kiinduló pontnak. Amit talál, utána bármikor módosíthatod, ez csak egy gyors rajtot ad.</div>
          <div style="margin-top:10px">
            <button type="button" class="btn" id="open-ai-setup-btn" disabled>Automatikus beállítás indítása →</button>
          </div>
        </div>

        <div class="field" id="system-test-card" style="max-width:620px; margin-top:22px">
          <div class="view-head" style="margin-bottom:8px">
            <label style="margin:0; font-size:12px; font-weight:600; color:var(--text-soft)">Rendszerteszt — végpontok ellenőrzése</label>
            <span class="pill pill--warn" id="system-test-status-pill">Előbb teszteld a kapcsolatot</span>
          </div>
          <div class="order-detail-box">Végigmegy egy teljes rendelésen (Stripe fizetés → webhook → rendelés létrejötte → Kvikk csomag → visszaigazoló email), és soronként megmutatja, hol tart, illetve hol akadt el. Mivel lesz FTP- és CLI-hozzáférésed is, a részletes hibaüzeneteket ott, a szerver naplójában is visszakeresheted majd. Ez az első valódi alkalom is, hogy ellenőrizni tudjuk a fenti "Adat-mezőleképezés" beállítást — ha a vevő neve (vagy más adat) üresen vagy hibásan jönne át, itt fog kiderülni.</div>
          <div style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap">
            <button type="button" class="btn btn--system-test" id="open-system-test-btn" data-mode="test" disabled>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 3h6"/><path d="M10 3v6.4a2 2 0 0 1-.3 1L5 18a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-4.7-7.6a2 2 0 0 1-.3-1V3"/><path d="M8 14h8"/></svg>
              <span>
                <span class="btn-system-test-title">Teszt mód futtatása</span>
                <span class="btn-system-test-sub">Nincs valódi pénzmozgás</span>
              </span>
            </button>
            <button type="button" class="btn btn--system-test-live" id="open-system-test-live-btn" data-mode="live" disabled>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4"/><path d="M12 16.5h.01"/><path d="M10.4 4.3 2.5 18a2.1 2.1 0 0 0 1.8 3.1h15.4a2.1 2.1 0 0 0 1.8-3.1L13.6 4.3a1.8 1.8 0 0 0-3.2 0Z"/></svg>
              <span>
                <span class="btn-system-test-title">Éles teszt indítása</span>
                <span class="btn-system-test-sub">300 Ft valódi fizetés</span>
              </span>
            </button>
          </div>
        </div>

        <div class="card" style="max-width:620px; margin-top:22px; padding:22px 24px 26px">
          <div class="view-head" style="margin-bottom:8px">
            <div>
              <span class="view-eyebrow">bejelentkezés</span>
              <h2 style="font-size:15px; font-weight:700; margin:0">Adminisztrátorok</h2>
            </div>
            <button class="btn btn--ghost btn--small" id="new-admin-btn">+ Új admin</button>
          </div>
          <p class="empty-note" style="padding:0 0 10px">Akiknek bejelentkezési hozzáférése van ehhez az admin felülethez.</p>
          <div class="table-scroll"><table>
            <thead><tr><th>Email</th><th>Létrehozva</th><th>Utolsó belépés</th><th></th></tr></thead>
            <tbody id="admins-tbody"></tbody>
          </table></div>
        </div>
      </section>

      <div class="modal-overlay" id="product-modal">
        <div class="modal-card modal-card--xl">
          <div class="modal-head">
            <h2 class="modal-title">Új termék</h2>
            <button type="button" class="modal-close" id="product-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <form id="product-form" class="modal-form">
            <div class="modal-body">
            <div class="product-tabs">
              <button type="button" class="product-tab-btn active" data-tab="basic">Alapadatok</button>
              <button type="button" class="product-tab-btn" data-tab="variants">Variánsok</button>
              <button type="button" class="product-tab-btn" data-tab="linked">Kapcsolt termék</button>
              <button type="button" class="product-tab-btn" data-tab="addons">Extrák</button>
              <button type="button" class="product-tab-btn" data-tab="overview">Áttekintés</button>
            </div>

            <div class="product-tab-panel active" id="tab-basic" data-tab="basic">
              <p class="product-tab-hint">Ez az alaptermék adatlapja — ide rögzíted a saját nyilvántartásodhoz a képet, árat és a többi alapadatot, és ez alapján megy a raktárkészlet-vezetés. A tényleges shop-oldalt, amit a vevő lát, majd a webshop rendszere vezérli — ez itt csak a te áttekintésedet szolgálja, azzal kell összhangban tartani. Kötelező kitölteni minden termékhez.</p>
              <div class="field" style="margin-bottom:0; max-width:320px">
                <label for="product-image">Termékképek (több is kiválasztható, az első lesz a borítókép)</label>
                <input type="file" id="product-image" accept="image/*" multiple>
                <div class="gallery-preview" id="product-image-preview">
                  <span class="thumb thumb--preview">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3.5 7.5 8.5-4 8.5 4-8.5 4-8.5-4Z"/><path d="M3.5 7.5v9l8.5 4 8.5-4v-9"/><path d="M12 11.5v9"/></svg>
                  </span>
                </div>
              </div>

              <div class="field-grid" style="max-width:none; margin-top:16px">
                <div class="field">
                  <label for="product-name">Termék neve</label>
                  <input type="text" id="product-name" placeholder="pl. Alaptermék — Méz 500g" required>
                </div>
                <div class="field">
                  <label for="product-type">Termék fajtája</label>
                  <select id="product-type">
                    <option value="regular">Rendes termék (eladható)</option>
                    <option value="component">Kapcsolt / csomagolóanyag (nem eladó)</option>
                  </select>
                </div>
                <div class="field">
                  <label for="product-price" id="product-price-label">Ár (Ft)</label>
                  <input type="number" id="product-price" placeholder="pl. 12900" min="0" required>
                </div>
                <div class="field">
                  <label for="product-stock" id="product-stock-label">Kezdő készlet</label>
                  <input type="number" id="product-stock" placeholder="0" min="0" required>
                </div>
                <div class="field">
                  <label for="product-deduction">Csökkenés rendelésenként</label>
                  <input type="number" id="product-deduction" value="1" min="1">
                </div>
                <div class="field">
                  <label for="product-weight">Súly (g) <span style="font-weight:400; color:var(--text-soft)">— a Kvikk automata címkézéshez kell</span></label>
                  <input type="number" id="product-weight" placeholder="pl. 350" min="0">
                </div>
              </div>
            </div>

            <div class="product-tab-panel-group">
            <div class="product-tab-panel" id="tab-variants" data-tab="variants">
              <p class="product-tab-hint">Itt add meg, ha a termék több változatban kapható (pl. boríték színe). Ha hozzáadsz legalább egy variánst, a termék készlete variánsonként külön lesz nyilvántartva, és az Alapadatok fülön a "Készleten" mező ekkor az összesített darabszámot mutatja majd (nem szerkeszthető ott közvetlenül). Opcionális — ha a terméknek nincs változata, hagyd üresen.</p>
              <p class="modal-section-label">Variánsok <span style="font-weight:400; color:var(--text-soft); text-transform:none; letter-spacing:0">(opcionális — pl. boríték színe)</span></p>
              <div id="product-variants-list"></div>
              <button type="button" class="btn btn--ghost btn--small" id="add-variant-btn" style="margin-top:10px">+ Variáns hozzáadása</button>
            </div>

            <div class="product-tab-panel" id="tab-linked" data-tab="linked">
              <p class="product-tab-hint">Ha ennek a terméknek minden eladásakor automatikusan fogyasztani kell egy másik raktári tételt is (pl. minden borítékhoz jár egy Doboz), itt kapcsold össze. A kapcsolt tétel készlete minden rendelésnél magától csökken — nem kell külön könyvelned. A Súly mezőt csak akkor töltsd ki, ha a kapcsolt tétel pluszsúlyt ad a csomaghoz az alaptermék súlyán felül (ez számít a Kvikk automata címkézésénél). Opcionális.</p>
              <p class="modal-section-label">Kapcsolt termék <span style="font-weight:400; color:var(--text-soft); text-transform:none; letter-spacing:0">— automatikus, kötelező fogyás</span></p>
              <div class="field-grid" style="max-width:none">
                <div class="field">
                  <label for="product-linked-select">Kapcsolt termék</label>
                  <select id="product-linked-select">
                    <option value="">Nincs kapcsolt termék</option>
                  </select>
                </div>
                <div class="field">
                  <label for="product-linked-qty">Mennyiség / rendelés</label>
                  <input type="number" id="product-linked-qty" min="1" placeholder="pl. 1" disabled>
                </div>
                <div class="field">
                  <label for="product-linked-weight">Súly (g) <span style="font-weight:400; color:var(--text-soft)">— opcionális, pluszsúly</span></label>
                  <input type="number" id="product-linked-weight" min="0" placeholder="pl. 40" disabled>
                </div>
              </div>
              <p class="empty-note" style="padding:6px 0 0">Pl. "Doboz" 1-es mennyiséggel, 40g súllyal — minden rendelésnél automatikusan csökken a Doboz raktárkészlete, és a csomag súlyához is hozzáadódik.</p>
            </div>

            <div class="product-tab-panel" id="tab-addons" data-tab="addons">
              <p class="product-tab-hint">Opcionális extrák nyilvántartása, amiket a shopban majd felárral választhat a vevő (pl. "+2 extra boríték", "QR-kódos hangüzenet", "Masnikötés") — itt csak a saját raktár- és súlykövetéshez rögzíted őket, összhangban a shop kínálatával. Ha az extra fizikai tétel és von raktárkészletet, kapcsold össze egy meglévő termékkel; ha digitális/szolgáltatás és nem von semmit, hagyd üresen. A Súly mezőt csak akkor töltsd ki, ha az extra pluszsúlyt ad a csomaghoz — ez számít a Kvikk címkézésénél.</p>
              <p class="modal-section-label">Választható extrák <span style="font-weight:400; color:var(--text-soft); text-transform:none; letter-spacing:0">— opcionális, felárral</span></p>
              <div id="product-addons-list"></div>
              <button type="button" class="btn btn--ghost btn--small" id="add-addon-btn" style="margin-top:10px">+ Extra hozzáadása</button>
            </div>
            </div>

            <div class="product-tab-panel" id="tab-overview" data-tab="overview">
              <p class="product-tab-hint">Gyors összefoglaló arról, amit eddig megadtál a többi fülön — nézd át mentés előtt, hogy minden stimmel-e.</p>
              <div id="product-overview-content"></div>
            </div>

            <div class="modal-actions">
              <button type="button" class="btn btn--ghost" id="product-cancel">Mégsem</button>
              <button type="submit" class="btn" id="product-submit-btn">Termék mentése</button>
            </div>
          </form>
        </div>
      </div>
      </div>

      <!-- globális, nézettől független modal: bárhonnan megnyitható -->
      <div class="modal-overlay" id="admin-user-modal">
        <div class="modal-card">
          <div class="modal-head">
            <h2 class="modal-title">Új admin hozzáadása</h2>
            <button type="button" class="modal-close" id="admin-user-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <form id="admin-user-form" class="modal-form">
            <div class="modal-body">
              <div class="field" style="margin-bottom:14px">
                <label for="admin-user-email">Email cím</label>
                <input type="email" id="admin-user-email" required>
              </div>
              <div class="field">
                <label for="admin-user-password">Jelszó (min. 8 karakter)</label>
                <input type="password" id="admin-user-password" required minlength="8">
              </div>
              <p class="empty-note" style="padding:6px 0 0">Ezt az email címet és jelszót add oda annak, akinek hozzáférést szeretnél adni — ő ezzel tud majd bejelentkezni.</p>
            </div>
            <div class="modal-actions">
              <button type="button" class="btn btn--ghost" id="admin-user-cancel">Mégsem</button>
              <button type="submit" class="btn">Admin hozzáadása</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal-overlay" id="notif-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <h2 class="modal-title">Értesítések</h2>
            <button type="button" class="modal-close" id="notif-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <div class="notif-modal-list" id="notif-modal-list"></div>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="customer-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <div class="customer-head">
              <span class="avatar" id="customer-avatar">?</span>
              <div>
                <h2 class="modal-title" id="customer-name">—</h2>
                <div class="customer-contact" id="customer-contact">—</div>
              </div>
            </div>
            <button type="button" class="modal-close" id="customer-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <p class="modal-section-label" style="margin-top:4px">Vásárlási előzmények</p>
            <div class="table-scroll">
              <table>
                <thead>
                  <tr><th>Dátum</th><th>Rendelés</th><th>Termék</th><th class="num">Összeg</th><th>Státusz</th></tr>
                </thead>
                <tbody id="customer-history-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="order-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <div>
              <h2 class="modal-title" id="order-modal-title">Rendelés #0000</h2>
              <div class="customer-contact" id="order-modal-meta">—</div>
            </div>
            <button type="button" class="modal-close" id="order-modal-close" aria-label="Bezárás">&times;</button>
          </div>

          <div class="modal-body">
            <div class="order-status-row">
              <span class="pill pill--neutral" id="order-modal-method-pill">—</span>
              <span class="pill" id="order-modal-payment-pill">—</span>
              <span class="pill" id="order-modal-shipping-pill">—</span>
            </div>

            <p class="modal-section-label" style="margin-top:16px">Tételek</p>
            <div class="table-scroll">
              <table>
                <thead><tr><th>Termék</th><th class="num">Mennyiség</th><th class="num">Egységár</th><th class="num">Összeg</th></tr></thead>
                <tbody id="order-modal-items"></tbody>
              </table>
            </div>

            <p class="modal-section-label" style="margin-top:18px">Súly <span style="font-weight:400; color:var(--text-soft); text-transform:none; letter-spacing:0">— a Kvikk címkézéshez</span></p>
            <div id="order-modal-weight-breakdown"></div>

            <div class="field-grid" style="max-width:none; margin-top:18px">
              <div class="field">
                <label>Szállítási cím</label>
                <div class="order-detail-box" id="order-modal-address">—</div>
              </div>
              <div class="field">
                <label id="order-modal-payment-ref-label">Stripe tranzakció</label>
                <div class="order-detail-box mono" id="order-modal-stripe">—</div>
              </div>
            </div>

            <div class="field" id="order-modal-coupon-wrap" style="margin-top:14px; display:none">
              <label>Felhasznált kupon</label>
              <div class="order-detail-box mono" id="order-modal-coupon">—</div>
            </div>
            <div class="field" id="order-modal-kvikk-auto-wrap" style="margin-top:14px; display:none">
              <label>Kvikk automata csomag</label>
              <div class="order-detail-box" id="order-modal-kvikk-auto">—</div>
            </div>

            <p class="modal-section-label" style="margin-top:22px">Számla</p>
            <label class="checkbox-item" style="border:1px solid var(--border); border-radius:8px;">
              <input type="checkbox" id="order-modal-invoice">
              <span>Kézi számla kiállítva és a csomagba téve</span>
            </label>
            <div class="field" style="margin-top:10px; max-width:420px">
              <label for="order-modal-invoice-file">Számla csatolása (PDF, opcionális)</label>
              <input type="file" id="order-modal-invoice-file" accept="application/pdf">
              <p class="empty-note" style="padding:6px 0 0" id="order-modal-invoice-filename"></p>
            </div>
            <div class="settings-save-row" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border)">
              <span class="save-status" id="order-modal-invoice-save-status"></span>
              <button type="button" class="btn" id="order-modal-invoice-save-btn">Számla adatok mentése</button>
            </div>
          </div>

          <div class="order-invoice-row" id="order-modal-return-row">
            <a class="btn btn--ghost" href="https://app.kvikk.hu" target="_blank" rel="noopener">Visszaküldés a Kvikknél →</a>
            <span id="order-modal-stripe-refund-slot"></span>
            <button type="button" class="btn order-close-btn" id="order-modal-close-footer">Bezárás</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="reconcile-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <div>
              <h2 class="modal-title" id="reconcile-modal-title">Rendelések</h2>
              <div class="customer-contact" id="reconcile-modal-subtitle">—</div>
            </div>
            <button type="button" class="modal-close" id="reconcile-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <div class="table-scroll">
              <table>
                <thead><tr id="reconcile-modal-thead"><th>Rendelés</th><th>Vevő</th><th class="num">Összeg</th><th>Dátum</th></tr></thead>
                <tbody id="reconcile-modal-tbody"></tbody>
              </table>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn" id="reconcile-modal-close-footer">Bezárás</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="stripe-cli-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <h2 class="modal-title">Stripe CLI csatlakozás</h2>
            <button type="button" class="modal-close" id="stripe-cli-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <div class="wizard-dots" id="stripe-cli-dots"></div>

            <div class="wizard-panel active" data-step="1">
              <p class="wizard-step-label">1. lépés / 4</p>
              <h3 class="wizard-step-title">Stripe CLI telepítése</h3>
              <p class="empty-note" style="padding:0 0 8px">A Stripe CLI egy parancssori eszköz — ezzel tesztelheted le a webhookokat és kérheted le a kulcsaidat anélkül, hogy a Dashboardon kattintgatnál. Egyszer kell feltelepíteni a géped.</p>
              <div class="wizard-cmd"><span>Windows (Scoop): scoop install stripe</span><button type="button" class="btn btn--ghost btn--small wizard-copy-btn" data-copy="scoop install stripe">Másolás</button></div>
              <div class="wizard-cmd"><span>Mac (Homebrew): brew install stripe/stripe-cli/stripe</span><button type="button" class="btn btn--ghost btn--small wizard-copy-btn" data-copy="brew install stripe/stripe-cli/stripe">Másolás</button></div>
              <p class="empty-note" style="padding:6px 0 0">Egyéb rendszerhez: <a href="https://github.com/stripe/stripe-cli/releases/latest" target="_blank" rel="noopener">letöltés a hivatalos GitHub oldalról →</a></p>
            </div>

            <div class="wizard-panel" data-step="2">
              <p class="wizard-step-label">2. lépés / 4</p>
              <h3 class="wizard-step-title">Bejelentkezés</h3>
              <p class="empty-note" style="padding:0 0 8px">Kösd össze a CLI-t a Stripe-fiókoddal:</p>
              <div class="wizard-cmd"><span>stripe login</span><button type="button" class="btn btn--ghost btn--small wizard-copy-btn" data-copy="stripe login">Másolás</button></div>
              <p class="empty-note" style="padding:6px 0 0">Ez megnyit egy böngészőablakot, ahol a Stripe-fiókoddal hitelesíted magad — utána a terminál visszaigazolja a sikeres kapcsolatot.</p>
            </div>

            <div class="wizard-panel" data-step="3">
              <p class="wizard-step-label">3. lépés / 4</p>
              <h3 class="wizard-step-title">Webhook továbbítása teszteléshez</h3>
              <p class="empty-note" style="padding:0 0 8px">Ez a parancs a Stripe-eseményeket (fizetés sikeres, visszatérítés stb.) élőben továbbítja a backendednek, teszt közben:</p>
              <div class="wizard-cmd"><span>stripe listen --forward-to https://webshop.hu/webhook/stripe.php</span><button type="button" class="btn btn--ghost btn--small wizard-copy-btn" data-copy="stripe listen --forward-to https://webshop.hu/webhook/stripe.php">Másolás</button></div>
              <p class="empty-note" style="padding:6px 0 0">Ez kiír egy ideiglenes <span class="mono">whsec_...</span> kulcsot a terminálban — ezt másold be a fenti <strong>Teszt módú</strong> "Webhook signing secret" mezőbe.</p>
            </div>

            <div class="wizard-panel" data-step="4">
              <p class="wizard-step-label">4. lépés / 4</p>
              <h3 class="wizard-step-title">Élesítéskor</h3>
              <p class="empty-note" style="padding:0 0 8px">A <span class="mono">stripe listen</span> csak fejlesztéshez/teszteléshez való — nem fut folyamatosan éles környezetben. Élesítéskor menj vissza a Stripe Dashboardra, és ott hozz létre egy végleges webhook endpointot ugyanazzal az URL-lel.</p>
              <a class="btn btn--ghost" data-target="webhooks" href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe Dashboard → Webhookok →</a>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn--ghost" id="stripe-cli-back-btn">Vissza</button>
            <button type="button" class="btn" id="stripe-cli-next-btn">Tovább</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="ai-setup-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <h2 class="modal-title">Automatikus alapadat-felismerés</h2>
            <button type="button" class="modal-close" id="ai-setup-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <p class="empty-note" style="padding:0 0 14px">Ez a bemutató szimulálja, hogyan fog működni élesben: a valódi rendszerben az AI ténylegesen beolvassa a csatlakoztatott weboldaladat, és ez alapján tölti ki a lenti adatokat kiindulásképp — amit utána bármikor módosíthatsz.</p>
            <div id="ai-setup-steps">
              <div class="ai-step" data-step-id="connect">
                <span class="ai-step-icon pending">…</span>
                <div><div class="ai-step-title">Kapcsolódás a weboldalhoz</div><div class="ai-step-detail"></div></div>
              </div>
              <div class="ai-step" data-step-id="products">
                <span class="ai-step-icon pending">…</span>
                <div><div class="ai-step-title">Termékek felismerése (név, ár, leírás)</div><div class="ai-step-detail"></div></div>
              </div>
              <div class="ai-step" data-step-id="legal">
                <span class="ai-step-icon pending">…</span>
                <div><div class="ai-step-title">Jogi dokumentumok azonosítása (Impresszum, ÁSZF, Adatkezelési tájékoztató, Cookie szabályzat, Elállási nyilatkozat)</div><div class="ai-step-detail"></div></div>
              </div>
              <div class="ai-step" data-step-id="payment">
                <span class="ai-step-icon pending">…</span>
                <div><div class="ai-step-title">Fizetési beállítások ellenőrzése (Stripe)</div><div class="ai-step-detail"></div></div>
              </div>
              <div class="ai-step" data-step-id="weight">
                <span class="ai-step-icon pending">…</span>
                <div><div class="ai-step-title">Termék súlyok felismerése</div><div class="ai-step-detail"></div></div>
              </div>
              <div class="ai-step" data-step-id="kvikk">
                <span class="ai-step-icon pending">…</span>
                <div><div class="ai-step-title">Kvikk szállítási kapcsolat ellenőrzése</div><div class="ai-step-detail"></div></div>
              </div>
            </div>
            <div id="ai-setup-summary" style="display:none">
              <p class="modal-section-label" style="margin-top:4px">Összefoglaló</p>
              <div id="ai-setup-summary-list"></div>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn" id="ai-setup-close-btn" style="display:none">Bezárás, megnézem az eredményt</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="system-test-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <div>
              <h2 class="modal-title" id="system-test-modal-title">Rendszerteszt</h2>
              <div class="customer-contact" id="system-test-modal-subtitle">—</div>
            </div>
            <button type="button" class="modal-close" id="system-test-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <div id="system-test-live-gate" style="display:none">
              <p class="empty-note" style="padding:12px 14px; border:1px solid var(--danger-text); background:var(--danger-bg); border-radius:8px; color:var(--danger-text)">
                <strong>Ez valódi pénzmozgással jár.</strong> Az éles teszt egy tényleges, 300 Ft összegű bankkártyás terhelést indít a Stripe éles fiókodon — ez nem szimuláció. Csak akkor indítsd, ha az Éles mód API-kulcsai már be vannak állítva a Fizetés oldalon, és tényleg készen állsz élesben tesztelni.
              </p>
              <label class="checkbox-item" style="border:1px solid var(--border); border-radius:8px; margin-top:12px">
                <input type="checkbox" id="system-test-live-confirm">
                <span>Megértettem, hogy ez valódi, 300 Ft-os terhelést indít — nem teszt-tranzakció.</span>
              </label>
              <div style="margin-top:14px">
                <button type="button" class="btn" id="system-test-live-start-btn" disabled>Éles teszt indítása →</button>
              </div>
            </div>

            <div id="system-test-run" style="display:none">
              <p class="empty-note" style="padding:0 0 14px">Ez a bemutató szimulálja a folyamatot — élesben ez a checklist a backend és a Stripe/Kvikk API-k tényleges válaszai alapján fog frissülni lépésről lépésre.</p>
              <div id="system-test-steps">
                <div class="ai-step" data-step-id="connect">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Stripe és Kvikk kapcsolat ellenőrzése</div><div class="ai-step-detail"></div></div>
                </div>
                <div class="ai-step" data-step-id="payment">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Fizetés indítása</div><div class="ai-step-detail"></div></div>
                </div>
                <div class="ai-step" data-step-id="webhook">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Stripe webhook fogadásának ellenőrzése</div><div class="ai-step-detail"></div></div>
                </div>
                <div class="ai-step" data-step-id="order">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Rendelés létrejötte az adminban</div><div class="ai-step-detail"></div></div>
                </div>
                <div class="ai-step" data-step-id="kvikk">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Kvikk csomag-létrehozás tesztje</div><div class="ai-step-detail"></div></div>
                </div>
                <div class="ai-step" data-step-id="email">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Visszaigazoló email kiküldésének ellenőrzése</div><div class="ai-step-detail"></div></div>
                </div>
                <div class="ai-step" data-step-id="cleanup">
                  <span class="ai-step-icon pending">…</span>
                  <div><div class="ai-step-title">Teendő a teszt után</div><div class="ai-step-detail"></div></div>
                </div>
              </div>
              <div id="system-test-summary" style="display:none">
                <p class="modal-section-label" style="margin-top:4px">Összefoglaló — hol tart, hol akadt el</p>
                <div id="system-test-summary-list"></div>
              </div>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn" id="system-test-close-btn" style="display:none">Bezárás</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="export-modal">
        <div class="modal-card modal-card--wide">
          <div class="modal-head">
            <h2 class="modal-title">Excel export</h2>
            <button type="button" class="modal-close" id="export-modal-close" aria-label="Bezárás">&times;</button>
          </div>
          <div class="modal-body">
            <p class="modal-section-label">Időszak</p>
            <div class="mode-toggle" id="export-range-toggle">
              <button type="button" class="mode-btn active" data-range="all">Minden idő</button>
              <button type="button" class="mode-btn" data-range="custom">Egyéni időszak</button>
            </div>
            <div class="field-grid" id="export-range-fields" style="max-width:none; margin-top:14px; display:none">
              <div class="field">
                <label for="export-range-from">Ettől</label>
                <input type="date" id="export-range-from">
              </div>
              <div class="field">
                <label for="export-range-to">Eddig</label>
                <input type="date" id="export-range-to">
              </div>
            </div>

            <p class="modal-section-label" style="margin-top:20px">Mit exportálj?</p>
            <div class="checkbox-list">
              <label class="checkbox-item">
                <input type="checkbox" id="export-check-orders" checked>
                <span>Rendelések — rendelésszám, vevő, összeg, fizetés- és szállítási állapot, dátum</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" id="export-check-shipping" checked>
                <span>Szállítások — Kvikk-állapot, cím, fizetési mód rendelésenként (lemondott rendelés nélkül)</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" id="export-check-customers" checked>
                <span>Vevők — név, email, telefon, rendelésszám, utolsó vásárlás</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" id="export-check-epr" checked>
                <span><strong>EPR — anyagok, termékek és súlyok</strong> rendelésenként (kiterjesztett gyártói felelősség jelentéshez, lemondott rendelés nélkül, mert az nem lett ténylegesen kiszállítva)</span>
              </label>
            </div>
            <p class="empty-note" style="padding:10px 0 0" id="export-summary-note"></p>
            <p class="empty-note" style="padding:6px 0 0">Az export az itt látható demó-rendelések (#0226–#0231) adatait tartalmazza — a valódi backendnél ez az összes rendelésedet fogja exportálni.</p>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn--ghost" id="export-cancel">Mégsem</button>
            <button type="button" class="btn" id="export-run-btn">Excel exportálása →</button>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>const CURRENT_ADMIN_ID = <?= (int) current_admin_id() ?>;</script>
<script src="script.js"></script>
</body>
</html>
