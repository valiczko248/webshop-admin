  // ---- sötét / világos téma ----
  (function initTheme(){
    let saved = null;
    try { saved = localStorage.getItem('webshop-theme'); } catch (e) {}
    if (saved === 'light' || saved === 'dark') {
      document.documentElement.setAttribute('data-theme', saved);
    } else {
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    }
  })();

  document.getElementById('theme-toggle').addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('webshop-theme', next); } catch (e) {}
  });

  const navItems = document.querySelectorAll('.nav-item');
  const views = document.querySelectorAll('.view');
  function switchToView(viewName){
    const target = document.getElementById(viewName);
    if (!target) return;
    navItems.forEach(t => t.classList.remove('active'));
    views.forEach(v => v.classList.remove('active'));
    const navItem = document.querySelector(`.nav-item[data-view="${viewName}"]`);
    if (navItem) navItem.classList.add('active');
    target.classList.add('active');
    if (viewName === 'stats') renderStatistics();
  }
  navItems.forEach(item => {
    item.addEventListener('click', () => switchToView(item.dataset.view));
  });

  // ---- raktár: készlet +/- (több soron is működik) ----
  const inventoryTbody = document.getElementById('inventory-tbody');
  inventoryTbody.addEventListener('click', (e) => {
    const qtyBtn = e.target.closest('.qty-btn');
    if (qtyBtn) {
      const row = qtyBtn.closest('tr');
      const stockCell = row.querySelector('.stock-count');
      const availableCell = row.querySelector('.stock-available');
      let value = parseInt(stockCell.textContent, 10) || 0;
      value = qtyBtn.dataset.action === 'plus' ? value + 1 : Math.max(0, value - 1);
      stockCell.textContent = value;
      if (availableCell) {
        const reserved = parseInt(row.children[4].textContent, 10) || 0;
        availableCell.textContent = Math.max(0, value - reserved);
      }
      const prodName = getProdName(row);
      if (value <= 10) {
        pushNotification(`Alacsony készlet: <strong>${prodName}</strong> (${value} db maradt)`, 'warn');
      } else {
        pushNotification(`${prodName} készlete ${value} db-ra változott`, 'info');
      }
      fetch(`/api/products.php?id=${row.dataset.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stock: value })
      });
      return;
    }
    const archiveBtn = e.target.closest('.row-archive-btn');
    if (archiveBtn) {
      const row = archiveBtn.closest('tr');
      const wasArchived = row.dataset.archived === 'true';
      row.dataset.archived = wasArchived ? 'false' : 'true';
      row.classList.toggle('row-archived', !wasArchived);
      archiveBtn.innerHTML = wasArchived ? archiveIconSvg : restoreIconSvg;
      archiveBtn.setAttribute('aria-label', wasArchived ? 'Termék archiválása' : 'Termék visszaállítása');
      archiveBtn.title = wasArchived ? 'Archiválás' : 'Visszaállítás';
      applyArchivedFilter();
      pushNotification(`<strong>${getProdName(row)}</strong> ${wasArchived ? 'visszaállítva az aktív termékek közé' : 'archiválva — kikerült az aktív listából'}`, 'info');
      fetch(`/api/products.php?id=${row.dataset.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ archived: !wasArchived })
      });
      return;
    }
    const deleteBtn = e.target.closest('.row-delete-btn');
    if (deleteBtn) {
      const row = deleteBtn.closest('tr');
      const prodName = getProdName(row);
      if (confirm(`Biztosan törlöd a(z) "${prodName}" terméket? Ez nem vonható vissza.`)) {
        fetch(`/api/products.php?id=${row.dataset.id}`, { method: 'DELETE' });
        row.remove();
        pushNotification(`Törölve: <strong>${prodName}</strong>`, 'warn');
      }
      return;
    }
    const editBtn = e.target.closest('.row-edit-btn');
    if (editBtn) openForEditProduct(editBtn.closest('tr'));
  });

  function applyArchivedFilter(){
    const showArchived = document.getElementById('show-archived-toggle').checked;
    inventoryTbody.querySelectorAll('tr').forEach(row => {
      const isArchived = row.dataset.archived === 'true';
      row.style.display = (isArchived && !showArchived) ? 'none' : '';
    });
  }
  document.getElementById('show-archived-toggle').addEventListener('change', applyArchivedFilter);

  // ---- termék modal (új + szerkesztés, kapcsolt termékkel) ----
  const productModal = document.getElementById('product-modal');
  const productForm = document.getElementById('product-form');
  const productModalTitle = document.querySelector('#product-modal .modal-title');
  const productSubmitBtn = document.getElementById('product-submit-btn');
  const productStockLabel = document.getElementById('product-stock-label');
  const productNameInput = document.getElementById('product-name');
  const productTypeSelect = document.getElementById('product-type');
  const productPriceLabel = document.getElementById('product-price-label');
  const productPriceInput = document.getElementById('product-price');
  const productStockInput = document.getElementById('product-stock');
  const productDeductionInput = document.getElementById('product-deduction');
  const productLinkedSelect = document.getElementById('product-linked-select');
  const productLinkedQtyInput = document.getElementById('product-linked-qty');
  const productLinkedWeightInput = document.getElementById('product-linked-weight');
  const productImageInput = document.getElementById('product-image');
  const productImagePreview = document.getElementById('product-image-preview');
  const productWeightInput = document.getElementById('product-weight');
  const variantsListEl = document.getElementById('product-variants-list');
  const addonsListEl = document.getElementById('product-addons-list');
  const boxIconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3.5 7.5 8.5-4 8.5 4-8.5 4-8.5-4Z"/><path d="M3.5 7.5v9l8.5 4 8.5-4v-9"/><path d="M12 11.5v9"/></svg>';
  const editIconSvgProduct = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
  const archiveIconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 13h4"/></svg>';
  const restoreIconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>';
  const deleteIconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';

  function rowActionsHtml(archived){
    return `
      <div class="row-actions">
        <button type="button" class="row-edit-btn" aria-label="Termék szerkesztése">${editIconSvgProduct}</button>
        <button type="button" class="row-edit-btn row-archive-btn" aria-label="${archived ? 'Termék visszaállítása' : 'Termék archiválása'}" title="${archived ? 'Visszaállítás' : 'Archiválás'}">${archived ? restoreIconSvg : archiveIconSvg}</button>
        <button type="button" class="row-edit-btn row-delete-btn" aria-label="Termék törlése">${deleteIconSvg}</button>
      </div>
    `;
  }
  let productImages = [];
  let editingProductRow = null;
  let productVariants = [];
  let productAddons = [];

  function getProdName(row){
    const el = row.querySelector('.prod-name');
    return (el.childNodes[0] ? el.childNodes[0].textContent : el.textContent).trim();
  }

  function renderGalleryPreview(){
    productImagePreview.innerHTML = productImages.length
      ? productImages.map(src => `<span class="thumb thumb--preview"><img src="${src}" alt=""></span>`).join('')
      : `<span class="thumb thumb--preview">${boxIconSvg}</span>`;
  }

  function syncStockFromVariants(){
    if (!productVariants.length) {
      productStockInput.disabled = false;
      return;
    }
    productStockInput.disabled = true;
    productStockInput.value = productVariants.reduce((sum, v) => sum + (parseInt(v.stock, 10) || 0), 0);
  }

  function renderVariantsList(){
    variantsListEl.innerHTML = productVariants.length ? '' : '<div class="empty-list-hint">Nincs variáns — a termék egyetlen közös készlettel megy.</div>';
    productVariants.forEach((v, i) => {
      const row = document.createElement('div');
      row.className = 'repeat-card';
      row.innerHTML = `
        <button type="button" class="row-remove-btn" data-idx="${i}" aria-label="Variáns törlése">&times;</button>
        <div class="repeat-grid">
          <div class="repeat-field">
            <label>Variáns neve</label>
            <input type="text" class="variant-name" placeholder="pl. Piros boríték" value="${v.name.replace(/"/g,'&quot;')}" data-idx="${i}" data-field="name">
          </div>
          <div class="repeat-field">
            <label>Készlet (db)</label>
            <input type="number" class="variant-stock" placeholder="0" min="0" value="${v.stock}" data-idx="${i}" data-field="stock">
          </div>
          <div class="repeat-field">
            <label>Súly (g) <span style="text-transform:none; font-weight:400">— ha eltér az alaptermékétől</span></label>
            <input type="number" class="variant-weight" placeholder="pl. 15" min="0" value="${v.weight || ''}" data-idx="${i}" data-field="weight">
          </div>
        </div>
      `;
      variantsListEl.appendChild(row);
    });
    syncStockFromVariants();
  }

  variantsListEl.addEventListener('input', (e) => {
    const idx = e.target.dataset.idx;
    if (idx === undefined) return;
    productVariants[idx][e.target.dataset.field] = e.target.value;
    if (e.target.dataset.field === 'stock') syncStockFromVariants();
  });
  variantsListEl.addEventListener('click', (e) => {
    const btn = e.target.closest('.row-remove-btn');
    if (!btn) return;
    productVariants.splice(parseInt(btn.dataset.idx, 10), 1);
    renderVariantsList();
  });
  document.getElementById('add-variant-btn').addEventListener('click', () => {
    productVariants.push({ name: '', stock: 0, weight: '' });
    renderVariantsList();
  });

  function renderAddonsList(){
    const productNames = Array.from(inventoryTbody.querySelectorAll('.prod-name')).map(el => el.childNodes[0].textContent.trim());
    addonsListEl.innerHTML = productAddons.length ? '' : '<div class="empty-list-hint">Nincs választható extra ennél a terméknél.</div>';
    productAddons.forEach((a, i) => {
      const row = document.createElement('div');
      row.className = 'repeat-card';
      const options = ['<option value="">Nincs — digitális/szolgáltatás</option>']
        .concat(productNames.map(n => `<option value="${n}" ${a.linked === n ? 'selected' : ''}>${n}</option>`));
      row.innerHTML = `
        <button type="button" class="row-remove-btn" data-idx="${i}" aria-label="Extra törlése">&times;</button>
        <div class="repeat-grid">
          <div class="repeat-field repeat-field--full">
            <label>Extra neve</label>
            <input type="text" class="addon-name" placeholder="pl. QR-kódos hangüzenet" value="${a.name.replace(/"/g,'&quot;')}" data-idx="${i}" data-field="name">
          </div>
          <div class="repeat-field">
            <label>Felár (Ft)</label>
            <input type="number" class="addon-price" placeholder="0" min="0" value="${a.price}" data-idx="${i}" data-field="price">
          </div>
          <div class="repeat-field">
            <label>Súly (g) — ha pluszsúlyt ad</label>
            <input type="number" class="addon-weight" placeholder="pl. 10" min="0" value="${a.weight || ''}" data-idx="${i}" data-field="weight">
          </div>
          <div class="repeat-field">
            <label>Von raktárkészletet?</label>
            <select class="addon-linked" data-idx="${i}" data-field="linked">${options.join('')}</select>
          </div>
          <div class="repeat-field repeat-field--full">
            <label>Mennyiség / rendelés ${a.linked ? '' : '(csak ha van raktártétel megadva)'}</label>
            <input type="number" class="addon-linked-qty" placeholder="1" min="1" value="${a.linkedQty || 1}" data-idx="${i}" data-field="linkedQty" ${a.linked ? '' : 'disabled'}>
          </div>
        </div>
      `;
      addonsListEl.appendChild(row);
    });
  }

  addonsListEl.addEventListener('input', (e) => {
    const idx = e.target.dataset.idx;
    if (idx === undefined) return;
    productAddons[idx][e.target.dataset.field] = e.target.value;
  });
  addonsListEl.addEventListener('change', (e) => {
    const idx = e.target.dataset.idx;
    if (idx === undefined || e.target.dataset.field !== 'linked') return;
    productAddons[idx].linked = e.target.value;
    renderAddonsList();
  });
  addonsListEl.addEventListener('click', (e) => {
    const btn = e.target.closest('.row-remove-btn');
    if (!btn) return;
    productAddons.splice(parseInt(btn.dataset.idx, 10), 1);
    renderAddonsList();
  });
  document.getElementById('add-addon-btn').addEventListener('click', () => {
    productAddons.push({ name: '', price: 0, weight: '', linked: '', linkedQty: 1 });
    renderAddonsList();
  });

  function stockSubHtml(deduction, linkedName, linkedQty, weight){
    const parts = [];
    if (weight) parts.push(`${weight} g`);
    if (deduction && deduction != 1) parts.push(`${deduction} db / rendelés`);
    if (linkedName) parts.push(`+ ${linkedQty || 1}× ${linkedName} / rendelés`);
    return parts.length ? `<div class="stock-sub">${parts.join(' · ')}</div>` : '';
  }

  function typeBadgeHtml(type){
    return type === 'component' ? '<div class="type-badge">Csomagolóanyag — nem eladó termék</div>' : '';
  }

  function priceCellHtml(price){
    return price ? `${parseInt(price, 10).toLocaleString('hu-HU')} Ft` : '—';
  }

  // ---- termékek betöltése/mentése a backend API-n keresztül ----
  function buildProductRowInnerHtml(p){
    const thumbHtml = p.gallery && p.gallery.length ? `<img src="${p.gallery[0]}" alt="">` : boxIconSvg;
    const galleryBadge = p.gallery && p.gallery.length > 1 ? `<span class="gallery-badge">+${p.gallery.length - 1} kép</span>` : '';
    const hasVariants = p.variants && p.variants.length > 0;
    const variantExtraSubHtml = (p.variants.length || p.addons.length)
      ? `<div class="variant-extra-sub">${[
          p.variants.length ? `${p.variants.length} variáns` : '',
          p.addons.length ? `${p.addons.length} extra` : ''
        ].filter(Boolean).join(' · ')}</div>`
      : '';
    const qtyControlHtml = hasVariants
      ? `<span class="qty-control">
          <button type="button" class="qty-btn" disabled title="Variánsonként a termék szerkesztésében állítható">−</button>
          <button type="button" class="qty-btn" disabled title="Variánsonként a termék szerkesztésében állítható">+</button>
        </span>`
      : `<span class="qty-control">
          <button type="button" class="qty-btn" data-action="minus" aria-label="Készlet csökkentése">−</button>
          <button type="button" class="qty-btn" data-action="plus" aria-label="Készlet növelése">+</button>
        </span>`;
    return `
      <td><span class="thumb">${thumbHtml}</span></td>
      <td><div class="prod-name">${p.name}${galleryBadge}</div>${stockSubHtml(p.deduction, p.linkedName, p.linkedQty, p.weight)}${typeBadgeHtml(p.type)}${variantExtraSubHtml}</td>
      <td class="num mono stock-price">${priceCellHtml(p.price)}</td>
      <td class="num mono stock-count">${p.stock}</td>
      <td class="num mono">${p.reserved}</td>
      <td class="num mono stock-available">${Math.max(0, p.stock - p.reserved)}</td>
      <td>${qtyControlHtml}</td>
      <td>${rowActionsHtml(p.archived)}</td>
    `;
  }
  function applyProductDataset(row, p){
    row.dataset.id = p.id;
    row.dataset.type = p.type;
    row.dataset.deduction = p.deduction;
    row.dataset.linkedName = p.linkedName || '';
    row.dataset.linkedQty = p.linkedQty || '';
    row.dataset.linkedWeight = p.linkedWeight || '';
    row.dataset.price = p.price || '';
    row.dataset.weight = p.weight || '';
    row.dataset.gallery = JSON.stringify(p.gallery || []);
    row.dataset.variants = JSON.stringify(p.variants || []);
    row.dataset.addons = JSON.stringify(p.addons || []);
    row.dataset.archived = p.archived ? 'true' : 'false';
    row.classList.toggle('row-archived', !!p.archived);
  }
  function renderProductRow(p){
    const row = document.createElement('tr');
    applyProductDataset(row, p);
    row.innerHTML = buildProductRowInnerHtml(p);
    return row;
  }
  function loadProducts(){
    fetch('/api/products.php')
      .then(res => res.json())
      .then(products => {
        inventoryTbody.innerHTML = '';
        products.forEach(p => inventoryTbody.appendChild(renderProductRow(p)));
        applyArchivedFilter();
      })
      .catch(() => pushNotification('Nem sikerült betölteni a termékeket a szerverről.', 'danger'));
  }
  loadProducts();

  function updateProductTypeUI(){
    const isComponent = productTypeSelect.value === 'component';
    productPriceInput.required = !isComponent;
    productPriceLabel.textContent = isComponent ? 'Ár (Ft) — opcionális' : 'Ár (Ft)';
  }
  productTypeSelect.addEventListener('change', updateProductTypeUI);

  function populateLinkedOptions(excludeRow){
    const current = editingProductRow ? editingProductRow.dataset.linkedName : '';
    productLinkedSelect.innerHTML = '<option value="">Nincs kapcsolt termék</option>';
    inventoryTbody.querySelectorAll('tr').forEach(row => {
      if (row === excludeRow) return;
      const name = getProdName(row);
      const opt = document.createElement('option');
      opt.value = name;
      opt.textContent = name;
      productLinkedSelect.appendChild(opt);
    });
    productLinkedSelect.value = current || '';
  }

  productLinkedSelect.addEventListener('change', () => {
    productLinkedQtyInput.disabled = !productLinkedSelect.value;
    productLinkedWeightInput.disabled = !productLinkedSelect.value;
    if (productLinkedSelect.value && !productLinkedQtyInput.value) productLinkedQtyInput.value = 1;
  });

  // ---- termék modal fülek (csak új termék felvitelekor aktív) ----
  const productTabBtns = Array.from(document.querySelectorAll('.product-tab-btn'));
  const productTabPanels = Array.from(document.querySelectorAll('.product-tab-panel'));

  function setActiveProductTab(tabName){
    productTabBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === tabName));
    productTabPanels.forEach(p => p.classList.toggle('active', p.dataset.tab === tabName));
    if (tabName === 'overview') renderProductOverview();
  }
  productTabBtns.forEach(btn => {
    btn.addEventListener('click', () => setActiveProductTab(btn.dataset.tab));
  });

  function renderProductOverview(){
    const name = productNameInput.value.trim() || '—';
    const type = productTypeSelect.value;
    const price = productPriceInput.value ? `${parseInt(productPriceInput.value, 10).toLocaleString('hu-HU')} Ft` : '—';
    const stock = productStockInput.value || '0';
    const weight = productWeightInput.value ? `${productWeightInput.value} g` : 'nincs megadva';

    const namedVariants = productVariants.filter(v => v.name && v.name.trim());
    const variantsHtml = namedVariants.length
      ? namedVariants.map(v => `<div class="overview-row"><span class="overview-label">${v.name}</span><span class="overview-value">${v.stock || 0} db készleten${v.weight ? ` · ${v.weight} g` : ''}</span></div>`).join('')
      : '<div class="overview-row"><span class="overview-value" style="color:var(--text-soft)">Nincs variáns</span></div>';

    const linkedName = productLinkedSelect.value;
    const linkedQty = productLinkedQtyInput.value || 1;
    const linkedWeight = productLinkedWeightInput.value;
    const linkedHtml = linkedName
      ? `<div class="overview-row"><span class="overview-label">${linkedName}</span><span class="overview-value">${linkedQty}× / rendelés${linkedWeight ? ` · +${linkedWeight} g` : ''}</span></div>`
      : '<div class="overview-row"><span class="overview-value" style="color:var(--text-soft)">Nincs kapcsolt termék</span></div>';

    const namedAddons = productAddons.filter(a => a.name && a.name.trim());
    const addonsHtml = namedAddons.length
      ? namedAddons.map(a => `<div class="overview-row"><span class="overview-label">${a.name}</span><span class="overview-value">+${parseInt(a.price, 10) || 0} Ft${a.linked ? ` · von: ${a.linked} (${a.linkedQty || 1}×)` : ''}${a.weight ? ` · +${a.weight} g` : ''}</span></div>`).join('')
      : '<div class="overview-row"><span class="overview-value" style="color:var(--text-soft)">Nincs extra</span></div>';

    document.getElementById('product-overview-content').innerHTML = `
      <p class="overview-section-title">Alapadatok</p>
      <div class="overview-row"><span class="overview-label">Név</span><span class="overview-value">${name}</span></div>
      <div class="overview-row"><span class="overview-label">Típus</span><span class="overview-value">${type === 'component' ? 'Kapcsolt / csomagolóanyag' : 'Rendes termék'}</span></div>
      <div class="overview-row"><span class="overview-label">Ár</span><span class="overview-value">${price}</span></div>
      <div class="overview-row"><span class="overview-label">Készlet</span><span class="overview-value">${stock} db</span></div>
      <div class="overview-row"><span class="overview-label">Súly</span><span class="overview-value">${weight}</span></div>
      <p class="overview-section-title">Variánsok</p>
      ${variantsHtml}
      <p class="overview-section-title">Kapcsolt termék</p>
      ${linkedHtml}
      <p class="overview-section-title">Választható extrák</p>
      ${addonsHtml}
    `;
  }

  function openProductModal(){
    productModal.classList.add('active');
    productNameInput.focus();
  }
  function closeProductModal(){
    productModal.classList.remove('active');
    productForm.reset();
    productImages = [];
    renderGalleryPreview();
    productVariants = [];
    productAddons = [];
    productStockInput.disabled = false;
    productLinkedQtyInput.disabled = true;
    productLinkedWeightInput.disabled = true;
    editingProductRow = null;
  }

  function openForCreateProduct(){
    editingProductRow = null;
    productForm.reset();
    productDeductionInput.value = 1;
    productTypeSelect.value = 'regular';
    updateProductTypeUI();
    productImages = [];
    renderGalleryPreview();
    productVariants = [];
    productAddons = [];
    renderVariantsList();
    renderAddonsList();
    productModalTitle.textContent = 'Új termék';
    productStockLabel.textContent = 'Kezdő készlet';
    productSubmitBtn.textContent = 'Termék mentése';
    populateLinkedOptions(null);
    productLinkedQtyInput.disabled = true;
    productLinkedWeightInput.disabled = true;
    setActiveProductTab('basic');
    openProductModal();
  }

  function openForEditProduct(row){
    editingProductRow = row;
    productNameInput.value = getProdName(row);
    productTypeSelect.value = row.dataset.type || 'regular';
    updateProductTypeUI();
    productPriceInput.value = row.dataset.price || '';
    productStockInput.value = row.querySelector('.stock-count').textContent.trim();
    productDeductionInput.value = row.dataset.deduction || 1;
    productWeightInput.value = row.dataset.weight || '';
    try { productImages = row.dataset.gallery ? JSON.parse(row.dataset.gallery) : []; }
    catch (e) { productImages = []; }
    renderGalleryPreview();
    try { productVariants = row.dataset.variants ? JSON.parse(row.dataset.variants) : []; }
    catch (e) { productVariants = []; }
    try { productAddons = row.dataset.addons ? JSON.parse(row.dataset.addons) : []; }
    catch (e) { productAddons = []; }
    renderVariantsList();
    renderAddonsList();
    productModalTitle.textContent = 'Termék szerkesztése';
    productStockLabel.textContent = 'Készleten';
    productSubmitBtn.textContent = 'Módosítások mentése';
    populateLinkedOptions(row);
    productLinkedQtyInput.value = row.dataset.linkedQty || '';
    productLinkedQtyInput.disabled = !row.dataset.linkedName;
    productLinkedWeightInput.value = row.dataset.linkedWeight || '';
    productLinkedWeightInput.disabled = !row.dataset.linkedName;
    setActiveProductTab('basic');
    openProductModal();
  }

  document.getElementById('new-product-btn').addEventListener('click', openForCreateProduct);
  document.getElementById('product-modal-close').addEventListener('click', closeProductModal);
  document.getElementById('product-cancel').addEventListener('click', closeProductModal);
  productModal.addEventListener('click', (e) => {
    if (e.target === productModal) closeProductModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && productModal.classList.contains('active')) closeProductModal();
  });

  productImageInput.addEventListener('change', () => {
    const files = Array.from(productImageInput.files || []);
    if (!files.length) return;
    Promise.all(files.map(file => new Promise(resolve => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.readAsDataURL(file);
    }))).then(results => {
      productImages = results;
      renderGalleryPreview();
    });
  });

  productForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = productNameInput.value.trim();
    const type = productTypeSelect.value;
    const price = parseInt(productPriceInput.value, 10) || 0;
    const deduction = parseInt(productDeductionInput.value, 10) || 1;
    const weight = productWeightInput.value.trim();
    const linkedName = productLinkedSelect.value;
    const linkedQty = linkedName ? (parseInt(productLinkedQtyInput.value, 10) || 1) : '';
    const linkedWeight = linkedName ? productLinkedWeightInput.value.trim() : '';

    const cleanVariants = productVariants
      .filter(v => v.name && v.name.trim())
      .map(v => ({ name: v.name.trim(), stock: parseInt(v.stock, 10) || 0, weight: v.weight ? parseInt(v.weight, 10) || '' : '' }));
    const cleanAddons = productAddons
      .filter(a => a.name && a.name.trim())
      .map(a => ({ name: a.name.trim(), price: parseInt(a.price, 10) || 0, weight: a.weight ? parseInt(a.weight, 10) || '' : '', linked: a.linked || '', linkedQty: a.linked ? (parseInt(a.linkedQty, 10) || 1) : '' }));
    const hasVariants = cleanVariants.length > 0;
    const stock = hasVariants
      ? cleanVariants.reduce((sum, v) => sum + v.stock, 0)
      : (parseInt(productStockInput.value, 10) || 0);

    const payload = {
      type, name, price,
      weight: weight ? parseInt(weight, 10) : null,
      stock, deduction,
      linkedName: linkedName || null,
      linkedQty: linkedName ? linkedQty : null,
      linkedWeight: linkedName ? (linkedWeight ? parseInt(linkedWeight, 10) : null) : null,
      gallery: productImages,
      variants: cleanVariants,
      addons: cleanAddons
    };

    const request = editingProductRow
      ? fetch(`/api/products.php?id=${editingProductRow.dataset.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
      : fetch('/api/products.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

    request.then(res => res.json()).then(saved => {
      if (editingProductRow) {
        applyProductDataset(editingProductRow, saved);
        editingProductRow.innerHTML = buildProductRowInnerHtml(saved);
      } else {
        inventoryTbody.appendChild(renderProductRow(saved));
      }
      pushNotification(`Termék mentve: <strong>${saved.name}</strong> (${saved.stock} db készleten)`, 'info');
      closeProductModal();
    }).catch(() => pushNotification('Nem sikerült menteni a terméket a szerverre.', 'danger'));
  });

  // ---- kupon modal (új + szerkesztés) ----
  const couponModal = document.getElementById('coupon-modal');
  const couponForm = document.getElementById('coupon-form');
  const couponsTbody = document.getElementById('coupons-tbody');
  const couponsEyebrow = document.querySelector('#coupons .view-eyebrow');
  const couponModalTitle = document.querySelector('#coupon-modal .modal-title');
  const couponSubmitBtn = document.getElementById('coupon-submit-btn');
  const couponCodeInput = document.getElementById('coupon-code');
  const couponDiscountTypeSelect = document.getElementById('coupon-discount-type');
  const couponDiscountValueLabel = document.getElementById('coupon-discount-value-label');
  const couponDiscountValueInput = document.getElementById('coupon-discount-value');
  const couponFromInput = document.getElementById('coupon-from');
  const couponStartNowCheckbox = document.getElementById('coupon-start-now');
  const couponUntilInput = document.getElementById('coupon-until');
  const couponLimitInput = document.getElementById('coupon-limit');
  const couponLimitPerEmailInput = document.getElementById('coupon-limit-per-email');
  const couponStatusSelect = document.getElementById('coupon-status');
  const couponNoteInput = document.getElementById('coupon-note');
  const couponExcludeList = document.getElementById('coupon-exclude-list');
  let activeCouponCount = 3;
  let editingRow = null;

  function updateCouponDiscountUI(){
    const isPercent = couponDiscountTypeSelect.value === 'percent';
    couponDiscountValueLabel.textContent = isPercent ? 'Mérték (%)' : 'Mérték (Ft)';
    couponDiscountValueInput.placeholder = isPercent ? 'pl. 10' : 'pl. 1500';
  }
  couponDiscountTypeSelect.addEventListener('change', updateCouponDiscountUI);

  function updateCouponFromUI(){
    couponFromInput.disabled = couponStartNowCheckbox.checked;
    if (couponStartNowCheckbox.checked) couponFromInput.value = '';
  }
  couponStartNowCheckbox.addEventListener('change', updateCouponFromUI);

  function formatDiscount(type, value){
    return type === 'percent' ? `${value}%` : `${parseInt(value, 10).toLocaleString('hu-HU')} Ft`;
  }

  function populateCouponExcludeList(selectedNames){
    couponExcludeList.innerHTML = '';
    const productNames = Array.from(inventoryTbody.querySelectorAll('.prod-name')).map(el => (el.childNodes[0] ? el.childNodes[0].textContent : el.textContent).trim());
    if (!productNames.length) {
      couponExcludeList.innerHTML = '<div class="checkbox-item">Nincs még termék a Raktárban.</div>';
      return;
    }
    productNames.forEach((name, i) => {
      const id = `coupon-exclude-${i}`;
      const item = document.createElement('div');
      item.className = 'checkbox-item';
      item.innerHTML = `
        <input type="checkbox" id="${id}" value="${name}" ${selectedNames.includes(name) ? 'checked' : ''}>
        <label for="${id}">${name}</label>
      `;
      couponExcludeList.appendChild(item);
    });
  }

  const editIconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
  const pillLabel = { success:'Aktív', danger:'Lejárt', neutral:'Inaktív' };

  function isoFromHunDate(text){
    const m = text.match(/(\d{4})\.(\d{2})\.(\d{2})/);
    return m ? `${m[1]}-${m[2]}-${m[3]}` : '';
  }

  function openCouponModal(){
    couponModal.classList.add('active');
    couponCodeInput.focus();
  }
  function closeCouponModal(){
    couponModal.classList.remove('active');
    couponForm.reset();
    editingRow = null;
  }

  function openForCreate(){
    editingRow = null;
    couponForm.reset();
    couponStatusSelect.value = 'success';
    couponDiscountTypeSelect.value = 'percent';
    updateCouponDiscountUI();
    couponStartNowCheckbox.checked = true;
    updateCouponFromUI();
    couponModalTitle.textContent = 'Új kupon';
    couponSubmitBtn.textContent = 'Kupon mentése';
    populateCouponExcludeList([]);
    openCouponModal();
  }

  function openForEdit(row){
    editingRow = row;
    const cells = row.children;
    couponCodeInput.value = cells[0].querySelector('.coupon-code-main').textContent.trim();
    couponDiscountTypeSelect.value = row.dataset.discountType || 'percent';
    updateCouponDiscountUI();
    couponDiscountValueInput.value = row.dataset.discountValue || '';
    couponUntilInput.value = row.dataset.until || isoFromHunDate(cells[2].textContent.trim());
    couponStartNowCheckbox.checked = row.dataset.startNow !== 'false';
    couponFromInput.value = row.dataset.from || '';
    updateCouponFromUI();
    const usageParts = cells[3].textContent.split('/').map(s => s.trim());
    couponLimitInput.value = (usageParts[1] && usageParts[1] !== '—') ? usageParts[1] : '';
    couponLimitPerEmailInput.value = row.dataset.limitPerEmail || '';
    couponNoteInput.value = row.dataset.note || '';
    const pillEl = cells[4].querySelector('.pill');
    const statusKey = ['success','danger','neutral'].find(k => pillEl.classList.contains('pill--' + k)) || 'success';
    couponStatusSelect.value = statusKey;
    couponModalTitle.textContent = 'Kupon szerkesztése';
    couponSubmitBtn.textContent = 'Módosítások mentése';
    const excluded = (row.dataset.excluded || '').split(',').map(s => s.trim()).filter(Boolean);
    populateCouponExcludeList(excluded);
    openCouponModal();
  }

  document.getElementById('new-coupon-btn').addEventListener('click', openForCreate);
  document.getElementById('coupon-modal-close').addEventListener('click', closeCouponModal);
  document.getElementById('coupon-cancel').addEventListener('click', closeCouponModal);
  couponModal.addEventListener('click', (e) => {
    if (e.target === couponModal) closeCouponModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && couponModal.classList.contains('active')) closeCouponModal();
  });
  couponsTbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.row-edit-btn');
    if (btn) openForEdit(btn.closest('tr'));
  });

  couponForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = couponCodeInput.value.trim().toUpperCase();
    const discountType = couponDiscountTypeSelect.value;
    const discountValue = parseInt(couponDiscountValueInput.value, 10) || 0;
    const discount = formatDiscount(discountType, discountValue);
    const untilRaw = couponUntilInput.value;
    const startNow = couponStartNowCheckbox.checked;
    const fromRaw = startNow ? '' : couponFromInput.value;
    const limit = couponLimitInput.value;
    const limitPerEmail = couponLimitPerEmailInput.value;
    const statusKey = couponStatusSelect.value;
    const excludedNames = Array.from(couponExcludeList.querySelectorAll('input:checked')).map(cb => cb.value);
    const note = couponNoteInput.value.trim();

    const untilText = untilRaw ? untilRaw.replaceAll('-', '.') + '-ig' : 'nincs lejárat';
    let until;
    if (fromRaw && untilRaw) until = `${fromRaw.replaceAll('-', '.')} – ${untilRaw.replaceAll('-', '.')}`;
    else if (fromRaw) until = `${fromRaw.replaceAll('-', '.')}-tól`;
    else until = untilText;
    const usedSoFar = editingRow
      ? (parseInt(editingRow.children[3].textContent.split('/')[0], 10) || 0)
      : 0;
    const usage = limit ? `${usedSoFar} / ${limit}` : `${usedSoFar} / —`;
    const subHtml = [
      excludedNames.length ? `<div class="exclude-sub">Kivéve: ${excludedNames.join(', ')}</div>` : '',
      limitPerEmail ? `<div class="email-limit-sub">Max ${limitPerEmail}× / email</div>` : '',
      note ? `<div class="coupon-note-sub" title="${note.replace(/"/g, '&quot;')}">${note}</div>` : ''
    ].join('');

    const rowHtml = `
      <td class="mono"><span class="coupon-code-main">${code}</span>${subHtml}</td>
      <td>${discount}</td>
      <td class="mono">${until}</td>
      <td class="num">${usage}</td>
      <td><span class="pill pill--${statusKey}">${pillLabel[statusKey]}</span></td>
      <td><button type="button" class="row-edit-btn" aria-label="Kupon szerkesztése">${editIconSvg}</button></td>
    `;

    if (editingRow){
      editingRow.dataset.excluded = excludedNames.join(',');
      editingRow.dataset.discountType = discountType;
      editingRow.dataset.discountValue = discountValue;
      editingRow.dataset.limitPerEmail = limitPerEmail;
      editingRow.dataset.note = note;
      editingRow.dataset.from = fromRaw;
      editingRow.dataset.until = untilRaw;
      editingRow.dataset.startNow = startNow ? 'true' : 'false';
      editingRow.innerHTML = rowHtml;
    } else {
      const row = document.createElement('tr');
      row.dataset.excluded = excludedNames.join(',');
      row.dataset.discountType = discountType;
      row.dataset.discountValue = discountValue;
      row.dataset.limitPerEmail = limitPerEmail;
      row.dataset.note = note;
      row.dataset.from = fromRaw;
      row.dataset.until = untilRaw;
      row.dataset.startNow = startNow ? 'true' : 'false';
      row.innerHTML = rowHtml;
      couponsTbody.prepend(row);
      activeCouponCount++;
      couponsEyebrow.textContent = `${activeCouponCount} aktív`;
    }

    pushNotification(`Kupon mentve: <strong>${code}</strong> (${discount}, ${pillLabel[statusKey]})`, 'info');
    closeCouponModal();
  });

  // ---- vásárlói részletek + rendeléstörténet ----
  const customerModal = document.getElementById('customer-modal');
  const customerNameEl = document.getElementById('customer-name');
  const customerAvatarEl = document.getElementById('customer-avatar');
  const customerContactEl = document.getElementById('customer-contact');
  const customerHistoryTbody = document.getElementById('customer-history-tbody');

  const customerStatusPill = {
    'Fizetve': 'success',
    'Függőben': 'warn',
    'Lemondva': 'danger'
  };

  const customerData = {
    'kovacs-anna': {
      name: 'Kovács Anna',
      contact: 'kovacs.anna@email.hu · +36 30 123 4567',
      orders: [
        { date: '2026.09.20.', order: '#0231', product: 'Alaptermék × 1', amount: '12 900 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestAB12cKovacs' },
        { date: '2026.08.02.', order: '#0198', product: 'Alaptermék × 1', amount: '9 800 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestQR56sKovacs' },
        { date: '2026.06.14.', order: '#0142', product: 'Alaptermék × 2', amount: '15 200 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestST78uKovacs' }
      ]
    },
    'nagy-bence': {
      name: 'Nagy Bence',
      contact: 'nagy.bence@email.hu · +36 20 987 6543',
      orders: [
        { date: '2026.09.19.', order: '#0230', product: 'Alaptermék × 1', amount: '9 400 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestCD34eNagyB' }
      ]
    },
    'szabo-petra': {
      name: 'Szabó Petra',
      contact: 'szabo.petra@email.hu · +36 70 555 1122',
      orders: [
        { date: '2026.09.19.', order: '#0229', product: 'Alaptermék × 2', amount: '21 800 Ft', status: 'Függőben' },
        { date: '2026.08.10.', order: '#0201', product: 'Alaptermék × 1', amount: '12 900 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestUV90wSzaboP' },
        { date: '2026.07.22.', order: '#0176', product: 'Alaptermék × 1', amount: '9 400 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestWX12ySzaboP' },
        { date: '2026.06.30.', order: '#0150', product: 'Alaptermék × 1', amount: '6 200 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestYZ34aSzaboP' },
        { date: '2026.06.02.', order: '#0133', product: 'Alaptermék × 1', amount: '12 900 Ft', status: 'Fizetve', stripeRef: 'pi_3PtestBC56dSzaboP' }
      ]
    }
  };

  function openCustomerModal(key){
    const data = customerData[key];
    if (!data) return;
    customerNameEl.textContent = data.name;
    customerAvatarEl.textContent = data.name.charAt(0);
    customerContactEl.textContent = data.contact;
    customerHistoryTbody.innerHTML = data.orders.map(o => `
      <tr class="row-clickable" data-order-num="${o.order}" title="Kattints a rendelés megnyitásához">
        <td class="mono">${o.date}</td>
        <td class="mono order-link-btn">${o.order}</td>
        <td>${o.product}</td>
        <td class="num">${o.amount}</td>
        <td>
          <span class="pill pill--${customerStatusPill[o.status] || 'neutral'}">${o.status}</span>
          ${o.stripeRef ? `<a class="stripe-ref-link" style="margin-left:6px; font-size:11.5px" href="${stripeDashboardUrl(o.stripeRef, 'test')}" target="_blank" rel="noopener">Stripe →</a>` : ''}
        </td>
      </tr>
    `).join('');
    customerModal.classList.add('active');
  }
  function closeCustomerModal(){
    customerModal.classList.remove('active');
  }

  document.getElementById('customers-tbody').addEventListener('click', (e) => {
    const row = e.target.closest('.row-clickable');
    if (row) openCustomerModal(row.dataset.customer);
  });

  customerHistoryTbody.addEventListener('click', (e) => {
    if (e.target.closest('.stripe-ref-link')) return;
    const historyRow = e.target.closest('.row-clickable');
    if (!historyRow) return;
    const orderNumRaw = historyRow.dataset.orderNum;
    const orderNum = orderNumRaw.replace('#', '');
    const orderRow = document.querySelector(`#orders-tbody tr[data-order="${orderNum}"]`);
    if (orderRow) {
      closeCustomerModal();
      openOrderModal(orderRow);
    } else {
      pushNotification(`A(z) ${orderNumRaw} rendelés régebbi, mint amit ez a demó tartalmaz — nincs hozzá részletnézet.`, 'warn');
    }
  });
  document.getElementById('customer-modal-close').addEventListener('click', closeCustomerModal);
  customerModal.addEventListener('click', (e) => {
    if (e.target === customerModal) closeCustomerModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && customerModal.classList.contains('active')) closeCustomerModal();
  });

  // ---- email sablon modal ----
  const emailModal = document.getElementById('email-modal');
  const emailForm = document.getElementById('email-form');
  const emailActiveSelect = document.getElementById('email-active');
  const emailSubjectInput = document.getElementById('email-subject');
  const emailBodyInput = document.getElementById('email-body');
  const emailsTbody = document.getElementById('emails-tbody');
  const emailPillLabel = { success: 'Aktív', neutral: 'Inaktív' };
  let editingEmailRow = null;

  function openEmailModal(row){
    editingEmailRow = row;
    const pillEl = row.querySelector('.pill');
    emailActiveSelect.value = pillEl.classList.contains('pill--success') ? 'success' : 'neutral';
    emailSubjectInput.value = row.dataset.subject || '';
    emailBodyInput.value = row.dataset.body || '';
    document.getElementById('email-modal-title').textContent = `Sablon szerkesztése — ${row.children[0].textContent.trim()}`;
    emailModal.classList.add('active');
    emailSubjectInput.focus();
  }
  function closeEmailModal(){
    emailModal.classList.remove('active');
    editingEmailRow = null;
  }

  emailsTbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.row-edit-btn');
    if (btn) openEmailModal(btn.closest('tr'));
  });
  document.getElementById('email-modal-close').addEventListener('click', closeEmailModal);
  document.getElementById('email-cancel').addEventListener('click', closeEmailModal);
  emailModal.addEventListener('click', (e) => {
    if (e.target === emailModal) closeEmailModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && emailModal.classList.contains('active')) closeEmailModal();
  });

  emailForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!editingEmailRow) return;
    const statusKey = emailActiveSelect.value;
    editingEmailRow.dataset.subject = emailSubjectInput.value;
    editingEmailRow.dataset.body = emailBodyInput.value;
    editingEmailRow.querySelector('.pill').className = `pill pill--${statusKey}`;
    editingEmailRow.querySelector('.pill').textContent = emailPillLabel[statusKey];
    const templateName = editingEmailRow.children[0].textContent.trim();
    pushNotification(`Email sablon frissítve: <strong>${templateName}</strong> (${emailPillLabel[statusKey]})`, 'info');
    closeEmailModal();
  });

  // ---- Stripe teszt / éles mód ----
  const stripeModeToggle = document.getElementById('stripe-mode-toggle');
  const testModeBanner = document.getElementById('test-mode-banner');
  const stripeKeysModeLabel = document.getElementById('stripe-keys-mode-label');
  const stripePubLabel = document.getElementById('stripe-pub-label');
  const stripeSecretLabel = document.getElementById('stripe-secret-label');
  const stripePubInput = document.getElementById('stripe-publishable-key');
  const stripeSecretInput = document.getElementById('stripe-secret-key');
  const stripeWebhookInput = document.getElementById('stripe-webhook-secret');
  const stripeConnectionPill = document.getElementById('stripe-connection-pill');
  const stripeDashboardLink = document.getElementById('stripe-dashboard-link');
  const paymentTxRows = document.querySelectorAll('#payment-transactions-tbody tr');
  const paymentTxEmpty = document.getElementById('payment-tx-empty');

  const stripeKeys = {
    test: { pub: '', secret: '', webhook: '' },
    live: { pub: '', secret: '', webhook: '' }
  };
  let currentStripeMode = 'test';

  function saveStripeFieldsToStore(mode){
    stripeKeys[mode] = {
      pub: stripePubInput.value,
      secret: stripeSecretInput.value,
      webhook: stripeWebhookInput.value
    };
  }

  function setStripeMode(mode){
    saveStripeFieldsToStore(currentStripeMode);
    currentStripeMode = mode;

    stripeModeToggle.querySelectorAll('.mode-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    testModeBanner.classList.toggle('active', mode === 'test');

    stripeKeysModeLabel.textContent = mode === 'test' ? 'Teszt kulcsok' : 'Éles kulcsok';
    stripePubLabel.textContent = 'Publishable key';
    stripeSecretLabel.textContent = 'Secret key';
    stripePubInput.placeholder = mode === 'test' ? 'pk_test_...' : 'pk_live_...';
    stripeSecretInput.placeholder = mode === 'test' ? 'sk_test_...' : 'sk_live_...';

    const stored = stripeKeys[mode];
    stripePubInput.value = stored.pub;
    stripeSecretInput.value = stored.secret;
    stripeWebhookInput.value = stored.webhook;

    stripeDashboardLink.href = mode === 'test'
      ? 'https://dashboard.stripe.com/test/payments'
      : 'https://dashboard.stripe.com/payments';
    document.querySelectorAll('.stripe-help-link').forEach(link => {
      const section = link.dataset.target === 'webhooks' ? 'webhooks' : 'apikeys';
      link.href = mode === 'test'
        ? `https://dashboard.stripe.com/test/${section}`
        : `https://dashboard.stripe.com/${section}`;
    });

    if (stored.secret) {
      stripeConnectionPill.className = 'pill pill--success';
      stripeConnectionPill.textContent = mode === 'test' ? 'Csatlakoztatva (teszt)' : 'Csatlakoztatva (éles)';
    } else {
      stripeConnectionPill.className = 'pill pill--warn';
      stripeConnectionPill.textContent = 'Még nincs API-kulcs beállítva';
    }

    let visibleCount = 0;
    paymentTxRows.forEach(row => {
      const match = row.dataset.mode === mode;
      row.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    paymentTxEmpty.style.display = visibleCount ? 'none' : 'block';
  }

  stripeModeToggle.addEventListener('click', (e) => {
    const btn = e.target.closest('.mode-btn');
    if (btn) {
      setStripeMode(btn.dataset.mode);
      pushNotification(`Stripe mód váltva: <strong>${btn.dataset.mode === 'test' ? 'Teszt' : 'Éles'}</strong>`, btn.dataset.mode === 'test' ? 'warn' : 'success');
    }
  });
  [stripePubInput, stripeSecretInput, stripeWebhookInput].forEach(input => {
    input.addEventListener('input', () => saveStripeFieldsToStore(currentStripeMode));
  });

  setStripeMode('test');

  // ---- weboldal API kulcs: generálás + másolás ----
  const siteKeyInput = document.getElementById('settings-site-api-key');
  const siteConnectionPill = document.getElementById('site-connection-pill');
  const siteConnectionDetail = document.getElementById('site-connection-detail');

  function setAiSetupAvailable(available){
    document.getElementById('open-ai-setup-btn').disabled = !available;
    document.getElementById('ai-setup-status-pill').className = available ? 'pill pill--success' : 'pill pill--warn';
    document.getElementById('ai-setup-status-pill').textContent = available ? 'Kapcsolat kész' : 'Előbb teszteld a kapcsolatot';

    document.getElementById('open-system-test-btn').disabled = !available;
    document.getElementById('open-system-test-live-btn').disabled = !available;
    document.getElementById('system-test-status-pill').className = available ? 'pill pill--success' : 'pill pill--warn';
    document.getElementById('system-test-status-pill').textContent = available ? 'Kapcsolat kész' : 'Előbb teszteld a kapcsolatot';
  }

  function resetSiteConnectionStatus(reason){
    siteConnectionPill.className = 'pill pill--neutral';
    siteConnectionPill.textContent = 'Még nem tesztelve';
    siteConnectionDetail.textContent = reason || 'Még nem futott le kapcsolat-ellenőrzés. Kattints a "Kapcsolat tesztelése" gombra, miután megadtad a domain nevet fent és beillesztetted az API kulcsot a weboldal kódjába.';
    setAiSetupAvailable(false);
  }

  document.getElementById('regenerate-site-key-btn').addEventListener('click', () => {
    const bytes = Array.from({ length: 24 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
    siteKeyInput.value = `whs_live_${bytes}`;
    siteKeyInput.focus();
    siteKeyInput.select();
    resetSiteConnectionStatus('Új kulcsot generáltál — a weboldal kódjában is frissítened kell, utána teszteld újra a kapcsolatot.');
    pushNotification('Weboldal API kulcs újragenerálva — a régi kulcs innentől érvénytelen', 'warn');
  });

  document.getElementById('test-site-connection-btn').addEventListener('click', () => {
    const domain = document.getElementById('settings-domain').value.trim();
    if (!domain) {
      siteConnectionPill.className = 'pill pill--danger';
      siteConnectionPill.textContent = 'Hiányzó domain';
      siteConnectionDetail.textContent = 'Add meg fent a domain nevét, mielőtt tesztelnéd a kapcsolatot.';
      setAiSetupAvailable(false);
      pushNotification('Kapcsolat-teszt sikertelen: nincs megadva domain név', 'warn');
      return;
    }
    const now = new Date();
    const stamp = `${now.toISOString().slice(0,10).replaceAll('-', '.')}. ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    siteConnectionPill.className = 'pill pill--success';
    siteConnectionPill.textContent = 'Csatlakozva';
    siteConnectionDetail.innerHTML = `<strong>${domain}</strong> — sikeres API-hívás érkezett erről a domainről, érvényes kulccsal. Utolsó ellenőrzés: ${stamp}.`;
    setAiSetupAvailable(true);
    pushNotification(`Kapcsolat rendben: <strong>${domain}</strong> sikeresen csatlakozik az API-kulccsal`, 'success');
  });

  const copySiteKeyBtn = document.getElementById('copy-site-key-btn');
  copySiteKeyBtn.addEventListener('click', () => {
    siteKeyInput.select();
    const finish = (ok) => {
      copySiteKeyBtn.textContent = ok ? 'Másolva ✓' : 'Nem sikerült';
      setTimeout(() => { copySiteKeyBtn.textContent = 'Másolás'; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(siteKeyInput.value).then(() => finish(true)).catch(() => finish(false));
    } else {
      try { finish(document.execCommand('copy')); } catch (e) { finish(false); }
    }
  });

  // ---- Adat-mezőleképezés a webshoppal ----
  document.getElementById('field-mapping-save-btn').addEventListener('click', () => {
    const filled = Array.from(document.querySelectorAll('#field-mapping-tbody input')).filter(i => i.value.trim()).length;
    const total = document.querySelectorAll('#field-mapping-tbody input').length;
    flashSaveStatus(document.getElementById('field-mapping-save-status'));
    pushNotification(`Adat-mezőleképezés mentve (${filled}/${total} változóhoz van megadva webshop-mezőnév)`, 'success');
  });

  // ---- AI automatikus alapadat-felismerés (szimuláció) ----
  function sleep(ms){ return new Promise(resolve => setTimeout(resolve, ms)); }

  function setAiStepStatus(stepId, status, detail){
    const step = document.querySelector(`.ai-step[data-step-id="${stepId}"]`);
    const icon = step.querySelector('.ai-step-icon');
    icon.className = 'ai-step-icon ' + status;
    icon.textContent = status === 'done' ? '✓' : status === 'failed' ? '!' : '…';
    step.querySelector('.ai-step-detail').textContent = detail || '';
  }

  function addAiImportedProduct(name, price){
    const row = document.createElement('tr');
    row.dataset.type = 'regular';
    row.dataset.deduction = 1;
    row.dataset.linkedName = '';
    row.dataset.linkedQty = '';
    row.dataset.price = price;
    row.dataset.weight = '';
    row.innerHTML = `
      <td><span class="thumb">${boxIconSvg}</span></td>
      <td><div class="prod-name">${name}</div><div class="stock-sub">AI-felismerve a weboldalról — ellenőrizd az adatokat, és add meg a súlyt</div></td>
      <td class="num mono stock-price">${priceCellHtml(price)}</td>
      <td class="num mono stock-count">0</td>
      <td class="num mono">0</td>
      <td class="num mono stock-available">0</td>
      <td>
        <span class="qty-control">
          <button type="button" class="qty-btn" data-action="minus" aria-label="Készlet csökkentése">−</button>
          <button type="button" class="qty-btn" data-action="plus" aria-label="Készlet növelése">+</button>
        </span>
      </td>
      <td>${rowActionsHtml(false)}</td>
    `;
    inventoryTbody.appendChild(row);
  }

  async function runAiSetup(){
    document.querySelectorAll('#ai-setup-steps .ai-step-icon').forEach(icon => { icon.className = 'ai-step-icon pending'; icon.textContent = '…'; });
    document.querySelectorAll('#ai-setup-steps .ai-step-detail').forEach(d => { d.textContent = ''; });
    document.getElementById('ai-setup-summary').style.display = 'none';
    document.getElementById('ai-setup-close-btn').style.display = 'none';
    document.getElementById('ai-setup-modal').classList.add('active');

    const domain = document.getElementById('settings-domain').value.trim() || 'a megadott domain';

    await sleep(700);
    setAiStepStatus('connect', 'done', `Sikeresen elérve: ${domain}`);

    await sleep(900);
    addAiImportedProduct('Nyisd Ki Amikor — Prémium csomag', 15900);
    addAiImportedProduct('Nyisd Ki Amikor — Mini csomag', 3900);
    setAiStepStatus('products', 'done', '2 új termék importálva (név, ár) — ellenőrizd és egészítsd ki őket a Termékek oldalon.');

    await sleep(900);
    const legalCount = document.querySelectorAll('#legal-docs-tbody tr').length;
    setAiStepStatus('legal', 'done', `${legalCount}/${legalCount} dokumentum megtalálva és megerősítve a weboldalon.`);

    await sleep(700);
    setAiStepStatus('payment', 'done', `Stripe ${currentStripeMode === 'test' ? 'teszt' : 'éles'} kapcsolat működik.`);

    await sleep(700);
    setAiStepStatus('weight', 'failed', 'Fizikai súly nem állapítható meg egy weboldal szövegéből — minden termékhez kézzel kell megadni a Termékeknél (a Kvikk-küldéshez kötelező).');

    await sleep(700);
    setAiStepStatus('kvikk', 'failed', 'A Kvikk API-kulcs biztonsági okból nem automatizálható — ezt kézzel kell megadnod a szerveroldali .env fájlban.');

    await sleep(400);
    const summaryItems = [
      { ok: true, text: '2 termék importálva (név, ár) a weboldalról — nézd át a Termékeknél.' },
      { ok: true, text: `${legalCount} jogi dokumentum azonosítva és megerősítve.` },
      { ok: true, text: 'Stripe-kapcsolat ellenőrizve, működik.' },
      { ok: false, text: 'Termék súlyok — minden termékhez kézzel kell megadni (Termékek → szerkesztés → Súly mező).' },
      { ok: false, text: 'Kvikk API-kulcs — kézzel kell beállítani a szerveren, ez sosem lesz automatikus.' }
    ];
    document.getElementById('ai-setup-summary-list').innerHTML = summaryItems.map(item => `
      <div class="ai-summary-row">
        <span class="ai-step-icon ${item.ok ? 'done' : 'failed'}">${item.ok ? '✓' : '!'}</span>
        <span>${item.text}</span>
      </div>
    `).join('');
    document.getElementById('ai-setup-summary').style.display = 'block';
    document.getElementById('ai-setup-close-btn').style.display = 'inline-flex';
    pushNotification('Automatikus alapadat-felismerés kész — nézd át az eredményt.', 'success');
  }

  document.getElementById('open-ai-setup-btn').addEventListener('click', runAiSetup);
  document.getElementById('ai-setup-modal-close').addEventListener('click', () => document.getElementById('ai-setup-modal').classList.remove('active'));
  document.getElementById('ai-setup-close-btn').addEventListener('click', () => document.getElementById('ai-setup-modal').classList.remove('active'));
  document.getElementById('ai-setup-modal').addEventListener('click', (e) => {
    if (e.target.id === 'ai-setup-modal') document.getElementById('ai-setup-modal').classList.remove('active');
  });

  // ---- rendszerteszt: teljes rendelési folyamat ellenőrzése (teszt / éles 300 Ft) ----
  function setSystemTestStepStatus(stepId, status, detail){
    const step = document.querySelector(`#system-test-steps .ai-step[data-step-id="${stepId}"]`);
    const icon = step.querySelector('.ai-step-icon');
    icon.className = 'ai-step-icon ' + status;
    icon.textContent = status === 'done' ? '✓' : status === 'skipped' ? '—' : status === 'error' ? '✕' : '!';
    step.querySelector('.ai-step-detail').textContent = detail || '';
  }

  function closeSystemTestModal(){ document.getElementById('system-test-modal').classList.remove('active'); }

  function openSystemTestModal(mode){
    document.getElementById('system-test-modal-title').textContent = mode === 'live' ? 'Éles rendszerteszt (300 Ft valódi fizetés)' : 'Rendszerteszt (teszt mód)';
    document.getElementById('system-test-modal-subtitle').textContent = mode === 'live'
      ? 'Egy tényleges, kis összegű élő fizetéssel ellenőrzi a teljes láncot.'
      : 'Stripe teszt-kártyával és Kvikk teszt-adatokkal ellenőrzi a teljes láncot, valódi pénzmozgás nélkül.';
    document.querySelectorAll('#system-test-steps .ai-step-icon').forEach(icon => { icon.className = 'ai-step-icon pending'; icon.textContent = '…'; });
    document.querySelectorAll('#system-test-steps .ai-step-detail').forEach(d => { d.textContent = ''; });
    document.getElementById('system-test-summary').style.display = 'none';
    document.getElementById('system-test-close-btn').style.display = 'none';
    document.getElementById('system-test-live-confirm').checked = false;
    document.getElementById('system-test-live-start-btn').disabled = true;

    if (mode === 'live') {
      document.getElementById('system-test-live-gate').style.display = 'block';
      document.getElementById('system-test-run').style.display = 'none';
    } else {
      document.getElementById('system-test-live-gate').style.display = 'none';
      document.getElementById('system-test-run').style.display = 'block';
    }
    document.getElementById('system-test-modal').classList.add('active');
    if (mode !== 'live') runSystemTest('test');
  }

  document.getElementById('system-test-live-confirm').addEventListener('change', (e) => {
    document.getElementById('system-test-live-start-btn').disabled = !e.target.checked;
  });
  document.getElementById('system-test-live-start-btn').addEventListener('click', () => {
    document.getElementById('system-test-live-gate').style.display = 'none';
    document.getElementById('system-test-run').style.display = 'block';
    runSystemTest('live');
  });

  async function runSystemTest(mode){
    await sleep(600);
    setSystemTestStepStatus('connect', 'done', 'Stripe és Kvikk API elérhető, a kulcsok érvényesek.');

    await sleep(900);
    setSystemTestStepStatus('payment', 'done', mode === 'live'
      ? 'Valódi bankkártyás terhelés: 300 Ft — sikeres.'
      : 'Teszt-kártyával (4242 4242 4242 4242) szimulált fizetés — sikeres.');

    await sleep(800);
    setSystemTestStepStatus('webhook', 'done', 'A backend fogadta a Stripe payment_intent.succeeded eseményét.');

    await sleep(800);
    setSystemTestStepStatus('order', 'done', 'A rendelés létrejött és megjelent a Rendelések listájában.');

    await sleep(900);
    const missingWeightRows = Array.from(inventoryTbody.querySelectorAll('tr'))
      .filter(row => row.dataset.type === 'regular' && !row.dataset.weight);
    const kvikkOk = missingWeightRows.length === 0;
    if (kvikkOk) {
      setSystemTestStepStatus('kvikk', 'done', 'A Kvikk API elfogadta a csomagot, a címke elkészült.');
    } else {
      const names = missingWeightRows.map(row => getProdName(row)).join(', ');
      setSystemTestStepStatus('kvikk', 'error', `Elakadt itt: a Kvikk API "missing_weight" hibát adott — ${missingWeightRows.length} terméknél nincs megadva a súly (${names}). Töltsd ki a Termékeknél, majd futtasd újra a tesztet.`);
    }

    await sleep(700);
    if (kvikkOk) {
      setSystemTestStepStatus('email', 'done', 'A visszaigazoló email sikeresen kiküldve (szerver PHP mail()).');
    } else {
      setSystemTestStepStatus('email', 'skipped', 'Kihagyva, mert az előző lépésnél (Kvikk) elakadt a folyamat.');
    }

    await sleep(500);
    if (mode === 'live') {
      setSystemTestStepStatus('cleanup', 'failed', 'Ez valódi terhelés volt — ha csak tesztelés volt a cél, indíts hozzá visszatérítést (Fizetés oldal → a tranzakció Stripe-linkje → Visszatérítés), különben a 300 Ft nálad marad bevételként.');
    } else {
      setSystemTestStepStatus('cleanup', 'done', 'Nincs teendő — ez csak teszt-tranzakció volt, nem kell visszatéríteni.');
    }

    await sleep(300);
    const summaryItems = [
      { status: 'done', text: 'Stripe fizetés + webhook + rendelés létrehozása rendben.' },
      kvikkOk
        ? { status: 'done', text: 'Kvikk csomag-létrehozás és email-küldés rendben.' }
        : { status: 'error', text: `Elakadt a Kvikk lépésnél: ${missingWeightRows.length} terméknél hiányzik a súly — emiatt az email-küldés is kimaradt.` },
      mode === 'live'
        ? { status: 'failed', text: 'Éles teszt volt — ha nem akartad megtartani a 300 Ft-ot, térítsd vissza a Fizetés oldalról.' }
        : { status: 'done', text: 'Teszt mód volt, valódi pénz nem mozgott.' }
    ];
    document.getElementById('system-test-summary-list').innerHTML = summaryItems.map(item => `
      <div class="ai-summary-row">
        <span class="ai-step-icon ${item.status}">${item.status === 'done' ? '✓' : item.status === 'error' ? '✕' : '!'}</span>
        <span>${item.text}</span>
      </div>
    `).join('');
    document.getElementById('system-test-summary').style.display = 'block';
    document.getElementById('system-test-close-btn').style.display = 'inline-flex';
    pushNotification(kvikkOk ? 'Rendszerteszt kész — a teljes lánc rendben végigfutott.' : 'Rendszerteszt elakadt a Kvikk lépésnél — hiányzó súly miatt.', kvikkOk ? 'success' : 'warn');
  }

  document.getElementById('open-system-test-btn').addEventListener('click', () => openSystemTestModal('test'));
  document.getElementById('open-system-test-live-btn').addEventListener('click', () => openSystemTestModal('live'));
  document.getElementById('system-test-modal-close').addEventListener('click', closeSystemTestModal);
  document.getElementById('system-test-close-btn').addEventListener('click', closeSystemTestModal);
  document.getElementById('system-test-modal').addEventListener('click', (e) => {
    if (e.target.id === 'system-test-modal') closeSystemTestModal();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && document.getElementById('system-test-modal').classList.contains('active')) closeSystemTestModal(); });

  // ---- rendelés részletnézet ----
  function stripeDashboardUrl(ref, mode){
    const m = mode || currentStripeMode;
    return `https://dashboard.stripe.com/${m === 'test' ? 'test/' : ''}payments/${ref}`;
  }
  const orderModal = document.getElementById('order-modal');
  const orderDetails = {
    '0231': { items: [{ name: 'Alaptermék', qty: 1, unit: 12900 }], address: 'Kovács Anna, 1051 Budapest, Váci utca 10.', ref: 'pi_3PtestAB12cKovacs' },
    '0230': { items: [{ name: 'Alaptermék', qty: 1, unit: 9400 }], address: 'Nagy Bence, 4024 Debrecen, Piac utca 5.', ref: 'pi_3PtestCD34eNagyB' },
    '0229': { items: [{ name: 'Alaptermék', qty: 2, unit: 10900 }], address: 'Szabó Petra, 6720 Szeged, Kárász utca 3.', ref: '— (fizetésre vár)' },
    '0228': { items: [{ name: 'Alaptermék', qty: 1, unit: 12900 }], address: 'Tóth Dávid, 9021 Győr, Baross Gábor út 15.', ref: '12 900 Ft — futár beszedte, kézbesítéskor (Kvikk visszaigazolva)' },
    '0227': { items: [{ name: 'Alaptermék', qty: 1, unit: 6200 }], address: 'Horváth Eszter, 7621 Pécs, Rákóczi út 8.', ref: '— (lemondva)' },
    '0226': { items: [{ name: 'Alaptermék', qty: 1, unit: 8900 }], address: 'Kiss Gábor, 3300 Eger, Dobó tér 2.', ref: '8 900 Ft — a futár szedi be kézbesítéskor' },
    '0212': { items: [{ name: 'Alaptermék', qty: 1, unit: 9800 }], address: 'Teszt Judit, 2000 Szentendre, Fő tér 1.', ref: 'pi_3PtestEF11jTesztJ' },
    '0205': { items: [{ name: 'Alaptermék', qty: 1, unit: 12900 }], address: 'Varga Zsolt, 8000 Székesfehérvár, Városház tér 3.', ref: 'pi_3PtestGH22kVargaZs' },
    '0193': { items: [{ name: 'Alaptermék', qty: 1, unit: 6200 }], address: 'Fekete Márk, 7100 Szekszárd, Béla király tér 6.', ref: '— (visszaküldés úton, a visszatérítés még nem esedékes)' }
  };
  const methodPillHtml = {
    card: { cls: 'pill pill--neutral', text: 'Kártya — Stripe' },
    cod: { cls: 'pill pill--neutral', text: 'Utánvét — Kvikk' }
  };
  let currentInvoiceFileName = '';
  let currentInvoiceFileUrl = '';

  function buildSyntheticOrderRow(orderNum){
    const returnRow = document.querySelector(`#returns-tbody tr[data-order="${orderNum}"]`);
    if (!returnRow || !orderDetails[orderNum]) return null;
    const tr = document.createElement('tr');
    tr.dataset.order = orderNum;
    tr.dataset.paymentMethod = 'card';
    tr.dataset.invoiced = 'true';
    tr.dataset.invoiceFile = '';
    tr.dataset.invoiceFileUrl = '';
    const customer = returnRow.children[2].textContent.trim();
    const amount = returnRow.children[3].textContent.trim();
    const dateText = returnRow.children[0].textContent.trim();
    const shippingPillHtml = returnRow.dataset.kvikkStatus === 'returned'
      ? '<span class="pill pill--success">Visszaküldve</span>'
      : '<span class="pill pill--warn">Úton visszafelé</span>';
    tr.innerHTML = `
      <td class="mono">#${orderNum}</td>
      <td>${customer}</td>
      <td class="num">${amount}</td>
      <td><span class="pill pill--success">Fizetve</span></td>
      <td>${shippingPillHtml}</td>
      <td><span class="pill pill--success order-invoice-pill">Kiállítva</span></td>
      <td class="mono">${dateText}</td>
    `;
    return tr;
  }

  function openOrderByNumber(orderNum){
    const orderRow = document.querySelector(`#orders-tbody tr[data-order="${orderNum}"]`);
    if (orderRow) { openOrderModal(orderRow); return true; }
    const synthetic = buildSyntheticOrderRow(orderNum);
    if (synthetic) { openOrderModal(synthetic); return true; }
    pushNotification(`A(z) #${orderNum} rendelés régebbi, mint amit ez a demó tartalmaz — nincs hozzá részletnézet.`, 'warn');
    return false;
  }

  function refreshInvoiceTableCell(row){
    const pill = row.querySelector('.order-invoice-pill');
    if (!pill) return;
    if (row.dataset.invoiced === 'true') {
      pill.className = 'pill pill--success order-invoice-pill';
      pill.textContent = 'Kiállítva';
    } else {
      pill.className = 'pill pill--warn order-invoice-pill';
      pill.textContent = 'Nincs kiállítva';
    }
  }

  function computeOrderWeightBreakdown(items){
    const invRows = Array.from(inventoryTbody.querySelectorAll('tr'));
    const breakdown = [];
    let total = 0;
    let hasMissing = false;
    items.forEach(it => {
      const prodRow = invRows.find(r => getProdName(r) === it.name);
      const unitWeight = prodRow ? (parseInt(prodRow.dataset.weight, 10) || 0) : 0;
      if (unitWeight) {
        const lineWeight = unitWeight * it.qty;
        breakdown.push({ label: it.name, sub: `${it.qty}× ${unitWeight} g`, weight: lineWeight });
        total += lineWeight;
      } else {
        hasMissing = true;
        breakdown.push({ label: it.name, sub: `${it.qty}×`, weight: null });
      }
      const linkedName = prodRow ? prodRow.dataset.linkedName : '';
      if (linkedName) {
        const linkedQty = parseInt(prodRow.dataset.linkedQty, 10) || 1;
        let linkedUnitWeight = parseInt(prodRow.dataset.linkedWeight, 10) || 0;
        if (!linkedUnitWeight) {
          const linkedRow = invRows.find(r => getProdName(r) === linkedName);
          linkedUnitWeight = linkedRow ? (parseInt(linkedRow.dataset.weight, 10) || 0) : 0;
        }
        const totalLinkedQty = linkedQty * it.qty;
        if (linkedUnitWeight) {
          const lineWeight = linkedUnitWeight * totalLinkedQty;
          breakdown.push({ label: `+ ${linkedName} (kapcsolt)`, sub: `${totalLinkedQty}× ${linkedUnitWeight} g`, weight: lineWeight });
          total += lineWeight;
        } else {
          hasMissing = true;
          breakdown.push({ label: `+ ${linkedName} (kapcsolt)`, sub: `${totalLinkedQty}×`, weight: null });
        }
      }
    });
    return { breakdown, total, hasMissing };
  }

  // ---- Excel export (rendelések, szállítások, vevők, EPR-anyagok és súlyok) ----
  function parseShortDate(text){
    const m = (text || '').trim().match(/(\d{2})\.(\d{2})\.?$/);
    if (!m) return null;
    return new Date(2026, parseInt(m[1], 10) - 1, parseInt(m[2], 10));
  }

  function parseDateInputLocal(value){
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function getExportDateRange(){
    const mode = document.querySelector('#export-range-toggle .mode-btn.active').dataset.range;
    if (mode === 'all') return null;
    const fromVal = document.getElementById('export-range-from').value;
    const toVal = document.getElementById('export-range-to').value;
    return {
      from: parseDateInputLocal(fromVal),
      to: parseDateInputLocal(toVal)
    };
  }

  function dateInExportRange(date, range){
    if (!range || !date) return true;
    if (range.from && date < range.from) return false;
    if (range.to && date > range.to) return false;
    return true;
  }

  function buildExportOrdersRows(range){
    return Array.from(document.querySelectorAll('#orders-tbody tr'))
      .filter(row => dateInExportRange(parseShortDate(row.children[6].textContent), range))
      .map(row => ({
        'Rendelésszám': '#' + row.dataset.order,
        'Vevő': row.children[1].textContent.trim(),
        'Összeg': row.children[2].textContent.trim(),
        'Fizetés': row.children[3].querySelector('.pill').textContent.trim(),
        'Szállítás': row.children[4].querySelector('.pill') ? row.children[4].querySelector('.pill').textContent.trim() : '—',
        'Számla': row.children[5].querySelector('.pill').textContent.trim(),
        'Dátum': row.children[6].textContent.trim()
      }));
  }

  function isOrderCancelled(row){
    return row.children[3].querySelector('.pill').textContent.trim() === 'Lemondva';
  }

  function buildExportShippingRows(range){
    return Array.from(document.querySelectorAll('#orders-tbody tr'))
      .filter(row => !isOrderCancelled(row) && dateInExportRange(parseShortDate(row.children[6].textContent), range))
      .map(row => {
        const detail = orderDetails[row.dataset.order] || {};
        const shipPill = row.children[4].querySelector('.pill');
        return {
          'Rendelésszám': '#' + row.dataset.order,
          'Vevő': row.children[1].textContent.trim(),
          'Fizetési mód': row.dataset.paymentMethod === 'cod' ? 'Utánvét — Kvikk' : 'Kártya — Stripe',
          'Szállítási állapot': shipPill ? shipPill.textContent.trim() : '—',
          'Cím': detail.address || '',
          'Dátum': row.children[6].textContent.trim()
        };
      });
  }

  function buildExportCustomersRows(range){
    return Array.from(document.querySelectorAll('#customers-tbody tr'))
      .filter(row => dateInExportRange(parseShortDate(row.children[4].textContent), range))
      .map(row => ({
        'Név': row.children[0].textContent.trim(),
        'Email': row.children[1].textContent.trim(),
        'Telefon': row.children[2].textContent.trim(),
        'Rendelések száma': row.children[3].textContent.trim(),
        'Utolsó vásárlás': row.children[4].textContent.trim()
      }));
  }

  function buildExportEprRows(range){
    const rows = [];
    Array.from(document.querySelectorAll('#orders-tbody tr')).forEach(row => {
      if (isOrderCancelled(row)) return;
      const orderDate = row.children[6].textContent.trim();
      if (!dateInExportRange(parseShortDate(orderDate), range)) return;
      const detail = orderDetails[row.dataset.order];
      if (!detail) return;
      const { breakdown } = computeOrderWeightBreakdown(detail.items);
      breakdown.forEach(item => {
        rows.push({
          'Rendelésszám': '#' + row.dataset.order,
          'Dátum': orderDate,
          'Vevő': row.children[1].textContent.trim(),
          'Tétel (anyag/termék)': item.label,
          'Mennyiség': item.sub,
          'Összsúly (g)': item.weight !== null ? item.weight : 'nincs megadva'
        });
      });
    });
    return rows;
  }

  function updateExportSummaryNote(){
    const range = getExportDateRange();
    const orderCount = buildExportOrdersRows(range).length;
    const custCount = buildExportCustomersRows(range).length;
    document.getElementById('export-summary-note').textContent = `Ebbe az időszakba ${orderCount} rendelés és ${custCount} vevő esik bele.`;
  }

  const exportModal = document.getElementById('export-modal');
  function openExportModal(){
    updateExportSummaryNote();
    exportModal.classList.add('active');
  }
  function closeExportModal(){ exportModal.classList.remove('active'); }

  document.getElementById('open-export-btn').addEventListener('click', openExportModal);
  document.getElementById('export-modal-close').addEventListener('click', closeExportModal);
  document.getElementById('export-cancel').addEventListener('click', closeExportModal);
  exportModal.addEventListener('click', (e) => { if (e.target === exportModal) closeExportModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && exportModal.classList.contains('active')) closeExportModal(); });

  document.getElementById('export-range-toggle').addEventListener('click', (e) => {
    const btn = e.target.closest('.mode-btn');
    if (!btn) return;
    document.querySelectorAll('#export-range-toggle .mode-btn').forEach(b => b.classList.toggle('active', b === btn));
    document.getElementById('export-range-fields').style.display = btn.dataset.range === 'custom' ? 'grid' : 'none';
    updateExportSummaryNote();
  });
  ['export-range-from', 'export-range-to'].forEach(id => {
    document.getElementById(id).addEventListener('input', updateExportSummaryNote);
  });
  ['export-check-orders', 'export-check-shipping', 'export-check-customers', 'export-check-epr'].forEach(id => {
    document.getElementById(id).addEventListener('change', updateExportSummaryNote);
  });

  document.getElementById('export-run-btn').addEventListener('click', () => {
    if (typeof XLSX === 'undefined') {
      pushNotification('Az Excel export könyvtár nem eltöltődött (nincs internetkapcsolat?) — próbáld újra.', 'danger');
      return;
    }
    const range = getExportDateRange();
    const wb = XLSX.utils.book_new();
    let sheetCount = 0;

    if (document.getElementById('export-check-orders').checked) {
      XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(buildExportOrdersRows(range)), 'Rendelések');
      sheetCount++;
    }
    if (document.getElementById('export-check-shipping').checked) {
      XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(buildExportShippingRows(range)), 'Szállítások');
      sheetCount++;
    }
    if (document.getElementById('export-check-customers').checked) {
      XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(buildExportCustomersRows(range)), 'Vevők');
      sheetCount++;
    }
    if (document.getElementById('export-check-epr').checked) {
      XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(buildExportEprRows(range)), 'EPR - anyagok és súlyok');
      sheetCount++;
    }

    if (!sheetCount) {
      pushNotification('Válassz legalább egy exportálandó adatkört.', 'warn');
      return;
    }

    const stamp = new Date().toISOString().slice(0, 10).replaceAll('-', '');
    XLSX.writeFile(wb, `webshop-export-${stamp}.xlsx`);
    pushNotification(`Excel export elkészült (${sheetCount} munkalap) — a letöltés elindult.`, 'success');
    closeExportModal();
  });

  // ---- Kvikk szállítási díjak (Szállítás oldal) — a feltöltött árlistából, súly szerint sávosítva ----
  const KVIKK_WEIGHT_TIERS = [
    { max: 1000, label: '0 – 1000 g', myPrice: 1490,
      mpl: 1160, mpl_posta: 1060, mpl_postapont: 1110, mpl_automata: 860,
      foxpost: 1310, foxpost_zpont: 800, dpd: 1320, dpd_csomagpont: 880,
      gls: 1580, gls_csomagpont: 1260, gls_automata: 1160 },
    { max: 2000, label: '1001 – 2000 g', myPrice: 1490,
      mpl: 1290, mpl_posta: 1080, mpl_postapont: 1140, mpl_automata: 880,
      foxpost: 1340, foxpost_zpont: 800, dpd: 1320, dpd_csomagpont: 880,
      gls: 1590, gls_csomagpont: 1280, gls_automata: 1170 },
    { max: 3000, label: '2001 – 3000 g', myPrice: 1690,
      mpl: 1340, mpl_posta: 1130, mpl_postapont: 1190, mpl_automata: 890,
      foxpost: 1360, foxpost_zpont: 800, dpd: 1320, dpd_csomagpont: 880,
      gls: 1720, gls_csomagpont: 1400, gls_automata: 1300 },
    { max: 4000, label: '3001 – 4000 g', myPrice: 1690,
      mpl: 1390, mpl_posta: 1170, mpl_postapont: 1220, mpl_automata: 900,
      foxpost: 1360, foxpost_zpont: 800, dpd: 1560, dpd_csomagpont: 990,
      gls: 1920, gls_csomagpont: 1620, gls_automata: 1500 },
    { max: 5000, label: '4001 – 5000 g', myPrice: 1890,
      mpl: 1480, mpl_posta: 1210, mpl_postapont: 1270, mpl_automata: 910,
      foxpost: 1360, foxpost_zpont: 800, dpd: 1560, dpd_csomagpont: 990,
      gls: 1940, gls_csomagpont: 1620, gls_automata: 1530 },
    { max: 10000, label: '5001 – 10000 g', myPrice: 1990,
      mpl: 1560, mpl_posta: 1270, mpl_postapont: 1330, mpl_automata: 960,
      foxpost: 1360, foxpost_zpont: 800, dpd: 1560, dpd_csomagpont: 990,
      gls: 2230, gls_csomagpont: 1940, gls_automata: 1810 }
  ];
  const KVIKK_COMPARE_COLUMNS = [
    { key: 'mpl', label: 'MPL házhoz' },
    { key: 'mpl_posta', label: 'MPL Postán maradó' },
    { key: 'mpl_postapont', label: 'MPL Postapont' },
    { key: 'mpl_automata', label: 'MPL Automata' },
    { key: 'foxpost', label: 'Foxpost házhoz' },
    { key: 'foxpost_zpont', label: 'Foxpost Z-Pont/Z-Box' },
    { key: 'dpd', label: 'DPD házhoz' },
    { key: 'dpd_csomagpont', label: 'DPD Csomagpont' },
    { key: 'gls', label: 'GLS házhoz' },
    { key: 'gls_csomagpont', label: 'GLS CsomagPont' },
    { key: 'gls_automata', label: 'GLS Automata' }
  ];
  const KVIKK_COD_TIERS = [
    { max: 10000, label: '0 – 10 000 Ft', mpl: [190, 0.3], foxpost: [240, 0.8], dpd: [250, 1.8], gls: [350, 1.3] },
    { max: 20000, label: '10 001 – 20 000 Ft', mpl: [190, 0.3], foxpost: [240, 0.8], dpd: [300, 1.8], gls: [450, 1.3] },
    { max: 50000, label: '20 001 – 50 000 Ft', mpl: [250, 0.3], foxpost: [180, 1.3], dpd: [300, 1.8], gls: [610, 1.3] },
    { max: 100000, label: '50 001 – 100 000 Ft', mpl: [410, 0.3], foxpost: [0, 1.8], dpd: [350, 1.8], gls: [720, 1.3] }
  ];

  function renderKvikkPriceTables(){
    document.getElementById('kvikk-weight-tiers-tbody').innerHTML = KVIKK_WEIGHT_TIERS.map((t, i) => {
      const cells = KVIKK_COMPARE_COLUMNS.map(col => {
        const cost = t[col.key];
        const delta = t.myPrice - cost;
        const cls = delta >= 0 ? 'kvikk-margin-positive' : 'kvikk-margin-negative';
        const deltaText = (delta >= 0 ? '+' : '') + delta.toLocaleString('hu-HU');
        return `<td class="num kvikk-cost-cell ${cls}">${cost.toLocaleString('hu-HU')} Ft<span class="kvikk-cost-delta">${deltaText} Ft</span></td>`;
      }).join('');
      return `
        <tr>
          <td class="mono">${t.label}</td>
          <td class="kvikk-my-price-cell"><input type="number" min="0" value="${t.myPrice}" data-tier-index="${i}" class="kvikk-my-price-input"></td>
          ${cells}
        </tr>
      `;
    }).join('');
    document.getElementById('kvikk-cod-tiers-tbody').innerHTML = KVIKK_COD_TIERS.map(t => `
      <tr><td class="mono">${t.label}</td><td class="num">${t.mpl[0]} Ft + ${t.mpl[1]}%</td><td class="num">${t.foxpost[0]} Ft + ${t.foxpost[1]}%</td><td class="num">${t.dpd[0]} Ft + ${t.dpd[1]}%</td><td class="num">${t.gls[0]} Ft + ${t.gls[1]}%</td></tr>
    `).join('');
  }
  renderKvikkPriceTables();

  document.getElementById('kvikk-weight-tiers-tbody').addEventListener('change', (e) => {
    const input = e.target.closest('.kvikk-my-price-input');
    if (!input) return;
    const idx = parseInt(input.dataset.tierIndex, 10);
    KVIKK_WEIGHT_TIERS[idx].myPrice = parseInt(input.value, 10) || 0;
    renderKvikkPriceTables();
    pushNotification(`"Az én áram" frissítve ehhez a súlysávhoz: ${KVIKK_WEIGHT_TIERS[idx].label} — ${KVIKK_WEIGHT_TIERS[idx].myPrice.toLocaleString('hu-HU')} Ft`, 'info');
  });

  document.getElementById('kvikk-prices-save-btn').addEventListener('click', () => {
    flashSaveStatus(document.getElementById('kvikk-prices-save-status'));
    pushNotification('Packeta-ár mentve — a Statisztika fülön ez alapján számol majd a profit.', 'success');
    renderStatistics();
  });

  // ---- Statisztika: bevétel, profit, top termékek, időszakok, órás bontás ----
  const courierLabel = { foxpost: 'Foxpost', mpl: 'MPL', gls: 'GLS', dpd: 'DPD', packeta: 'Packeta' };
  const statIconRevenue = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10"/><path d="M11 20V4"/><path d="M18 20v-7"/></svg>';
  const statIconOrders = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>';
  const statIconBasket = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/><path d="M2 3h2l2.6 12.4a2 2 0 0 0 2 1.6h8a2 2 0 0 0 2-1.6L21 7H6"/></svg>';
  const statIconCoupon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6a2 2 0 0 0 0-4V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4Z"/></svg>';
  const statIconTruck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1.5" y="6" width="13" height="10"/><path d="M14.5 10h4.5l3 3.5V16h-7.5z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/></svg>';
  const statIconProfit = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M15 9.5c0-1.4-1.3-2.5-3-2.5s-3 1-3 2.5 1.3 2 3 2.5 3 1.1 3 2.5-1.3 2.5-3 2.5-3-1.1-3-2.5"/></svg>';

  function getPacketaPrice(){
    return parseInt(document.getElementById('kvikk-price-packeta').value, 10) || 0;
  }

  function getWeightShippingCost(courier, weightG){
    if (courier === 'packeta') return getPacketaPrice();
    const tier = KVIKK_WEIGHT_TIERS.find(t => weightG <= t.max) || KVIKK_WEIGHT_TIERS[KVIKK_WEIGHT_TIERS.length - 1];
    return tier[courier] || 0;
  }

  function getCodFee(courier, orderAmount){
    if (courier === 'packeta' || !KVIKK_COD_TIERS[0][courier]) return 0;
    const tier = KVIKK_COD_TIERS.find(t => orderAmount <= t.max) || KVIKK_COD_TIERS[KVIKK_COD_TIERS.length - 1];
    const [base, pct] = tier[courier];
    return Math.round(base + orderAmount * (pct / 100));
  }

  function parseOrderAmount(text){
    return parseInt((text || '').replace(/[^\d]/g, ''), 10) || 0;
  }

  const DEMO_TODAY = new Date(2026, 8, 21);

  function getStatsDateRange(){
    const activeBtn = document.querySelector('#stats-range-toggle .mode-btn.active');
    const mode = activeBtn ? activeBtn.dataset.range : 'all';
    if (mode === 'all') return null;
    if (mode === 'custom') {
      return {
        from: parseDateInputLocal(document.getElementById('stats-range-from').value),
        to: parseDateInputLocal(document.getElementById('stats-range-to').value)
      };
    }
    const daysMap = { '1d': 1, '7d': 7, '30d': 30, '90d': 90, '180d': 180, '365d': 365 };
    const days = daysMap[mode] || 30;
    const from = new Date(DEMO_TODAY);
    from.setDate(from.getDate() - days);
    return { from, to: DEMO_TODAY };
  }

  function getStatsOrders(range){
    return Array.from(document.querySelectorAll('#orders-tbody tr'))
      .filter(row => !isOrderCancelled(row) && dateInExportRange(parseShortDate(row.children[6].textContent), range))
      .map(row => {
        const orderNum = row.dataset.order;
        const detail = orderDetails[orderNum];
        const items = detail ? detail.items : [];
        const amount = parseOrderAmount(row.children[2].textContent);
        const courier = row.dataset.courier || '';
        const { total: weight } = detail ? computeOrderWeightBreakdown(items) : { total: 0 };
        const isCod = row.dataset.paymentMethod === 'cod';
        const shippingCost = getWeightShippingCost(courier, weight) + (isCod ? getCodFee(courier, amount) : 0);
        return {
          orderNum,
          customer: row.children[1].textContent.trim(),
          amount,
          date: row.children[6].textContent.trim(),
          time: row.dataset.time || '',
          courier,
          weight,
          shippingCost,
          items
        };
      });
  }

  function renderStatMainRow(orders){
    const revenue = orders.reduce((s, o) => s + o.amount, 0);
    const orderCount = orders.length;
    const avgBasket = orderCount ? Math.round(revenue / orderCount) : 0;
    document.getElementById('stats-main-row').innerHTML = `
      <div class="stat-card">
        <div><span class="stat-label">Bevétel</span><span class="stat-value">${revenue.toLocaleString('hu-HU')}<small>Ft</small></span></div>
        <div class="stat-icon">${statIconRevenue}</div>
      </div>
      <div class="stat-card">
        <div><span class="stat-label">Rendelések</span><span class="stat-value">${orderCount}</span></div>
        <div class="stat-icon">${statIconOrders}</div>
      </div>
      <div class="stat-card">
        <div><span class="stat-label">Átlagos kosár</span><span class="stat-value">${avgBasket.toLocaleString('hu-HU')}<small>Ft</small></span></div>
        <div class="stat-icon">${statIconBasket}</div>
      </div>
      <div class="stat-card">
        <div><span class="stat-label">Kupon-felhasználás</span><span class="stat-value">19<small>%</small></span></div>
        <div class="stat-icon">${statIconCoupon}</div>
      </div>
    `;
    return { revenue, orderCount, avgBasket };
  }

  function renderStatProfitRow(orders, revenue){
    const shippingCostTotal = orders.reduce((s, o) => s + o.shippingCost, 0);
    const profitWith = revenue - shippingCostTotal;
    document.getElementById('stats-profit-row').innerHTML = `
      <div class="stat-card">
        <div><span class="stat-label">Bruttó profit — szállítási költséggel</span><span class="stat-value">${profitWith.toLocaleString('hu-HU')}<small>Ft</small></span></div>
        <div class="stat-icon">${statIconProfit}</div>
      </div>
      <div class="stat-card">
        <div><span class="stat-label">Bruttó profit — szállítási költség nélkül</span><span class="stat-value">${revenue.toLocaleString('hu-HU')}<small>Ft</small></span></div>
        <div class="stat-icon">${statIconTruck}</div>
      </div>
    `;
    document.getElementById('stats-shipping-note').textContent =
      `Összes szállítási költség (a Kvikk-árlistából, rendelésenként a tényleges súly és — utánvétnél — a rendelésérték alapján): ${shippingCostTotal.toLocaleString('hu-HU')} Ft. Ennyivel csökkenti a profitot, ha a szállítást te álltad.`;
  }

  function renderTopProducts(orders){
    const map = {};
    orders.forEach(o => o.items.forEach(it => {
      if (!map[it.name]) map[it.name] = { qty: 0, revenue: 0 };
      map[it.name].qty += it.qty;
      map[it.name].revenue += it.qty * it.unit;
    }));
    const rows = Object.entries(map).sort((a, b) => b[1].revenue - a[1].revenue);
    document.getElementById('stats-top-products-tbody').innerHTML = rows.length
      ? rows.map(([name, d]) => `<tr><td>${name}</td><td class="num">${d.qty}</td><td class="num">${d.revenue.toLocaleString('hu-HU')} Ft</td></tr>`).join('')
      : '<tr><td colspan="3" class="empty-note">Nincs adat.</td></tr>';
  }

  function renderBarChart(containerId, items){
    const el = document.getElementById(containerId);
    const max = Math.max(1, ...items.map(i => i.value));
    el.innerHTML = items.map(i => `
      <div class="bar-chart-col">
        <span class="bar-chart-value">${i.value ? i.displayValue : ''}</span>
        <div class="bar-chart-bar${i.value ? ' has-value' : ''}" style="height:${Math.max(2, Math.round((i.value / max) * 100))}%"></div>
        <span class="bar-chart-label">${i.label}</span>
      </div>
    `).join('');
  }

  function renderDailyBreakdown(orders){
    const map = {};
    orders.forEach(o => {
      if (!map[o.date]) map[o.date] = { count: 0, revenue: 0 };
      map[o.date].count++;
      map[o.date].revenue += o.amount;
    });
    const rows = Object.entries(map).sort((a, b) => a[0].localeCompare(b[0]));
    document.getElementById('stats-daily-tbody').innerHTML = rows.length
      ? rows.map(([date, d]) => `<tr><td class="mono">${date}</td><td class="num">${d.count}</td><td class="num">${d.revenue.toLocaleString('hu-HU')} Ft</td></tr>`).join('')
      : '<tr><td colspan="3" class="empty-note">Nincs adat.</td></tr>';
    renderBarChart('stats-daily-chart', rows.map(([date, d]) => ({
      label: date, value: d.revenue, displayValue: `${(d.revenue / 1000).toLocaleString('hu-HU', { maximumFractionDigits: 1 })}e`
    })));
  }

  function renderHourlyBreakdown(orders){
    const rows = [];
    orders.slice().sort((a, b) => a.time.localeCompare(b.time)).forEach(o => {
      o.items.forEach(it => {
        rows.push({ time: o.time, orderNum: o.orderNum, name: it.name, qty: it.qty });
      });
    });
    document.getElementById('stats-hourly-tbody').innerHTML = rows.length
      ? rows.map(r => `<tr><td class="mono">${r.time}</td><td class="mono">#${r.orderNum}</td><td>${r.name}</td><td class="num">${r.qty}</td></tr>`).join('')
      : '<tr><td colspan="4" class="empty-note">Nincs adat.</td></tr>';

    const hourCounts = {};
    orders.forEach(o => {
      const hour = o.time.split(':')[0];
      if (!hour) return;
      hourCounts[hour] = (hourCounts[hour] || 0) + 1;
    });
    const chartItems = Array.from({ length: 24 }, (_, h) => {
      const hh = String(h).padStart(2, '0');
      const count = hourCounts[hh] || 0;
      return { label: h % 3 === 0 ? hh : '', value: count, displayValue: count };
    });
    renderBarChart('stats-hourly-chart', chartItems);

    const peak = Object.entries(hourCounts).sort((a, b) => b[1] - a[1])[0];
    document.getElementById('stats-peak-hour-note').textContent = peak
      ? `Ebben a mintában a ${peak[0]}:00 órai sáv a legforgalmasabb (${peak[1]} rendelés) — kevés adaton alapul, élesben ez sokkal megbízhatóbb lesz több rendeléssel.`
      : 'Nincs elég adat a csúcsidőszak megállapításához.';
  }

  function renderStatistics(){
    const range = getStatsDateRange();
    const orders = getStatsOrders(range);
    const { revenue } = renderStatMainRow(orders);
    renderStatProfitRow(orders, revenue);
    renderTopProducts(orders);
    renderDailyBreakdown(orders);
    renderHourlyBreakdown(orders);
  }

  document.getElementById('stats-range-toggle').addEventListener('click', (e) => {
    const btn = e.target.closest('.mode-btn');
    if (!btn) return;
    document.querySelectorAll('#stats-range-toggle .mode-btn').forEach(b => b.classList.toggle('active', b === btn));
    document.getElementById('stats-range-fields').style.display = btn.dataset.range === 'custom' ? 'grid' : 'none';
    renderStatistics();
  });
  ['stats-range-from', 'stats-range-to'].forEach(id => {
    document.getElementById(id).addEventListener('input', renderStatistics);
  });

  renderStatistics();

  function renderOrderWeightBreakdown(items){
    const { breakdown, total, hasMissing } = computeOrderWeightBreakdown(items);
    const rowsHtml = breakdown.map(b => `<div class="overview-row"><span class="overview-label">${b.label}</span><span class="overview-value">${b.sub}${b.weight !== null ? ` = ${b.weight} g` : ' — nincs megadva súly'}</span></div>`).join('');
    const totalHtml = `<div class="overview-row" style="border-top:2px solid var(--border); font-weight:700"><span class="overview-label">Összesen</span><span class="overview-value">${total} g${hasMissing ? ' <span style="font-weight:400; color:var(--warn-text)">(hiányos — nem minden tételnél van megadva súly)</span>' : ''}</span></div>`;
    document.getElementById('order-modal-weight-breakdown').innerHTML = `<div style="border:1px solid var(--border); border-radius:8px; padding:4px 14px; background:var(--bg)">${rowsHtml}${totalHtml}</div>`;
  }

  function openOrderModal(row){
    const orderNum = row.dataset.order;
    const detail = orderDetails[orderNum];
    if (!detail) return;

    const method = row.dataset.paymentMethod === 'cod' ? 'cod' : 'card';

    document.getElementById('order-modal-title').textContent = `Rendelés #${orderNum}`;
    document.getElementById('order-modal-meta').textContent = `${row.children[1].textContent.trim()} · ${row.children[6].textContent.trim()}`;
    document.getElementById('order-modal-method-pill').className = methodPillHtml[method].cls;
    document.getElementById('order-modal-method-pill').textContent = methodPillHtml[method].text;
    document.getElementById('order-modal-payment-ref-label').textContent = method === 'cod' ? 'Utánvét állapota' : 'Stripe tranzakció';

    const paymentSrc = row.children[3].querySelector('.pill');
    const shippingSrc = row.children[4].querySelector('.pill');
    document.getElementById('order-modal-payment-pill').className = paymentSrc.className;
    document.getElementById('order-modal-payment-pill').textContent = paymentSrc.textContent;
    document.getElementById('order-modal-shipping-pill').className = shippingSrc.className;
    document.getElementById('order-modal-shipping-pill').textContent = shippingSrc.textContent;

    document.getElementById('order-modal-items').innerHTML = detail.items.map(it => `
      <tr>
        <td>${it.name}</td>
        <td class="num">${it.qty}</td>
        <td class="num">${it.unit.toLocaleString('hu-HU')} Ft</td>
        <td class="num">${(it.qty * it.unit).toLocaleString('hu-HU')} Ft</td>
      </tr>
    `).join('');
    renderOrderWeightBreakdown(detail.items);
    document.getElementById('order-modal-address').textContent = detail.address;
    const isRealStripeTxn = method === 'card' && /^pi_/.test(detail.ref);
    const stripeRefEl = document.getElementById('order-modal-stripe');
    stripeRefEl.innerHTML = isRealStripeTxn
      ? `<a class="stripe-ref-link" href="${stripeDashboardUrl(detail.ref)}" target="_blank" rel="noopener">${detail.ref} →</a>`
      : detail.ref;

    const invoiceCheckbox = document.getElementById('order-modal-invoice');
    const invoiceFileInput = document.getElementById('order-modal-invoice-file');
    const invoiceFileNameEl = document.getElementById('order-modal-invoice-filename');
    invoiceCheckbox.checked = row.dataset.invoiced === 'true';
    currentInvoiceFileName = row.dataset.invoiceFile || '';
    currentInvoiceFileUrl = row.dataset.invoiceFileUrl || '';
    invoiceFileInput.value = '';
    invoiceFileNameEl.innerHTML = currentInvoiceFileName
      ? `Csatolva: ${currentInvoiceFileName}${currentInvoiceFileUrl ? ` · <a href="${currentInvoiceFileUrl}" target="_blank" rel="noopener">Megtekintés →</a>` : ''}`
      : '';
    document.getElementById('order-modal-invoice-save-status').classList.remove('show');

    invoiceFileInput.onchange = () => {
      const file = invoiceFileInput.files[0];
      if (file) {
        currentInvoiceFileName = file.name;
        if (currentInvoiceFileUrl) URL.revokeObjectURL(currentInvoiceFileUrl);
        currentInvoiceFileUrl = URL.createObjectURL(file);
        invoiceFileNameEl.innerHTML = `Csatolva: ${currentInvoiceFileName} · <a href="${currentInvoiceFileUrl}" target="_blank" rel="noopener">Megtekintés →</a>`;
      }
    };

    document.getElementById('order-modal-invoice-save-btn').onclick = () => {
      row.dataset.invoiced = invoiceCheckbox.checked ? 'true' : '';
      row.dataset.invoiceFile = currentInvoiceFileName;
      row.dataset.invoiceFileUrl = currentInvoiceFileUrl;
      refreshInvoiceTableCell(row);
      flashSaveStatus(document.getElementById('order-modal-invoice-save-status'));
      const fileNote = currentInvoiceFileName ? `, csatolva: ${currentInvoiceFileName}` : '';
      pushNotification(`Rendelés #${orderNum}: számla ${invoiceCheckbox.checked ? 'kiállítva' : 'jelölése törölve'}${fileNote}`, 'success');
    };

    const stripeSlot = document.getElementById('order-modal-stripe-refund-slot');
    if (isRealStripeTxn) {
      stripeSlot.innerHTML = `<a class="btn btn--ghost" href="${stripeDashboardUrl(detail.ref)}" target="_blank" rel="noopener">Visszatérítés a Stripe-nál →</a>`;
    } else if (method === 'cod') {
      stripeSlot.innerHTML = `<span class="empty-note" style="padding:0">Utánvétnél nincs Stripe-tranzakció — a visszautalást banki átutalással intézd.</span>`;
    } else {
      stripeSlot.innerHTML = `<span class="empty-note" style="padding:0">Nincs sikeres Stripe-tranzakció ehhez a rendeléshez.</span>`;
    }

    orderModal.classList.add('active');
  }
  function closeOrderModal(){ orderModal.classList.remove('active'); }

  document.getElementById('orders-tbody').addEventListener('click', (e) => {
    const row = e.target.closest('.row-clickable');
    if (row) openOrderModal(row);
  });
  document.getElementById('order-modal-close').addEventListener('click', closeOrderModal);
  document.getElementById('order-modal-close-footer').addEventListener('click', closeOrderModal);
  orderModal.addEventListener('click', (e) => { if (e.target === orderModal) closeOrderModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && orderModal.classList.contains('active')) closeOrderModal(); });

  // ---- jogi dokumentumok ----
  const legalDocModal = document.getElementById('legal-doc-modal');
  const legalDocForm = document.getElementById('legal-doc-form');
  const legalDocNameInput = document.getElementById('legal-doc-name');
  const legalDocStatusSelect = document.getElementById('legal-doc-status');
  const legalDocEffectiveInput = document.getElementById('legal-doc-effective');
  const legalDocUrlInput = document.getElementById('legal-doc-url');
  const legalDocUrlView = document.getElementById('legal-doc-url-view');
  const legalDocContentInput = document.getElementById('legal-doc-content');
  const legalDocFileInput = document.getElementById('legal-doc-file');
  const legalDocFileName = document.getElementById('legal-doc-file-name');
  const legalDocsTbody = document.getElementById('legal-docs-tbody');
  const legalPillLabel = { success: 'Publikálva', neutral: 'Piszkozat' };
  let editingLegalRow = null;

  function getLegalDocName(row){ return row.querySelector('.prod-name').textContent.trim(); }

  function legalDocRowHtml(name, statusKey, effectiveIso, url, archived){
    const effectiveHun = effectiveIso ? effectiveIso.replaceAll('-', '.') + '.' : '—';
    const today = new Date().toISOString().slice(0,10).replaceAll('-', '.') + '.';
    return `
      <td><div class="prod-name">${name}</div>${url ? `<a class="stripe-ref-link" style="font-size:11.5px" href="${url}" target="_blank" rel="noopener">Megtekintés az oldalon →</a>` : ''}</td>
      <td class="mono">${effectiveHun}</td>
      <td class="mono">${today}</td>
      <td><span class="pill pill--${statusKey}">${legalPillLabel[statusKey]}</span></td>
      <td>
        <div class="row-actions">
          <button type="button" class="row-edit-btn" aria-label="Szerkesztés">${editIconSvgProduct}</button>
          <button type="button" class="row-edit-btn row-archive-btn" aria-label="${archived ? 'Visszaállítás' : 'Archiválás'}">${archived ? restoreIconSvg : archiveIconSvg}</button>
        </div>
      </td>
    `;
  }

  function openLegalDocModal(row){
    editingLegalRow = row;
    const isNew = !row;
    document.getElementById('legal-doc-modal-title').textContent = isNew ? 'Új dokumentum' : `Dokumentum szerkesztése — ${getLegalDocName(row)}`;
    legalDocNameInput.value = isNew ? '' : getLegalDocName(row);
    legalDocNameInput.readOnly = !isNew;
    const pillEl = isNew ? null : row.querySelector('.pill');
    legalDocStatusSelect.value = isNew ? 'neutral' : (pillEl.classList.contains('pill--success') ? 'success' : 'neutral');
    legalDocEffectiveInput.value = isNew ? '' : (row.dataset.effective || '');
    legalDocUrlInput.value = isNew ? '' : (row.dataset.url || '');
    legalDocContentInput.value = isNew ? '' : (row.dataset.content || '');
    legalDocFileInput.value = '';
    legalDocFileName.textContent = '';
    updateLegalDocUrlView();
    legalDocModal.classList.add('active');
    legalDocNameInput.focus();
  }
  function closeLegalDocModal(){ legalDocModal.classList.remove('active'); editingLegalRow = null; }

  function updateLegalDocUrlView(){
    const url = legalDocUrlInput.value.trim();
    legalDocUrlView.innerHTML = url ? `<a href="${url}" target="_blank" rel="noopener">Megnyitás →</a>` : '';
  }
  legalDocUrlInput.addEventListener('input', updateLegalDocUrlView);

  document.getElementById('new-legal-doc-btn').addEventListener('click', () => openLegalDocModal(null));
  legalDocsTbody.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.row-edit-btn:not(.row-archive-btn)');
    if (editBtn) { openLegalDocModal(editBtn.closest('tr')); return; }
    const archiveBtn = e.target.closest('.row-archive-btn');
    if (archiveBtn) {
      const row = archiveBtn.closest('tr');
      const wasArchived = row.dataset.archived === 'true';
      row.dataset.archived = wasArchived ? 'false' : 'true';
      row.classList.toggle('row-archived', !wasArchived);
      archiveBtn.innerHTML = wasArchived ? archiveIconSvg : restoreIconSvg;
      archiveBtn.setAttribute('aria-label', wasArchived ? 'Archiválás' : 'Visszaállítás');
      applyLegalDocArchivedFilter();
      pushNotification(`<strong>${getLegalDocName(row)}</strong> ${wasArchived ? 'visszaállítva' : 'archiválva — a shopban is eltűnik, amint a backend ezt is figyelembe veszi'}`, 'info');
    }
  });
  document.getElementById('legal-doc-modal-close').addEventListener('click', closeLegalDocModal);
  document.getElementById('legal-doc-cancel').addEventListener('click', closeLegalDocModal);
  legalDocModal.addEventListener('click', (e) => { if (e.target === legalDocModal) closeLegalDocModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && legalDocModal.classList.contains('active')) closeLegalDocModal(); });
  legalDocFileInput.addEventListener('change', () => {
    const file = legalDocFileInput.files[0];
    legalDocFileName.textContent = file ? `Kiválasztva: ${file.name}` : '';
  });

  function applyLegalDocArchivedFilter(){
    const showArchived = document.getElementById('show-archived-docs-toggle').checked;
    legalDocsTbody.querySelectorAll('tr').forEach(row => {
      row.style.display = (row.dataset.archived === 'true' && !showArchived) ? 'none' : '';
    });
  }
  document.getElementById('show-archived-docs-toggle').addEventListener('change', applyLegalDocArchivedFilter);

  legalDocForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = legalDocNameInput.value.trim();
    const statusKey = legalDocStatusSelect.value;
    const effective = legalDocEffectiveInput.value;
    const url = legalDocUrlInput.value.trim();
    const content = legalDocContentInput.value;

    if (editingLegalRow){
      editingLegalRow.dataset.content = content;
      editingLegalRow.dataset.effective = effective;
      editingLegalRow.dataset.url = url;
      editingLegalRow.innerHTML = legalDocRowHtml(name, statusKey, effective, url, editingLegalRow.dataset.archived === 'true');
    } else {
      const row = document.createElement('tr');
      row.dataset.content = content;
      row.dataset.effective = effective;
      row.dataset.url = url;
      row.dataset.archived = 'false';
      row.innerHTML = legalDocRowHtml(name, statusKey, effective, url, false);
      legalDocsTbody.appendChild(row);
    }
    pushNotification(`Jogi dokumentum mentve: <strong>${name}</strong> (${legalPillLabel[statusKey]})${statusKey === 'success' ? ' — azonnal élesben' : ''}`, 'info');
    closeLegalDocModal();
  });

  // ---- GDPR-kérelmek ----
  document.getElementById('gdpr-requests-tbody').addEventListener('click', (e) => {
    const btn = e.target.closest('.gdpr-complete-btn');
    if (!btn || btn.disabled) return;
    const row = btn.closest('tr');
    const pillEl = row.querySelector('.pill');
    pillEl.className = 'pill pill--success';
    pillEl.textContent = 'Teljesítve';
    btn.disabled = true;
    const email = row.children[0].textContent.trim();
    pushNotification(`GDPR-kérelem teljesítve: <strong>${email}</strong> — "GDPR-kérelem teljesítve" email kiküldve`, 'success');
  });

  const gdprRequestModal = document.getElementById('gdpr-request-modal');
  const gdprRequestForm = document.getElementById('gdpr-request-form');
  function openGdprRequestModal(){
    gdprRequestForm.reset();
    document.getElementById('gdpr-request-date').value = new Date().toISOString().slice(0, 10);
    gdprRequestModal.classList.add('active');
    document.getElementById('gdpr-request-email').focus();
  }
  function closeGdprRequestModal(){ gdprRequestModal.classList.remove('active'); }
  document.getElementById('new-gdpr-request-btn').addEventListener('click', openGdprRequestModal);
  document.getElementById('gdpr-request-modal-close').addEventListener('click', closeGdprRequestModal);
  document.getElementById('gdpr-request-cancel').addEventListener('click', closeGdprRequestModal);
  gdprRequestModal.addEventListener('click', (e) => { if (e.target === gdprRequestModal) closeGdprRequestModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && gdprRequestModal.classList.contains('active')) closeGdprRequestModal(); });

  gdprRequestForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const email = document.getElementById('gdpr-request-email').value.trim();
    const type = document.getElementById('gdpr-request-type').value;
    const dateIso = document.getElementById('gdpr-request-date').value;
    const dateHun = dateIso ? dateIso.replaceAll('-', '.') + '.' : '—';
    const row = document.createElement('tr');
    row.innerHTML = `
      <td class="mono">${email}</td>
      <td>${type}</td>
      <td class="mono">${dateHun}</td>
      <td><span class="pill pill--warn">Függőben</span></td>
      <td><button type="button" class="btn btn--small gdpr-complete-btn">Teljesítve</button></td>
    `;
    document.getElementById('gdpr-requests-tbody').prepend(row);
    pushNotification(`Új GDPR-kérelem rögzítve: <strong>${email}</strong> (${type})`, 'warn');
    closeGdprRequestModal();
  });

  // ---- analitika: élő állapotjelzők ----
  ['analytics-ga', 'analytics-gtm', 'analytics-pixel', 'analytics-clarity'].forEach(id => {
    const input = document.getElementById(id);
    const pill = document.getElementById(input.dataset.pill);
    input.addEventListener('input', () => {
      if (input.value.trim()) {
        pill.className = 'pill pill--success analytics-pill';
        pill.textContent = 'Aktív';
      } else {
        pill.className = 'pill pill--warn analytics-pill';
        pill.textContent = 'Nincs beállítva';
      }
    });
  });

  // ---- értesítés dropdown + nagy nézet ----
  const notifBell = document.getElementById('notif-bell');
  const notifDropdown = document.getElementById('notif-dropdown');
  const notifModal = document.getElementById('notif-modal');
  const notifModalList = document.getElementById('notif-modal-list');
  const notificationLog = [
    { text: 'Új rendelés érkezett — #0231, Kovács Anna', level: 'success', time: '2 órája' },
    { text: 'Alacsony készlet: Alaptermék (7 db maradt)', level: 'warn', time: '5 órája' },
    { text: 'Új GDPR-kérelem: kovacs.anna@email.hu', level: 'warn', time: 'tegnap' }
  ];

  function pushNotification(text, level){
    level = level || 'info';
    notificationLog.unshift({ text, level, time: 'most' });
    const item = document.createElement('div');
    item.className = 'notif-item';
    item.innerHTML = `
      <span class="notif-dot pill--${level}"></span>
      <div><div class="notif-text">${text}</div><div class="notif-time">most</div></div>
    `;
    notifDropdown.querySelector('.notif-head').insertAdjacentElement('afterend', item);
    const items = notifDropdown.querySelectorAll('.notif-item');
    if (items.length > 8) items[items.length - 1].remove();
    notifBell.classList.add('has-unread');
    if (notifModal.classList.contains('active')) renderNotifModal();
  }

  function renderNotifModal(){
    notifModalList.innerHTML = notificationLog.length
      ? notificationLog.map(n => `
        <div class="notif-item">
          <span class="notif-dot pill--${n.level}"></span>
          <div><div class="notif-text">${n.text}</div><div class="notif-time">${n.time}</div></div>
        </div>
      `).join('')
      : '<p class="empty-note">Még nincs értesítés.</p>';
  }
  function openNotifModal(){
    renderNotifModal();
    notifModal.classList.add('active');
  }
  function closeNotifModal(){
    notifModal.classList.remove('active');
  }
  document.getElementById('notif-view-all-btn').addEventListener('click', () => {
    notifDropdown.classList.remove('active');
    openNotifModal();
  });
  document.getElementById('notif-modal-close').addEventListener('click', closeNotifModal);
  notifModal.addEventListener('click', (e) => { if (e.target === notifModal) closeNotifModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && notifModal.classList.contains('active')) closeNotifModal(); });

  notifBell.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDropdown.classList.toggle('active');
    if (notifDropdown.classList.contains('active')) notifBell.classList.remove('has-unread');
  });
  document.addEventListener('click', (e) => {
    if (notifDropdown.classList.contains('active') && !notifDropdown.contains(e.target)) {
      notifDropdown.classList.remove('active');
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') notifDropdown.classList.remove('active');
  });

  // ---- oldal-szintű beállítások mentése (Beállítások / Fizetés / Emailek / Analitika) ----
  function flashSaveStatus(el){
    el.textContent = 'Mentve ✓';
    el.classList.add('show');
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(() => el.classList.remove('show'), 2200);
  }

  document.getElementById('settings-save-btn').addEventListener('click', () => {
    const shopName = document.getElementById('settings-shop-name').value.trim();
    flashSaveStatus(document.getElementById('settings-save-status'));
    pushNotification(`Beállítások mentve: <strong>${shopName || 'Webshop'}</strong>`, 'success');
    document.getElementById('settings-new-password').value = '';
  });

  document.getElementById('stripe-save-btn').addEventListener('click', () => {
    saveStripeFieldsToStore(currentStripeMode);
    flashSaveStatus(document.getElementById('stripe-save-status'));
    pushNotification(`Stripe API-kulcsok mentve (${currentStripeMode === 'test' ? 'Teszt' : 'Éles'})`, 'success');
  });

  document.getElementById('email-settings-save-btn').addEventListener('click', () => {
    flashSaveStatus(document.getElementById('email-settings-save-status'));
    pushNotification('Email küldő beállítások mentve (automatikus — szerver PHP mail())', 'success');
  });

  document.getElementById('analytics-save-btn').addEventListener('click', () => {
    flashSaveStatus(document.getElementById('analytics-save-status'));
    const active = ['analytics-ga','analytics-gtm','analytics-pixel','analytics-clarity']
      .filter(id => document.getElementById(id).value.trim()).length;
    pushNotification(`Analitika beállítások mentve (${active} aktív integráció)`, 'success');
  });

  // ---- Szállítás: kézi / automata mód ----
  const shippingModeToggle = document.getElementById('shipping-mode-toggle');
  const shippingPanels = document.querySelectorAll('.shipping-panel');
  const missingWeightCountEl = document.getElementById('shipping-missing-weight-count');
  let shippingModeInitialized = false;

  function updateMissingWeightCount(){
    const missing = Array.from(inventoryTbody.querySelectorAll('tr'))
      .filter(row => row.dataset.type === 'regular' && !row.dataset.weight);
    if (!missing.length) {
      missingWeightCountEl.textContent = 'minden eladható terméknél meg van adva.';
    } else {
      const names = missing.map(row => getProdName(row)).join(', ');
      missingWeightCountEl.innerHTML = `<strong>${missing.length} terméknél még hiányzik</strong>: ${names}.`;
    }
  }

  function setShippingMode(mode){
    shippingModeToggle.querySelectorAll('.mode-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    shippingPanels.forEach(panel => panel.classList.remove('active'));
    document.getElementById(mode === 'auto' ? 'shipping-panel-auto' : 'shipping-panel-manual').classList.add('active');
    if (mode === 'auto') updateMissingWeightCount();
    if (shippingModeInitialized) {
      pushNotification(`Szállítás mód váltva: <strong>${mode === 'auto' ? 'Automata' : 'Kézi'}</strong>`, 'info');
    }
    shippingModeInitialized = true;
  }

  shippingModeToggle.addEventListener('click', (e) => {
    const btn = e.target.closest('.mode-btn');
    if (btn) setShippingMode(btn.dataset.mode);
  });

  // ---- Szállítás: egyeztetés (rendelések vs Kvikk csomagok) ----
  let reconcileCategories = {};
  const reconcileModal = document.getElementById('reconcile-modal');

  function openReconcileModal(key){
    const cat = reconcileCategories[key];
    if (!cat) return;
    document.querySelectorAll('.stat-card--clickable').forEach(card => {
      card.classList.toggle('active', card.dataset.category === key);
    });
    document.getElementById('reconcile-modal-title').textContent = `${cat.title} (${cat.rows.length})`;
    document.getElementById('reconcile-modal-subtitle').textContent = cat.label;
    document.getElementById('reconcile-modal-thead').innerHTML = '<th>Rendelés</th><th>Vevő</th><th class="num">Összeg</th><th>Dátum</th>';
    const tbody = document.getElementById('reconcile-modal-tbody');
    tbody.innerHTML = cat.rows.length
      ? cat.rows.map(row => `
        <tr class="row-clickable reconcile-modal-row" data-order="${row.dataset.order}">
          <td class="mono">#${row.dataset.order}</td>
          <td>${row.children[1].textContent.trim()}</td>
          <td class="num">${row.children[2].textContent.trim()}</td>
          <td class="mono">${row.children[6].textContent.trim()}</td>
        </tr>`).join('')
      : `<tr><td colspan="4" class="empty-note">${cat.emptyText}</td></tr>`;
    reconcileModal.classList.add('active');
  }
  function closeReconcileModal(){ reconcileModal.classList.remove('active'); }

  document.getElementById('reconcile-modal-tbody').addEventListener('click', (e) => {
    const row = e.target.closest('.reconcile-modal-row');
    if (!row) return;
    if (openOrderByNumber(row.dataset.order)) closeReconcileModal();
  });
  document.getElementById('reconcile-modal-close').addEventListener('click', closeReconcileModal);
  document.getElementById('reconcile-modal-close-footer').addEventListener('click', closeReconcileModal);
  reconcileModal.addEventListener('click', (e) => { if (e.target === reconcileModal) closeReconcileModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && reconcileModal.classList.contains('active')) closeReconcileModal(); });

  function renderShippingReconciliation(){
    const rows = Array.from(document.querySelectorAll('#orders-tbody tr'));
    const shipPillText = row => {
      const pill = row.children[4].querySelector('.pill');
      return pill ? pill.textContent.trim() : '';
    };
    const active = rows.filter(row => shipPillText(row) !== '—');
    const notYetPackaged = active.filter(row => shipPillText(row) === 'Csomagolás alatt');
    const alreadyAtKvikk = active.filter(row => ['Feladva', 'Kézbesítve'].includes(shipPillText(row)));
    const awaitingPayment = active.filter(row => !notYetPackaged.includes(row) && !alreadyAtKvikk.includes(row));

    reconcileCategories = {
      active: { rows: active, title: 'Aktív rendelések', label: 'Nem lemondott rendelések, minden állapot vegyesen.', emptyText: 'Nincs aktív rendelés.' },
      awaiting: { rows: awaitingPayment, title: 'Még nem fizetve', label: 'Ezek még nem szállíthatók, mert a vevő még nem fizetett.', emptyText: 'Nincs fizetésre váró rendelés.' },
      gap: { rows: notYetPackaged, title: 'Még nincs csomagolva', label: 'Kifizetve, de még nincs csomag létrehozva a Kvikknél — add fel őket, mielőtt elfelejtődnek.', emptyText: 'Minden aktív rendeléshez tartozik Kvikk-csomag, vagy még nincs is kifizetve — nincs teendő.' },
      atKvikk: { rows: alreadyAtKvikk, title: 'Már feladva / kézbesítve', label: 'Ezekhez már létrejött a csomag a Kvikknél.', emptyText: 'Egyetlen rendelés sincs még feladva a Kvikknél.' }
    };

    const gapColor = notYetPackaged.length ? 'var(--danger-text)' : 'var(--success-text)';
    document.getElementById('shipping-reconcile-stats').innerHTML = `
      <div class="stat-card stat-card--clickable" data-category="active">
        <div><span class="stat-label">Aktív rendelés (nem lemondott)</span><span class="stat-value">${active.length}</span></div>
      </div>
      <div class="stat-card stat-card--clickable" data-category="awaiting">
        <div><span class="stat-label">Még nem fizetve (nem szállítható még)</span><span class="stat-value">${awaitingPayment.length}</span></div>
      </div>
      <div class="stat-card stat-card--clickable" data-category="gap">
        <div><span class="stat-label">Még nincs csomagolva (teendő)</span><span class="stat-value" style="color:${gapColor}">${notYetPackaged.length}</span></div>
      </div>
      <div class="stat-card stat-card--clickable" data-category="atKvikk">
        <div><span class="stat-label">Már feladva / kézbesítve a Kvikknél</span><span class="stat-value">${alreadyAtKvikk.length}</span></div>
      </div>
    `;

    const noteEl = document.getElementById('shipping-reconcile-note');
    if (notYetPackaged.length) {
      noteEl.innerHTML = `
        <p class="empty-note" style="margin-top:14px; padding:12px 14px; border:1px solid var(--danger-text); background:var(--danger-bg); border-radius:8px; color:var(--danger-text); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <span><strong>${notYetPackaged.length} kifizetett rendeléshez</strong> még nincs csomag létrehozva a Kvikknél.</span>
          <button type="button" class="btn btn--ghost btn--small" data-category="gap">Megtekintés →</button>
        </p>
      `;
    } else {
      noteEl.innerHTML = `<p class="empty-note" style="margin-top:14px; padding:12px 14px; border:1px dashed var(--border); border-radius:8px; color:var(--success-text)">✓ Minden aktív rendeléshez tartozik Kvikk-csomag, vagy még nincs is kifizetve — nincs teendő.</p>`;
    }
  }
  document.getElementById('shipping-reconcile-stats').addEventListener('click', (e) => {
    const card = e.target.closest('.stat-card--clickable');
    if (card) openReconcileModal(card.dataset.category);
  });
  document.getElementById('shipping-reconcile-note').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-category]');
    if (btn) openReconcileModal(btn.dataset.category);
  });
  document.getElementById('shipping-reconcile-refresh').addEventListener('click', () => {
    renderShippingReconciliation();
    pushNotification('Egyeztetés frissítve a jelenlegi rendelésadatok alapján.', 'info');
  });

  setShippingMode('manual');
  renderShippingReconciliation();

  // ---- Visszaküldések: Kvikk visszaküldés ↔ Stripe visszatérítés egyeztetés ----
  let returnsReconcileCategories = {};

  function renderReturnsReconciliation(){
    const rows = Array.from(document.querySelectorAll('#returns-tbody tr'));
    const returned = rows.filter(r => r.dataset.kvikkStatus === 'returned');
    const inTransit = rows.filter(r => r.dataset.kvikkStatus === 'intransit');
    const refunded = returned.filter(r => r.dataset.stripeStatus === 'refunded');
    const gap = returned.filter(r => r.dataset.stripeStatus !== 'refunded');

    returnsReconcileCategories = {
      all: { rows, title: 'Összes visszaküldés', label: 'Minden Kvikk-visszaküldés, állapottól függetlenül.', emptyText: 'Nincs visszaküldés.' },
      inTransit: { rows: inTransit, title: 'Még úton van', label: 'A csomag még nem érkezett vissza — a visszatérítés még nem esedékes.', emptyText: 'Nincs úton lévő visszaküldés.' },
      gap: { rows: gap, title: 'Megérkezett, de nincs visszatérítve', label: 'A csomag megérkezett a Kvikknél, de a Stripe-visszatérítés még nem történt meg — indítsd el.', emptyText: 'Minden megérkezett visszaküldéshez tartozik Stripe-visszatérítés — nincs teendő.' },
      refunded: { rows: refunded, title: 'Visszatérítve', label: 'Ezekhez már megtörtént a Stripe-visszatérítés.', emptyText: 'Egyetlen visszaküldés sincs még visszatérítve.' }
    };

    const gapColor = gap.length ? 'var(--danger-text)' : 'var(--success-text)';
    document.getElementById('returns-reconcile-stats').innerHTML = `
      <div class="stat-card stat-card--clickable" data-return-category="all">
        <div><span class="stat-label">Összes visszaküldés</span><span class="stat-value">${rows.length}</span></div>
      </div>
      <div class="stat-card stat-card--clickable" data-return-category="inTransit">
        <div><span class="stat-label">Még úton van</span><span class="stat-value">${inTransit.length}</span></div>
      </div>
      <div class="stat-card stat-card--clickable" data-return-category="gap">
        <div><span class="stat-label">Megérkezett, nincs visszatérítve</span><span class="stat-value" style="color:${gapColor}">${gap.length}</span></div>
      </div>
      <div class="stat-card stat-card--clickable" data-return-category="refunded">
        <div><span class="stat-label">Visszatérítve</span><span class="stat-value">${refunded.length}</span></div>
      </div>
    `;

    const noteEl = document.getElementById('returns-reconcile-note');
    if (gap.length) {
      noteEl.innerHTML = `
        <p class="empty-note" style="margin-top:14px; padding:12px 14px; border:1px solid var(--danger-text); background:var(--danger-bg); border-radius:8px; color:var(--danger-text); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <span><strong>${gap.length} megérkezett visszaküldéshez</strong> még nincs elindítva a Stripe-visszatérítés.</span>
          <button type="button" class="btn btn--ghost btn--small" data-return-category="gap">Megtekintés →</button>
        </p>
      `;
    } else {
      noteEl.innerHTML = `<p class="empty-note" style="margin-top:14px; padding:12px 14px; border:1px dashed var(--border); border-radius:8px; color:var(--success-text)">✓ Minden megérkezett visszaküldéshez tartozik Stripe-visszatérítés — nincs teendő.</p>`;
    }
  }

  function openReturnsReconcileModal(key){
    const cat = returnsReconcileCategories[key];
    if (!cat) return;
    document.querySelectorAll('#returns-reconcile-stats .stat-card--clickable').forEach(card => {
      card.classList.toggle('active', card.dataset.returnCategory === key);
    });
    const kvikkLabel = { returned: 'Visszaküldve', intransit: 'Úton visszafelé' };
    const stripeLabel = { refunded: 'Visszatérítve', pending: 'Még nincs visszatérítve', na: '— még nem esedékes' };
    document.getElementById('reconcile-modal-title').textContent = `${cat.title} (${cat.rows.length})`;
    document.getElementById('reconcile-modal-subtitle').textContent = cat.label;
    document.getElementById('reconcile-modal-thead').innerHTML = '<th>Rendelés</th><th>Vevő</th><th class="num">Összeg</th><th>Kvikk állapot</th><th>Stripe visszatérítés</th>';
    const tbody = document.getElementById('reconcile-modal-tbody');
    tbody.innerHTML = cat.rows.length
      ? cat.rows.map(row => `
        <tr class="row-clickable reconcile-modal-row" data-order="${row.dataset.order}">
          <td class="mono order-link-btn">#${row.dataset.order}</td>
          <td>${row.children[2].textContent.trim()}</td>
          <td class="num">${row.children[3].textContent.trim()}</td>
          <td>${kvikkLabel[row.dataset.kvikkStatus] || row.dataset.kvikkStatus}</td>
          <td>${stripeLabel[row.dataset.stripeStatus] || row.dataset.stripeStatus}</td>
        </tr>`).join('')
      : `<tr><td colspan="5" class="empty-note">${cat.emptyText}</td></tr>`;
    reconcileModal.classList.add('active');
  }
  document.getElementById('returns-reconcile-stats').addEventListener('click', (e) => {
    const card = e.target.closest('.stat-card--clickable');
    if (card) openReturnsReconcileModal(card.dataset.returnCategory);
  });
  document.getElementById('returns-reconcile-note').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-return-category]');
    if (btn) openReturnsReconcileModal(btn.dataset.returnCategory);
  });
  document.getElementById('returns-reconcile-refresh').addEventListener('click', () => {
    renderReturnsReconciliation();
    pushNotification('Visszaküldés-egyeztetés frissítve.', 'info');
  });
  document.getElementById('returns-tbody').addEventListener('click', (e) => {
    if (e.target.closest('.stripe-ref-link')) return;
    const row = e.target.closest('.row-clickable');
    if (!row) return;
    openOrderByNumber(row.dataset.order);
  });

  renderReturnsReconciliation();

  // ---- lépésenkénti (wizard) útmutató: Stripe CLI ----
  function initWizard(modalId, dotsId, openBtnId){
    const modal = document.getElementById(modalId);
    const dotsEl = document.getElementById(dotsId);
    const panels = Array.from(modal.querySelectorAll('.wizard-panel'));
    const backBtn = modal.querySelector('.modal-actions [id$="-back-btn"]');
    const nextBtn = modal.querySelector('.modal-actions [id$="-next-btn"]');
    const total = panels.length;
    let current = 1;

    dotsEl.innerHTML = panels.map(() => '<div class="wizard-dot"></div>').join('');
    const dots = Array.from(dotsEl.children);

    function render(){
      panels.forEach(p => p.classList.toggle('active', Number(p.dataset.step) === current));
      dots.forEach((d, i) => {
        d.classList.toggle('active', i + 1 === current);
        d.classList.toggle('done', i + 1 < current);
      });
      backBtn.disabled = current === 1;
      backBtn.style.visibility = current === 1 ? 'hidden' : 'visible';
      nextBtn.textContent = current === total ? 'Kész' : 'Tovább';
    }

    function open(){
      current = 1;
      render();
      modal.classList.add('active');
    }
    function close(){ modal.classList.remove('active'); }

    document.getElementById(openBtnId).addEventListener('click', open);
    document.getElementById(`${modalId}-close`).addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('active')) close(); });

    backBtn.addEventListener('click', () => { if (current > 1) { current--; render(); } });
    nextBtn.addEventListener('click', () => {
      if (current < total) { current++; render(); } else { close(); }
    });

    modal.querySelectorAll('.wizard-copy-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const text = btn.dataset.copy;
        const original = btn.textContent;
        const finish = (ok) => {
          btn.textContent = ok ? 'Másolva ✓' : 'Nem sikerült';
          setTimeout(() => { btn.textContent = original; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(() => finish(true)).catch(() => finish(false));
        } else {
          finish(false);
        }
      });
    });
  }

  initWizard('stripe-cli-modal', 'stripe-cli-dots', 'open-stripe-cli-wizard-btn');

  // ---- globális kereső (rendelés, vevő, termék) ----
  (function(){
    const searchInput = document.getElementById('global-search-input');
    const searchDropdown = document.getElementById('search-dropdown');
    let resultHandlers = {};

    function escapeHtml(str){
      return String(str).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }
    function highlight(text, q){
      const escaped = escapeHtml(text);
      if (!q) return escaped;
      const idx = escaped.toLowerCase().indexOf(q.toLowerCase());
      if (idx === -1) return escaped;
      return escaped.slice(0, idx) + '<mark>' + escaped.slice(idx, idx + q.length) + '</mark>' + escaped.slice(idx + q.length);
    }
    function closeSearchDropdown(){
      searchDropdown.classList.remove('active');
    }

    function runSearch(raw){
      const q = raw.trim().toLowerCase();
      if (!q) { closeSearchDropdown(); searchDropdown.innerHTML = ''; return; }
      const qNoHash = q.replace(/^#/, '');

      const orderItems = [];
      document.querySelectorAll('#orders-tbody tr').forEach(row => {
        const orderNum = row.dataset.order;
        const customer = row.children[1].textContent.trim();
        const amount = row.children[2].textContent.trim();
        const date = row.children[6].textContent.trim();
        const detail = orderDetails[orderNum];
        const productNames = detail ? detail.items.map(it => it.name).join(', ') : '';
        const matches = orderNum.toLowerCase().includes(qNoHash)
          || customer.toLowerCase().includes(q)
          || amount.toLowerCase().includes(q)
          || date.toLowerCase().includes(q)
          || productNames.toLowerCase().includes(q);
        if (!matches) return;
        orderItems.push({
          title: highlight('#' + orderNum, qNoHash),
          sub: highlight(`${customer} · ${amount} · ${date}${productNames ? ' · ' + productNames : ''}`, q),
          onClick: () => openOrderByNumber(orderNum)
        });
      });

      const customerItems = [];
      document.querySelectorAll('#customers-tbody tr').forEach(row => {
        const name = row.children[0].textContent.trim();
        const email = row.children[1].textContent.trim();
        const phone = row.children[2].textContent.trim();
        const key = row.dataset.customer;
        const matches = name.toLowerCase().includes(q) || email.toLowerCase().includes(q) || phone.toLowerCase().includes(q);
        if (!matches) return;
        customerItems.push({
          title: highlight(name, q),
          sub: highlight(`${email} · ${phone}`, q),
          onClick: () => { switchToView('customers'); openCustomerModal(key); }
        });
      });

      const productItems = [];
      document.querySelectorAll('#inventory-tbody tr').forEach(row => {
        const name = getProdName(row);
        if (!name.toLowerCase().includes(q)) return;
        const price = row.querySelector('.stock-price');
        productItems.push({
          title: highlight(name, q),
          sub: (price ? price.textContent.trim() : '') + (row.classList.contains('row-archived') ? ' · archivált' : ''),
          onClick: () => { switchToView('products'); openForEditProduct(row); }
        });
      });

      const groups = [
        { key: 'orders', label: 'Rendelések', items: orderItems.slice(0, 8) },
        { key: 'customers', label: 'Vásárlók', items: customerItems.slice(0, 8) },
        { key: 'products', label: 'Termékek', items: productItems.slice(0, 8) }
      ];
      const total = groups.reduce((sum, g) => sum + g.items.length, 0);

      resultHandlers = {};
      if (total === 0) {
        searchDropdown.innerHTML = `<div class="search-empty">Nincs találat erre: "${escapeHtml(raw.trim())}"</div>`;
        searchDropdown.classList.add('active');
        return;
      }
      searchDropdown.innerHTML = groups.filter(g => g.items.length).map(g => `
        <div class="search-group-label">${g.label}</div>
        ${g.items.map((item, i) => {
          const handlerKey = g.key + '-' + i;
          resultHandlers[handlerKey] = item.onClick;
          return `<div class="search-result-item" tabindex="0" data-key="${handlerKey}">
            <div class="search-result-title">${item.title}</div>
            <div class="search-result-sub">${item.sub}</div>
          </div>`;
        }).join('')}
      `).join('');
      searchDropdown.classList.add('active');
    }

    searchInput.addEventListener('input', () => runSearch(searchInput.value));
    searchInput.addEventListener('focus', () => { if (searchInput.value.trim()) runSearch(searchInput.value); });
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeSearchDropdown(); searchInput.blur(); }
      if (e.key === 'Enter') {
        const first = searchDropdown.querySelector('.search-result-item');
        if (first) { resultHandlers[first.dataset.key](); closeSearchDropdown(); searchInput.blur(); }
      }
    });
    searchDropdown.addEventListener('click', (e) => {
      const item = e.target.closest('.search-result-item');
      if (!item) return;
      const handler = resultHandlers[item.dataset.key];
      if (handler) handler();
      closeSearchDropdown();
    });
    searchDropdown.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      const item = e.target.closest('.search-result-item');
      if (!item) return;
      const handler = resultHandlers[item.dataset.key];
      if (handler) handler();
      closeSearchDropdown();
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.search')) closeSearchDropdown();
    });
  })();
