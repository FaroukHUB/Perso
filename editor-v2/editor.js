/**
 * PERSONNALY - Editor V2 JavaScript
 *
 * ARCHITECTURE :
 * - Toutes les données viennent de l'API /public/api/editor/product.php
 * - Aucun mock, aucune donnée hardcodée
 * - Le prix est calculé côté backend (frontend = estimation visuelle uniquement)
 * - Modals dédiés plein écran (pas de dropdowns/bottom sheets imbriqués)
 */

// ============================================
// CONFIGURATION
// ============================================
const CONFIG = window.__EDITOR_CONFIG || { productId: 1, apiBase: '/public/api' };

// ============================================
// STATE CENTRAL
// ============================================
const state = {
  loaded: false,
  productId: CONFIG.productId,

  // Données chargées depuis l'API
  product: null,
  colors: [],
  printZones: [],
  techniques: [],
  fonts: [],
  textColors: [], // Couleurs de texte depuis admin

  // Assets (designs & éléments depuis admin)
  designs: [],
  elements: [],

  // État courant
  currentColorId: null,
  currentView: 'front',
  currentTechnique: null,

  // Layers
  layers: [],
  activeLayerId: null,

  // Prix (estimation frontend)
  price: {
    base: 0,
    technique: 0,
    total: 0
  },

  // Paramètres texte
  textSettings: {
    text: '',
    fontFamily: 'Inter',
    fontId: 0,
    fontSize: 24,
    color: '#000000',
    align: 'left'
  }
};

// ============================================
// DOM ELEMENTS
// ============================================
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const els = {
  editor: $('#editor'),
  loadingOverlay: $('#loadingOverlay'),
  productTitle: $('#productTitle'),
  productImage: $('#productImage'),
  printArea: $('#printArea'),
  printAreaDebug: $('#printAreaDebug'),

  // Tabs
  tabs: null,
  panels: null,

  // Text controls (panel principal - non utilisé en mobile)
  textInput: $('#textInput'),
  fontSelector: $('#fontSelector'),
  fontSize: $('#fontSize'),
  textColor: $('#textColor'),
  alignBtns: null,
  btnAddText: $('#btnAddText'),

  // Technique (panel principal)
  techniqueSelector: $('#techniqueSelector'),

  // Grids
  designsGrid: $('#designsGrid'),
  elementsGrid: $('#elementsGrid'),

  // Layers
  layersList: $('#layersList'),
  layersEmpty: $('#layersEmpty'),

  // Price
  priceBase: $('#priceBase'),
  priceTechnique: $('#priceTechnique'),
  priceTotal: $('#priceTotal'),
  ctaPrice: $('#ctaPrice'),

  // Actions
  btnPreview: $('#btnPreview'),
  btnClosePreview: $('#btnClosePreview'),
  btnAddToCart: $('#btnAddToCart')
};

// ============================================
// UTILS
// ============================================
function generateId() {
  return 'layer_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
}

function formatPrice(price) {
  return price.toFixed(2).replace('.', ',') + ' €';
}

function showLoading() {
  if (els.loadingOverlay) els.loadingOverlay.style.display = 'flex';
}

function hideLoading() {
  if (els.loadingOverlay) els.loadingOverlay.style.display = 'none';
}

function isDesktop() {
  return window.innerWidth >= 1024;
}

// ============================================
// API
// ============================================
async function loadAssetsData() {
  try {
    const response = await fetch(`${CONFIG.apiBase}/editor/assets.php`);
    const data = await response.json();

    if (!data.success) {
      console.warn('[Editor] API assets erreur:', data.error);
      return false;
    }

    state.designs = data.designs || [];
    state.elements = data.elements || [];

    if (state.designs.length === 0 && state.elements.length === 0) {
      console.info('[Editor] Aucun design/élément trouvé dans l\'admin.');
    } else {
      console.info(`[Editor] Assets chargés: ${state.designs.length} catégorie(s) designs, ${state.elements.length} catégorie(s) éléments`);
    }

    return true;

  } catch (error) {
    console.error('[Editor] Erreur réseau chargement assets:', error.message);
    return false;
  }
}

async function loadTextColorsData() {
  try {
    const response = await fetch(`${CONFIG.apiBase}/editor/colors.php`);
    const data = await response.json();

    if (!data.success) {
      console.warn('[Editor] API colors erreur:', data.error);
      return false;
    }

    state.textColors = data.colors || [];
    console.info(`[Editor] Couleurs texte chargées: ${state.textColors.length}`);

    // Définir la couleur par défaut (première couleur ou noir)
    if (state.textColors.length > 0) {
      state.textSettings.color = state.textColors[0].hex;
    }

    return true;

  } catch (error) {
    console.error('[Editor] Erreur réseau chargement couleurs:', error.message);
    return false;
  }
}

async function loadProductData() {
  showLoading();

  try {
    const response = await fetch(`${CONFIG.apiBase}/editor/product.php?id=${state.productId}`);
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || 'Erreur de chargement');
    }

    state.product = data.product;
    state.colors = data.colors || [];
    state.printZones = data.print_zones || [];
    state.techniques = data.techniques || [];
    state.fonts = data.fonts || [];
    state.loaded = true;

    const defaultColor = state.colors.find(c => c.is_default) || state.colors[0];
    if (defaultColor) {
      state.currentColorId = defaultColor.id;
    }

    const defaultTechnique = state.techniques[0];
    if (defaultTechnique) {
      state.currentTechnique = defaultTechnique.value;
    }

    state.price.base = state.product.base_price;

    return true;

  } catch (error) {
    console.error('Erreur chargement produit:', error);
    alert('Impossible de charger le produit. Veuillez réessayer.');
    return false;

  } finally {
    hideLoading();
  }
}

// ============================================
// RENDER UI FROM API DATA
// ============================================
function renderProductInfo() {
  if (!state.product) return;

  if (els.productTitle) {
    els.productTitle.textContent = state.product.name;
  }

  const defaultColor = state.colors.find(c => c.id === state.currentColorId) || state.colors[0];
  if (defaultColor && defaultColor.images) {
    const imageUrl = state.currentView === 'front'
      ? defaultColor.images.front
      : defaultColor.images.back;

    if (imageUrl && els.productImage) {
      els.productImage.src = imageUrl;
    }
  }
}

function loadFontCSS() {
  // Charger les CSS des polices depuis l'API
  state.fonts.forEach(font => {
    if (font.css_url && !document.querySelector(`link[href="${font.css_url}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = font.css_url;
      link.crossOrigin = 'anonymous';
      document.head.appendChild(link);

      // Log pour debug
      console.info(`[Editor] Police chargée: ${font.label} (${font.family})`);
    }
  });

  // Définir la police par défaut
  if (state.fonts.length > 0) {
    state.textSettings.fontFamily = state.fonts[0].family;
    state.textSettings.fontId = state.fonts[0].id;
  }
}

function renderPrintZone() {
  if (!els.printAreaDebug) return;

  const zone = state.printZones.find(z => z.view === state.currentView) || state.printZones[0];

  if (zone) {
    els.printAreaDebug.style.left = zone.x + '%';
    els.printAreaDebug.style.top = zone.y + '%';
    els.printAreaDebug.style.width = zone.width + '%';
    els.printAreaDebug.style.height = zone.height + '%';
    els.printAreaDebug.style.right = 'auto';
    els.printAreaDebug.style.bottom = 'auto';

    if (els.printArea) {
      els.printArea.style.left = zone.x + '%';
      els.printArea.style.top = zone.y + '%';
      els.printArea.style.width = zone.width + '%';
      els.printArea.style.height = zone.height + '%';
      els.printArea.style.right = 'auto';
      els.printArea.style.bottom = 'auto';
    }
  }
}

// ============================================
// TABS
// ============================================
function initTabs() {
  els.tabs = $$('.ps-tab');
  els.panels = $$('.ps-panel');

  // Au chargement : aucun panneau affiché, aucun onglet actif (mobile)
  // Sur desktop : afficher le premier onglet par défaut
  els.tabs.forEach(t => t.classList.remove('active'));
  els.panels.forEach(p => p.classList.remove('active'));

  if (isDesktop()) {
    // Desktop : initialiser le contenu des panels et afficher Text par défaut
    initDesktopPanels();
    activateTab('text');
  }

  els.tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const tabId = tab.dataset.tab;

      // DESKTOP : afficher les panels inline
      if (isDesktop()) {
        activateTab(tabId);
        return;
      }

      // MOBILE : ouvrir les modals
      if (tabId === 'text') {
        openTextModal();
        return;
      }

      if (tabId === 'designs') {
        openDesignModal();
        return;
      }

      if (tabId === 'elements') {
        openElementModal();
        return;
      }

      // Calques → panneau inline (mobile aussi)
      activateTab(tabId);
    });
  });
}

function activateTab(tabId) {
  els.tabs.forEach(t => t.classList.remove('active'));
  els.panels.forEach(p => p.classList.remove('active'));

  const tab = $(`.ps-tab[data-tab="${tabId}"]`);
  const panel = $(`#panel-${tabId}`);

  if (tab) tab.classList.add('active');
  if (panel) panel.classList.add('active');
}

function initDesktopPanels() {
  // Remplir les panels designs et éléments pour desktop
  renderDesktopDesignsPanel();
  renderDesktopElementsPanel();
  // Initialiser les sélecteurs inline (polices, techniques, contrôles texte)
  initDesktopSelectors();
}

function renderDesktopDesignsPanel() {
  const grid = $('#designsGrid');
  if (!grid) return;

  grid.innerHTML = '';

  if (state.designs.length === 0) {
    grid.innerHTML = '<div class="ps-grid-empty">Aucun design disponible</div>';
    return;
  }

  state.designs.forEach(category => {
    const catTitle = document.createElement('div');
    catTitle.className = 'ps-grid-category-title';
    catTitle.textContent = category.category_name;
    grid.appendChild(catTitle);

    category.items.forEach(design => {
      const item = document.createElement('div');
      item.className = 'ps-grid-item';
      item.title = design.name;

      if (design.image) {
        item.innerHTML = `<img src="${design.image}" alt="${design.name}" loading="lazy">`;
      } else {
        item.innerHTML = `<span class="ps-grid-item-name">${design.name}</span>`;
      }

      item.addEventListener('click', () => addDesignLayer(design));
      grid.appendChild(item);
    });
  });
}

function renderDesktopElementsPanel() {
  const grid = $('#elementsGrid');
  if (!grid) return;

  grid.innerHTML = '';

  if (state.elements.length === 0) {
    grid.innerHTML = '<div class="ps-grid-empty">Aucun élément disponible</div>';
    return;
  }

  state.elements.forEach(category => {
    const catTitle = document.createElement('div');
    catTitle.className = 'ps-grid-category-title';
    catTitle.textContent = category.category_name;
    grid.appendChild(catTitle);

    category.items.forEach(element => {
      const item = document.createElement('div');
      item.className = 'ps-grid-item';
      item.title = element.name;

      let premiumBadge = '';
      if (element.is_premium) {
        premiumBadge = '<span class="ps-grid-badge-pro">PRO</span>';
        item.style.position = 'relative';
      }

      if (element.image) {
        item.innerHTML = `${premiumBadge}<img src="${element.image}" alt="${element.name}" loading="lazy">`;
      } else {
        item.innerHTML = `${premiumBadge}<span class="ps-grid-item-name">${element.name}</span>`;
      }

      item.addEventListener('click', () => addElementLayer(element));
      grid.appendChild(item);
    });
  });
}

// ============================================
// DESKTOP: Sélecteurs inline (Police / Technique)
// ============================================
function initDesktopSelectors() {
  initDesktopFontSelector();
  initDesktopTechniqueSelector();
  initDesktopTextControls();
}

function initDesktopFontSelector() {
  const selector = els.fontSelector;
  if (!selector) return;

  const trigger = selector.querySelector('.ps-custom-select-trigger');
  const textSpan = selector.querySelector('.ps-custom-select-text');

  // Créer le dropdown
  let dropdown = selector.querySelector('.ps-custom-select-dropdown');
  if (!dropdown) {
    dropdown = document.createElement('div');
    dropdown.className = 'ps-custom-select-dropdown';
    selector.appendChild(dropdown);
  }

  // Remplir avec les polices de l'API
  dropdown.innerHTML = '';

  if (state.fonts.length === 0) {
    dropdown.innerHTML = '<div class="ps-select-empty">Aucune police disponible</div>';
  } else {
    state.fonts.forEach(font => {
      const option = document.createElement('div');
      option.className = 'ps-select-option';
      option.dataset.value = font.family;
      option.innerHTML = `<span style="font-family: '${font.family}', sans-serif;">${font.label}</span>`;

      option.addEventListener('click', (e) => {
        e.stopPropagation();
        state.textSettings.fontFamily = font.family;
        state.textSettings.fontId = font.id;
        textSpan.textContent = font.label;
        textSpan.style.fontFamily = `'${font.family}', sans-serif`;
        selector.classList.remove('open');
        updateFontOptions(font.family);

        // Appliquer au calque texte actif si existant
        applyFontToActiveLayer(font.family, font.id);
      });

      dropdown.appendChild(option);
    });
  }

  // Toggle dropdown
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    closeAllSelectors();
    selector.classList.toggle('open');
  });

  // Initialiser avec la première police
  if (state.fonts.length > 0) {
    const defaultFont = state.fonts[0];
    textSpan.textContent = defaultFont.label;
    textSpan.style.fontFamily = `'${defaultFont.family}', sans-serif`;
    updateFontOptions(defaultFont.family);
  }
}

function updateFontOptions(selectedFamily) {
  const dropdown = els.fontSelector?.querySelector('.ps-custom-select-dropdown');
  if (!dropdown) return;

  dropdown.querySelectorAll('.ps-select-option').forEach(opt => {
    opt.classList.toggle('selected', opt.dataset.value === selectedFamily);
  });
}

function initDesktopTechniqueSelector() {
  const selector = els.techniqueSelector;
  if (!selector) return;

  const trigger = selector.querySelector('.ps-custom-select-trigger');
  const textSpan = selector.querySelector('.ps-custom-select-text');

  // Créer le dropdown
  let dropdown = selector.querySelector('.ps-custom-select-dropdown');
  if (!dropdown) {
    dropdown = document.createElement('div');
    dropdown.className = 'ps-custom-select-dropdown';
    selector.appendChild(dropdown);
  }

  // Remplir avec les techniques de l'API
  dropdown.innerHTML = '';

  if (state.techniques.length === 0) {
    dropdown.innerHTML = '<div class="ps-select-empty">Aucune technique disponible</div>';
  } else {
    state.techniques.forEach(tech => {
      const option = document.createElement('div');
      option.className = 'ps-select-option';
      option.dataset.value = tech.value;

      const priceLabel = tech.price > 0 ? `<span class="ps-select-price">+${formatPrice(tech.price)}</span>` : '<span class="ps-select-price included">Inclus</span>';
      option.innerHTML = `
        <div class="ps-select-option-content">
          <span class="ps-select-option-label">${tech.label}</span>
          ${tech.description ? `<span class="ps-select-option-desc">${tech.description}</span>` : ''}
        </div>
        ${priceLabel}
      `;

      option.addEventListener('click', (e) => {
        e.stopPropagation();
        state.currentTechnique = tech.value;
        const displayPrice = tech.price > 0 ? ` (+${formatPrice(tech.price)})` : '';
        textSpan.textContent = tech.label + displayPrice;
        selector.classList.remove('open');
        updateTechniqueOptions(tech.value);
        updatePrice();
      });

      dropdown.appendChild(option);
    });
  }

  // Toggle dropdown
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    closeAllSelectors();
    selector.classList.toggle('open');
  });

  // Initialiser avec la première technique
  if (state.techniques.length > 0) {
    const defaultTech = state.techniques[0];
    const displayPrice = defaultTech.price > 0 ? ` (+${formatPrice(defaultTech.price)})` : '';
    textSpan.textContent = defaultTech.label + displayPrice;
    updateTechniqueOptions(defaultTech.value);
  }
}

function updateTechniqueOptions(selectedValue) {
  const dropdown = els.techniqueSelector?.querySelector('.ps-custom-select-dropdown');
  if (!dropdown) return;

  dropdown.querySelectorAll('.ps-select-option').forEach(opt => {
    opt.classList.toggle('selected', opt.dataset.value === selectedValue);
  });
}

function initDesktopTextControls() {
  // Input texte
  if (els.textInput) {
    els.textInput.addEventListener('input', (e) => {
      state.textSettings.text = e.target.value;
    });
  }

  // Taille police
  if (els.fontSize) {
    els.fontSize.addEventListener('input', (e) => {
      state.textSettings.fontSize = parseInt(e.target.value);
    });
  }

  // Couleur texte - Swatches
  renderColorSwatches();

  // Alignement
  els.alignBtns = $$('#panel-text .ps-align-btn');
  els.alignBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      els.alignBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.textSettings.align = btn.dataset.align;
    });
  });

  // Bouton ajouter texte
  if (els.btnAddText) {
    els.btnAddText.addEventListener('click', (e) => {
      e.preventDefault();
      addTextLayer();
    });
  }

  // === Contrôles du texte actif (desktop) ===
  const controls = $('#textLayerControls');
  if (controls) {
    // Supprimer
    controls.querySelector('[data-action="delete"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      deleteActiveTextLayer();
    });

    // Gras
    controls.querySelector('[data-action="bold"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      toggleBold();
    });

    // Italique
    controls.querySelector('[data-action="italic"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      toggleItalic();
    });

    // Dimensions
    controls.querySelector('[data-action="decrease"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      decreaseSize();
    });

    controls.querySelector('[data-action="increase"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      increaseSize();
    });

    // Rotation
    const rotationSlider = controls.querySelector('#rotationSlider');
    const rotationValue = controls.querySelector('#rotationValue');
    if (rotationSlider) {
      rotationSlider.addEventListener('input', (e) => {
        setRotation(e.target.value);
        if (rotationValue) rotationValue.textContent = e.target.value + '°';
      });
    }

    // Déplacer
    controls.querySelectorAll('.ps-move-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        nudgeLayer(btn.dataset.direction);
      });
    });
  }
}

function renderColorSwatches() {
  const container = $('#colorSwatches');
  if (!container) return;

  container.innerHTML = '';

  if (state.textColors.length === 0) {
    container.innerHTML = '<span class="ps-color-swatches-empty">Aucune couleur</span>';
    return;
  }

  state.textColors.forEach(color => {
    const swatch = document.createElement('button');
    swatch.type = 'button';
    swatch.className = 'ps-color-swatch' + (color.hex === state.textSettings.color ? ' active' : '');
    swatch.style.backgroundColor = color.hex;
    swatch.title = color.label;
    swatch.dataset.color = color.hex;

    // Border pour les couleurs claires
    if (isLightColor(color.hex)) {
      swatch.classList.add('light');
    }

    swatch.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      selectTextColor(color.hex);
    });

    container.appendChild(swatch);
  });
}

function isLightColor(hex) {
  // Convertir hex en RGB et calculer luminosité
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.8;
}

function renderModalColorSwatches(modal) {
  const container = modal.querySelector('#modalColorSwatches');
  if (!container) return;

  container.innerHTML = '';

  if (state.textColors.length === 0) {
    container.innerHTML = '<span class="ps-color-swatches-empty">Aucune couleur</span>';
    return;
  }

  state.textColors.forEach(color => {
    const swatch = document.createElement('button');
    swatch.type = 'button';
    swatch.className = 'ps-color-swatch' + (color.hex === state.textSettings.color ? ' active' : '');
    swatch.style.backgroundColor = color.hex;
    swatch.title = color.label;
    swatch.dataset.color = color.hex;

    if (isLightColor(color.hex)) {
      swatch.classList.add('light');
    }

    swatch.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      selectTextColor(color.hex);
    });

    container.appendChild(swatch);
  });
}

function applyFontToActiveLayer(fontFamily, fontId) {
  const layer = getActiveTextLayer();
  if (!layer) return;

  layer.fontFamily = fontFamily;
  layer.fontId = fontId;

  const div = $(`.ps-layer[data-layer-id="${layer.id}"]`);
  if (div) {
    div.style.fontFamily = fontFamily;
  }
}

function selectTextColor(hex) {
  state.textSettings.color = hex;

  // Mettre à jour les swatches (desktop)
  $$('#colorSwatches .ps-color-swatch').forEach(sw => {
    sw.classList.toggle('active', sw.dataset.color === hex);
  });

  // Mettre à jour les swatches (mobile modal)
  $$('#modalColorSwatches .ps-color-swatch').forEach(sw => {
    sw.classList.toggle('active', sw.dataset.color === hex);
  });

  // Appliquer au calque texte actif si existant
  const layer = getActiveTextLayer();
  if (layer) {
    layer.color = hex;
    const div = $(`.ps-layer[data-layer-id="${layer.id}"]`);
    if (div) {
      div.style.color = hex;
    }
  }
}

function closeAllSelectors() {
  $$('.ps-custom-select.open').forEach(sel => sel.classList.remove('open'));
}

// Fermer les selectors au clic extérieur
document.addEventListener('click', () => {
  closeAllSelectors();
});

// ============================================
// MODAL: TEXTE (Plein écran)
// ============================================
let textModalInstance = null;

function openTextModal() {
  if (!textModalInstance) {
    textModalInstance = createTextModal();
    document.body.appendChild(textModalInstance);
  }

  syncTextModalState();
  textModalInstance.classList.add('open');
  document.body.style.overflow = 'hidden';

  setTimeout(() => {
    const input = textModalInstance.querySelector('#modalTextInput');
    if (input) input.focus();
  }, 300);
}

function closeTextModal() {
  if (textModalInstance && textModalInstance.classList.contains('open')) {
    textModalInstance.classList.remove('open');
    document.body.style.overflow = '';
  }
}

function createTextModal() {
  const modal = document.createElement('div');
  modal.id = 'textModal';
  modal.className = 'ps-fullscreen-modal';

  modal.innerHTML = `
    <div class="ps-fullscreen-modal-header">
      <h2 class="ps-fullscreen-modal-title">Ajouter du texte</h2>
      <button class="ps-fullscreen-modal-close" type="button" aria-label="Fermer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ps-fullscreen-modal-body">
      <div class="ps-form-group">
        <label class="ps-label">Votre texte</label>
        <input type="text" class="ps-input ps-input-lg" id="modalTextInput" placeholder="Entrez votre texte...">
      </div>

      <div class="ps-form-group">
        <label class="ps-label">Police</label>
        <button type="button" class="ps-selector-btn" id="modalFontBtn">
          <span class="ps-selector-text">Choisir une police</span>
          <span class="ps-selector-arrow">›</span>
        </button>
      </div>

      <div class="ps-form-group">
        <label class="ps-label">Taille</label>
        <div class="ps-range-wrapper">
          <input type="range" class="ps-range" id="modalFontSize" min="12" max="72" value="24">
          <span class="ps-range-value" id="modalFontSizeValue">24px</span>
        </div>
      </div>

      <div class="ps-form-group">
        <label class="ps-label">Couleur</label>
        <div class="ps-color-swatches" id="modalColorSwatches">
          <!-- Couleurs générées dynamiquement -->
        </div>
      </div>

      <div class="ps-form-group">
        <label class="ps-label">Alignement</label>
        <div class="ps-align-group ps-align-group-lg">
          <button class="ps-align-btn active" data-align="left" type="button">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm0 4h12v2H3V7zm0 4h18v2H3v-2zm0 4h12v2H3v-2zm0 4h18v2H3v-2z"/></svg>
          </button>
          <button class="ps-align-btn" data-align="center" type="button">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm3 4h12v2H6V7zm-3 4h18v2H3v-2zm3 4h12v2H6v-2zm-3 4h18v2H3v-2z"/></svg>
          </button>
          <button class="ps-align-btn" data-align="right" type="button">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm6 4h12v2H9V7zm-6 4h18v2H3v-2zm6 4h12v2H9v-2zm-6 4h18v2H3v-2z"/></svg>
          </button>
        </div>
      </div>

      <div class="ps-form-group">
        <label class="ps-label">Technique d'impression</label>
        <button type="button" class="ps-selector-btn" id="modalTechniqueBtn">
          <span class="ps-selector-text">Choisir une technique</span>
          <span class="ps-selector-arrow">›</span>
        </button>
      </div>

      <!-- Contrôles du texte actif (mobile) -->
      <div class="ps-text-controls disabled" id="modalTextLayerControls">
        <div class="ps-text-controls-header">
          <span class="ps-text-controls-title">Modifier le texte</span>
          <button class="ps-btn-delete" data-action="delete" title="Supprimer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
          </button>
        </div>

        <div class="ps-text-controls-row">
          <div class="ps-text-controls-group">
            <label class="ps-label">Style</label>
            <div class="ps-style-btns">
              <button class="ps-style-btn" data-action="bold" title="Gras">B</button>
              <button class="ps-style-btn" data-action="italic" title="Italique"><i>I</i></button>
            </div>
          </div>
          <div class="ps-text-controls-group">
            <label class="ps-label">Dimensions</label>
            <div class="ps-size-btns">
              <button class="ps-size-btn" data-action="decrease" title="Réduire">−</button>
              <button class="ps-size-btn" data-action="increase" title="Agrandir">+</button>
            </div>
          </div>
        </div>

        <div class="ps-text-controls-group">
          <label class="ps-label">Rotation <span id="modalRotationValue">0°</span></label>
          <input type="range" class="ps-range" id="modalRotationSlider" min="-180" max="180" value="0">
        </div>

        <div class="ps-text-controls-group">
          <label class="ps-label">Déplacer</label>
          <div class="ps-move-grid">
            <button class="ps-move-btn" data-direction="up" title="Haut">↑</button>
            <button class="ps-move-btn" data-direction="left" title="Gauche">←</button>
            <button class="ps-move-btn" data-direction="right" title="Droite">→</button>
            <button class="ps-move-btn" data-direction="down" title="Bas">↓</button>
          </div>
        </div>

        <div class="ps-text-controls-empty">
          <span>Sélectionnez un texte pour le modifier</span>
        </div>
      </div>
    </div>
    <div class="ps-fullscreen-modal-footer">
      <button class="ps-btn ps-btn-primary ps-btn-block ps-btn-lg" id="modalBtnAddText">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Ajouter le texte
      </button>
    </div>
  `;

  // === EVENT LISTENERS ISOLÉS ===

  // Fermer
  modal.querySelector('.ps-fullscreen-modal-close').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeTextModal();
  });

  // Input texte
  modal.querySelector('#modalTextInput').addEventListener('input', (e) => {
    state.textSettings.text = e.target.value;
  });

  // Font size
  const fontSizeInput = modal.querySelector('#modalFontSize');
  const fontSizeValue = modal.querySelector('#modalFontSizeValue');
  fontSizeInput.addEventListener('input', (e) => {
    state.textSettings.fontSize = parseInt(e.target.value);
    fontSizeValue.textContent = e.target.value + 'px';
  });

  // Couleur - Swatches (rendues dans syncTextModalState)
  renderModalColorSwatches(modal);

  // Alignement
  modal.querySelectorAll('.ps-align-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      modal.querySelectorAll('.ps-align-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.textSettings.align = btn.dataset.align;
    });
  });

  // Bouton Police → ouvre modal POLICE dédié
  modal.querySelector('#modalFontBtn').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    openFontModal();
  });

  // Bouton Technique → ouvre modal TECHNIQUE dédié
  modal.querySelector('#modalTechniqueBtn').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    openTechniqueModal();
  });

  // Ajouter texte
  modal.querySelector('#modalBtnAddText').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    addTextLayer();
    closeTextModal();
  });

  // === Contrôles du texte actif (mobile modal) ===
  const controls = modal.querySelector('#modalTextLayerControls');
  if (controls) {
    // Supprimer
    controls.querySelector('[data-action="delete"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      deleteActiveTextLayer();
    });

    // Gras
    controls.querySelector('[data-action="bold"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleBold();
    });

    // Italique
    controls.querySelector('[data-action="italic"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleItalic();
    });

    // Dimensions
    controls.querySelector('[data-action="decrease"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      decreaseSize();
    });

    controls.querySelector('[data-action="increase"]')?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      increaseSize();
    });

    // Rotation
    const rotationSlider = controls.querySelector('#modalRotationSlider');
    const rotationValue = controls.querySelector('#modalRotationValue');
    if (rotationSlider) {
      rotationSlider.addEventListener('input', (e) => {
        e.stopPropagation();
        setRotation(e.target.value);
        if (rotationValue) rotationValue.textContent = e.target.value + '°';
      });
    }

    // Déplacer
    controls.querySelectorAll('.ps-move-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        nudgeLayer(btn.dataset.direction);
      });
    });
  }

  return modal;
}

function syncTextModalState() {
  if (!textModalInstance) return;

  const textInput = textModalInstance.querySelector('#modalTextInput');
  const fontBtn = textModalInstance.querySelector('#modalFontBtn .ps-selector-text');
  const fontSize = textModalInstance.querySelector('#modalFontSize');
  const fontSizeValue = textModalInstance.querySelector('#modalFontSizeValue');
  const alignBtns = textModalInstance.querySelectorAll('.ps-align-btn');
  const techniqueBtn = textModalInstance.querySelector('#modalTechniqueBtn .ps-selector-text');

  textInput.value = state.textSettings.text || '';
  fontSize.value = state.textSettings.fontSize;
  fontSizeValue.textContent = state.textSettings.fontSize + 'px';

  // Couleur - Sync swatches
  textModalInstance.querySelectorAll('#modalColorSwatches .ps-color-swatch').forEach(sw => {
    sw.classList.toggle('active', sw.dataset.color === state.textSettings.color);
  });

  // Police
  const currentFont = state.fonts.find(f => f.family === state.textSettings.fontFamily);
  if (currentFont) {
    fontBtn.textContent = currentFont.label;
  }

  // Technique
  const currentTech = state.techniques.find(t => t.value === state.currentTechnique);
  if (currentTech) {
    const priceLabel = currentTech.price > 0 ? ` (+${formatPrice(currentTech.price)})` : '';
    techniqueBtn.textContent = currentTech.label + priceLabel;
  }

  // Alignement
  alignBtns.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.align === state.textSettings.align);
  });
}

// ============================================
// MODAL: POLICE (Plein écran dédié)
// ============================================
let fontModalInstance = null;
let fontModalTempSelection = null;

async function openFontModal() {
  if (!fontModalInstance) {
    fontModalInstance = createFontModal();
    document.body.appendChild(fontModalInstance);
  }

  // Sauvegarder la sélection actuelle
  fontModalTempSelection = state.textSettings.fontFamily;

  // Afficher le modal avec un état de chargement
  const list = fontModalInstance.querySelector('#fontModalList');
  list.innerHTML = '<div class="ps-fonts-loading">Chargement des polices...</div>';
  fontModalInstance.classList.add('open');

  // Charger explicitement chaque police avant de rendre la liste
  try {
    const fontLoadPromises = state.fonts.map(async font => {
      if (font.css_url) {
        try {
          // Forcer le chargement de la police avec document.fonts.load()
          await document.fonts.load(`400 24px "${font.family}"`);
        } catch (e) {
          // Ignorer les erreurs individuelles (police peut ne pas exister)
        }
      }
    });

    await Promise.all(fontLoadPromises);
    // Attendre aussi document.fonts.ready pour être sûr
    await document.fonts.ready;
  } catch (e) {
    console.warn('[Editor] Erreur chargement polices:', e);
  }

  renderFontModalList();
}

function closeFontModal() {
  if (fontModalInstance && fontModalInstance.classList.contains('open')) {
    fontModalInstance.classList.remove('open');
    fontModalTempSelection = null;
  }
}

function createFontModal() {
  const modal = document.createElement('div');
  modal.id = 'fontModal';
  modal.className = 'ps-fullscreen-modal ps-selection-modal';

  modal.innerHTML = `
    <div class="ps-fullscreen-modal-header">
      <h2 class="ps-fullscreen-modal-title">Choisir une police</h2>
      <button class="ps-fullscreen-modal-close" type="button" aria-label="Fermer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ps-fullscreen-modal-body ps-selection-list" id="fontModalList">
      <!-- Liste générée dynamiquement -->
    </div>
    <div class="ps-fullscreen-modal-footer">
      <button class="ps-btn ps-btn-primary ps-btn-block ps-btn-lg" id="fontModalValidate">
        Valider
      </button>
    </div>
  `;

  // Fermer (sans modifier)
  modal.querySelector('.ps-fullscreen-modal-close').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeFontModal();
  });

  // Valider
  modal.querySelector('#fontModalValidate').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (fontModalTempSelection) {
      const font = state.fonts.find(f => f.family === fontModalTempSelection);
      if (font) {
        state.textSettings.fontFamily = font.family;
        state.textSettings.fontId = font.id;
        syncTextModalState();

        // Appliquer au calque texte actif si existant
        applyFontToActiveLayer(font.family, font.id);
      }
    }
    closeFontModal();
  });

  return modal;
}

function renderFontModalList() {
  const list = fontModalInstance.querySelector('#fontModalList');
  list.innerHTML = '';

  state.fonts.forEach(font => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'ps-selection-item ps-font-item' + (font.family === fontModalTempSelection ? ' selected' : '');

    // Preview = nom de la police rendu AVEC cette police + fallback
    item.innerHTML = `
      <div class="ps-selection-item-content">
        <div class="ps-font-preview" style="font-family: '${font.family}', sans-serif;">${font.label}</div>
      </div>
      <div class="ps-selection-item-check"></div>
    `;

    item.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      fontModalTempSelection = font.family;
      list.querySelectorAll('.ps-selection-item').forEach(i => i.classList.remove('selected'));
      item.classList.add('selected');
    });

    list.appendChild(item);
  });
}

// ============================================
// MODAL: TECHNIQUE (Plein écran dédié)
// ============================================
let techniqueModalInstance = null;
let techniqueModalTempSelection = null;

function openTechniqueModal() {
  if (!techniqueModalInstance) {
    techniqueModalInstance = createTechniqueModal();
    document.body.appendChild(techniqueModalInstance);
  }

  // Sauvegarder la sélection actuelle
  techniqueModalTempSelection = state.currentTechnique;

  renderTechniqueModalList();
  techniqueModalInstance.classList.add('open');
}

function closeTechniqueModal() {
  if (techniqueModalInstance && techniqueModalInstance.classList.contains('open')) {
    techniqueModalInstance.classList.remove('open');
    techniqueModalTempSelection = null;
  }
}

function createTechniqueModal() {
  const modal = document.createElement('div');
  modal.id = 'techniqueModal';
  modal.className = 'ps-fullscreen-modal ps-selection-modal';

  modal.innerHTML = `
    <div class="ps-fullscreen-modal-header">
      <h2 class="ps-fullscreen-modal-title">Technique d'impression</h2>
      <button class="ps-fullscreen-modal-close" type="button" aria-label="Fermer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ps-fullscreen-modal-body ps-selection-list" id="techniqueModalList">
      <!-- Liste générée dynamiquement -->
    </div>
    <div class="ps-fullscreen-modal-footer">
      <button class="ps-btn ps-btn-primary ps-btn-block ps-btn-lg" id="techniqueModalValidate">
        Valider
      </button>
    </div>
  `;

  // Fermer (sans modifier)
  modal.querySelector('.ps-fullscreen-modal-close').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeTechniqueModal();
  });

  // Valider
  modal.querySelector('#techniqueModalValidate').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (techniqueModalTempSelection) {
      state.currentTechnique = techniqueModalTempSelection;
      updatePrice();
      syncTextModalState();
    }
    closeTechniqueModal();
  });

  return modal;
}

function renderTechniqueModalList() {
  const list = techniqueModalInstance.querySelector('#techniqueModalList');
  list.innerHTML = '';

  state.techniques.forEach(tech => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'ps-selection-item ps-technique-item' + (tech.value === techniqueModalTempSelection ? ' selected' : '');

    // Image de preview si disponible
    let imageHtml = '';
    if (tech.images && tech.images.length > 0) {
      imageHtml = `<div class="ps-technique-image"><img src="${tech.images[0]}" alt="${tech.label}"></div>`;
    }

    // Prix
    let priceHtml = '';
    if (tech.price > 0) {
      priceHtml = `<span class="ps-technique-price">+${formatPrice(tech.price)}</span>`;
    } else {
      priceHtml = `<span class="ps-technique-price included">Inclus</span>`;
    }

    item.innerHTML = `
      ${imageHtml}
      <div class="ps-selection-item-content">
        <div class="ps-selection-item-label">${tech.label}</div>
        ${tech.description ? `<div class="ps-selection-item-desc">${tech.description}</div>` : ''}
      </div>
      ${priceHtml}
      <div class="ps-selection-item-check"></div>
    `;

    item.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      techniqueModalTempSelection = tech.value;
      list.querySelectorAll('.ps-selection-item').forEach(i => i.classList.remove('selected'));
      item.classList.add('selected');
    });

    list.appendChild(item);
  });
}

// ============================================
// MODAL: DESIGNS (Plein écran dédié)
// ============================================
let designModalInstance = null;

function openDesignModal() {
  if (!designModalInstance) {
    designModalInstance = createDesignModal();
    document.body.appendChild(designModalInstance);
  }

  renderDesignModalContent();
  designModalInstance.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDesignModal() {
  if (designModalInstance && designModalInstance.classList.contains('open')) {
    designModalInstance.classList.remove('open');
    document.body.style.overflow = '';
  }
}

function createDesignModal() {
  const modal = document.createElement('div');
  modal.id = 'designModal';
  modal.className = 'ps-fullscreen-modal ps-assets-modal';

  modal.innerHTML = `
    <div class="ps-fullscreen-modal-header">
      <h2 class="ps-fullscreen-modal-title">Designs</h2>
      <button class="ps-fullscreen-modal-close" type="button" aria-label="Fermer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ps-fullscreen-modal-body" id="designModalContent">
      <!-- Contenu généré dynamiquement -->
    </div>
  `;

  modal.querySelector('.ps-fullscreen-modal-close').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeDesignModal();
  });

  return modal;
}

function renderDesignModalContent() {
  const content = designModalInstance.querySelector('#designModalContent');
  content.innerHTML = '';

  if (state.designs.length === 0) {
    content.innerHTML = '<div class="ps-assets-empty">Aucun design disponible</div>';
    return;
  }

  state.designs.forEach(category => {
    const section = document.createElement('div');
    section.className = 'ps-assets-category';

    const title = document.createElement('h3');
    title.className = 'ps-assets-category-title';
    title.textContent = category.category_name;
    section.appendChild(title);

    const grid = document.createElement('div');
    grid.className = 'ps-assets-grid';

    category.items.forEach(design => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'ps-assets-item';
      item.title = design.name;

      if (design.image) {
        item.innerHTML = `<img src="${design.image}" alt="${design.name}" loading="lazy">`;
      } else {
        item.innerHTML = `<span class="ps-assets-item-name">${design.name}</span>`;
      }

      item.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        addDesignLayer(design);
        closeDesignModal();
      });

      grid.appendChild(item);
    });

    section.appendChild(grid);
    content.appendChild(section);
  });
}

function addDesignLayer(design) {
  const layer = {
    id: generateId(),
    type: 'design',
    name: design.name,
    designId: design.id,
    image: design.image,
    x: 50,
    y: 50
  };

  state.layers.push(layer);
  renderLayer(layer);
  setActiveLayer(layer.id);
  updateLayersList();
  updatePrice();
}

// ============================================
// MODAL: ÉLÉMENTS (Plein écran dédié)
// ============================================
let elementModalInstance = null;

function openElementModal() {
  if (!elementModalInstance) {
    elementModalInstance = createElementModal();
    document.body.appendChild(elementModalInstance);
  }

  renderElementModalContent();
  elementModalInstance.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeElementModal() {
  if (elementModalInstance && elementModalInstance.classList.contains('open')) {
    elementModalInstance.classList.remove('open');
    document.body.style.overflow = '';
  }
}

function createElementModal() {
  const modal = document.createElement('div');
  modal.id = 'elementModal';
  modal.className = 'ps-fullscreen-modal ps-assets-modal';

  modal.innerHTML = `
    <div class="ps-fullscreen-modal-header">
      <h2 class="ps-fullscreen-modal-title">Éléments</h2>
      <button class="ps-fullscreen-modal-close" type="button" aria-label="Fermer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ps-fullscreen-modal-body" id="elementModalContent">
      <!-- Contenu généré dynamiquement -->
    </div>
  `;

  modal.querySelector('.ps-fullscreen-modal-close').addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeElementModal();
  });

  return modal;
}

function renderElementModalContent() {
  const content = elementModalInstance.querySelector('#elementModalContent');
  content.innerHTML = '';

  if (state.elements.length === 0) {
    content.innerHTML = '<div class="ps-assets-empty">Aucun élément disponible</div>';
    return;
  }

  state.elements.forEach(category => {
    const section = document.createElement('div');
    section.className = 'ps-assets-category';

    const title = document.createElement('h3');
    title.className = 'ps-assets-category-title';
    title.textContent = category.category_name;
    section.appendChild(title);

    const grid = document.createElement('div');
    grid.className = 'ps-assets-grid';

    category.items.forEach(element => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'ps-assets-item';
      item.title = element.name;

      let premiumBadge = '';
      if (element.is_premium) {
        premiumBadge = '<span class="ps-assets-badge-pro">PRO</span>';
      }

      if (element.image) {
        item.innerHTML = `${premiumBadge}<img src="${element.image}" alt="${element.name}" loading="lazy">`;
      } else {
        item.innerHTML = `${premiumBadge}<span class="ps-assets-item-name">${element.name}</span>`;
      }

      item.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        addElementLayer(element);
        closeElementModal();
      });

      grid.appendChild(item);
    });

    section.appendChild(grid);
    content.appendChild(section);
  });
}

function addElementLayer(element) {
  const layer = {
    id: generateId(),
    type: 'element',
    name: element.name,
    elementId: element.id,
    image: element.image,
    isPremium: element.is_premium,
    price: element.price,
    x: 50,
    y: 50
  };

  state.layers.push(layer);
  renderLayer(layer);
  setActiveLayer(layer.id);
  updateLayersList();
  updatePrice();
}

// ============================================
// TEXT LAYER
// ============================================
function addTextLayer() {
  const text = state.textSettings.text || 'Votre texte';

  const layer = {
    id: generateId(),
    type: 'text',
    name: text.substring(0, 15) + (text.length > 15 ? '...' : ''),
    text: text,
    fontFamily: state.textSettings.fontFamily,
    fontId: state.textSettings.fontId,
    fontSize: state.textSettings.fontSize,
    color: state.textSettings.color,
    align: state.textSettings.align,
    fontWeight: '400',
    fontStyle: 'normal',
    rotation: 0,
    scale: 1,
    x: 50,
    y: 50
  };

  state.layers.push(layer);
  renderLayer(layer);
  setActiveLayer(layer.id);
  updateLayersList();
  updatePrice();
  updateTextControlsState();

  // Reset
  state.textSettings.text = '';
}

// ============================================
// LAYER RENDERING
// ============================================
function renderLayer(layer) {
  const div = document.createElement('div');
  div.className = 'ps-layer';
  div.dataset.layerId = layer.id;
  div.style.left = layer.x + '%';
  div.style.top = layer.y + '%';

  if (layer.type === 'text') {
    div.classList.add('ps-layer-text');
    div.textContent = layer.text;
    div.style.fontFamily = layer.fontFamily;
    div.style.fontSize = (layer.fontSize * (layer.scale || 1)) + 'px';
    div.style.color = layer.color;
    div.style.textAlign = layer.align;
    div.style.fontWeight = layer.fontWeight || '400';
    div.style.fontStyle = layer.fontStyle || 'normal';
    const rotation = layer.rotation || 0;
    div.style.transform = `translate(-50%, -50%) rotate(${rotation}deg)`;
  } else if (layer.type === 'design' || layer.type === 'element') {
    div.style.transform = 'translate(-50%, -50%)';
  } else {
    div.style.transform = 'translate(-50%, -50%)';
  }

  if (layer.type === 'design' || layer.type === 'element') {
    div.classList.add('ps-layer-image');
    if (layer.image) {
      div.innerHTML = `<img src="${layer.image}" alt="${layer.name}" draggable="false">`;
    } else if (layer.svg) {
      div.innerHTML = layer.svg;
    }
  }

  els.printArea.appendChild(div);

  interact(div)
    .draggable({
      listeners: {
        move(event) {
          const l = state.layers.find(l => l.id === event.target.dataset.layerId);
          if (!l) return;

          const printArea = els.printArea.getBoundingClientRect();
          const deltaX = (event.dx / printArea.width) * 100;
          const deltaY = (event.dy / printArea.height) * 100;

          l.x = Math.max(0, Math.min(100, l.x + deltaX));
          l.y = Math.max(0, Math.min(100, l.y + deltaY));

          event.target.style.left = l.x + '%';
          event.target.style.top = l.y + '%';
        }
      }
    })
    .on('tap', (event) => {
      setActiveLayer(event.target.dataset.layerId);
    });
}

function setActiveLayer(layerId) {
  state.activeLayerId = layerId;

  $$('.ps-layer').forEach(el => {
    el.classList.toggle('active', el.dataset.layerId === layerId);
  });

  $$('.ps-layer-item').forEach(el => {
    el.classList.toggle('active', el.dataset.layerId === layerId);
  });

  const layer = state.layers.find(l => l.id === layerId);
  if (layer && layer.type === 'text') {
    state.textSettings = {
      text: layer.text,
      fontFamily: layer.fontFamily,
      fontId: layer.fontId,
      fontSize: layer.fontSize,
      color: layer.color,
      align: layer.align
    };
  }

  updateTextControlsState();
}

// ============================================
// TEXT LAYER CONTROLS
// ============================================
function getActiveTextLayer() {
  if (!state.activeLayerId) return null;
  const layer = state.layers.find(l => l.id === state.activeLayerId);
  return (layer && layer.type === 'text') ? layer : null;
}

function updateActiveTextLayerDOM(layer) {
  const div = $(`.ps-layer[data-layer-id="${layer.id}"]`);
  if (!div) return;

  div.style.fontSize = (layer.fontSize * (layer.scale || 1)) + 'px';
  div.style.fontWeight = layer.fontWeight || '400';
  div.style.fontStyle = layer.fontStyle || 'normal';
  const rotation = layer.rotation || 0;
  div.style.transform = `translate(-50%, -50%) rotate(${rotation}deg)`;
  div.style.left = layer.x + '%';
  div.style.top = layer.y + '%';
}

function updateTextControlsState() {
  const layer = getActiveTextLayer();
  const hasTextLayer = !!layer;

  // Update desktop controls
  const desktopControls = $('#textLayerControls');
  if (desktopControls) {
    desktopControls.classList.toggle('disabled', !hasTextLayer);

    if (hasTextLayer) {
      const boldBtn = desktopControls.querySelector('[data-action="bold"]');
      const italicBtn = desktopControls.querySelector('[data-action="italic"]');
      const rotationSlider = desktopControls.querySelector('#rotationSlider');
      const rotationValue = desktopControls.querySelector('#rotationValue');

      if (boldBtn) boldBtn.classList.toggle('active', layer.fontWeight === '700');
      if (italicBtn) italicBtn.classList.toggle('active', layer.fontStyle === 'italic');
      if (rotationSlider) rotationSlider.value = layer.rotation || 0;
      if (rotationValue) rotationValue.textContent = (layer.rotation || 0) + '°';
    }
  }

  // Update mobile modal controls
  const modalControls = $('#modalTextLayerControls');
  if (modalControls) {
    modalControls.classList.toggle('disabled', !hasTextLayer);

    if (hasTextLayer) {
      const boldBtn = modalControls.querySelector('[data-action="bold"]');
      const italicBtn = modalControls.querySelector('[data-action="italic"]');
      const rotationSlider = modalControls.querySelector('#modalRotationSlider');
      const rotationValue = modalControls.querySelector('#modalRotationValue');

      if (boldBtn) boldBtn.classList.toggle('active', layer.fontWeight === '700');
      if (italicBtn) italicBtn.classList.toggle('active', layer.fontStyle === 'italic');
      if (rotationSlider) rotationSlider.value = layer.rotation || 0;
      if (rotationValue) rotationValue.textContent = (layer.rotation || 0) + '°';
    }
  }
}

function toggleBold() {
  const layer = getActiveTextLayer();
  if (!layer) return;

  layer.fontWeight = layer.fontWeight === '700' ? '400' : '700';
  updateActiveTextLayerDOM(layer);
  updateTextControlsState();
}

function toggleItalic() {
  const layer = getActiveTextLayer();
  if (!layer) return;

  layer.fontStyle = layer.fontStyle === 'italic' ? 'normal' : 'italic';
  updateActiveTextLayerDOM(layer);
  updateTextControlsState();
}

function increaseSize() {
  const layer = getActiveTextLayer();
  if (!layer) return;

  layer.scale = Math.min(3, (layer.scale || 1) + 0.1);
  updateActiveTextLayerDOM(layer);
}

function decreaseSize() {
  const layer = getActiveTextLayer();
  if (!layer) return;

  layer.scale = Math.max(0.3, (layer.scale || 1) - 0.1);
  updateActiveTextLayerDOM(layer);
}

function setRotation(angle) {
  const layer = getActiveTextLayer();
  if (!layer) return;

  layer.rotation = Math.max(-180, Math.min(180, parseInt(angle) || 0));
  updateActiveTextLayerDOM(layer);
  updateTextControlsState();
}

function nudgeLayer(direction) {
  const layer = getActiveTextLayer();
  if (!layer) return;

  const step = 2; // 2% de la print-area

  switch (direction) {
    case 'left':
      layer.x = Math.max(0, layer.x - step);
      break;
    case 'right':
      layer.x = Math.min(100, layer.x + step);
      break;
    case 'up':
      layer.y = Math.max(0, layer.y - step);
      break;
    case 'down':
      layer.y = Math.min(100, layer.y + step);
      break;
  }

  updateActiveTextLayerDOM(layer);
}

function deleteActiveTextLayer() {
  const layer = getActiveTextLayer();
  if (!layer) return;

  removeLayer(layer.id);
  updateTextControlsState();
}

function removeLayer(layerId) {
  const index = state.layers.findIndex(l => l.id === layerId);
  if (index === -1) return;

  state.layers.splice(index, 1);

  const div = $(`[data-layer-id="${layerId}"]`);
  if (div) div.remove();

  if (state.activeLayerId === layerId) {
    state.activeLayerId = null;
  }

  updateLayersList();
  updatePrice();
}

function moveLayerUp(layerId) {
  const index = state.layers.findIndex(l => l.id === layerId);
  if (index <= 0) return;

  [state.layers[index - 1], state.layers[index]] = [state.layers[index], state.layers[index - 1]];
  updateLayersList();
  reorderLayersDom();
}

function moveLayerDown(layerId) {
  const index = state.layers.findIndex(l => l.id === layerId);
  if (index === -1 || index >= state.layers.length - 1) return;

  [state.layers[index], state.layers[index + 1]] = [state.layers[index + 1], state.layers[index]];
  updateLayersList();
  reorderLayersDom();
}

function reorderLayersDom() {
  state.layers.forEach(layer => {
    const el = $(`[data-layer-id="${layer.id}"]`);
    if (el) {
      els.printArea.appendChild(el);
    }
  });
}

// ============================================
// LAYERS LIST UI
// ============================================
function updateLayersList() {
  const hasLayers = state.layers.length > 0;
  if (els.layersEmpty) els.layersEmpty.style.display = hasLayers ? 'none' : 'block';
  if (els.layersList) els.layersList.innerHTML = '';

  [...state.layers].reverse().forEach(layer => {
    const item = document.createElement('div');
    item.className = 'ps-layer-item' + (layer.id === state.activeLayerId ? ' active' : '');
    item.dataset.layerId = layer.id;

    let icon, typeLabel;
    switch (layer.type) {
      case 'text':
        icon = 'T';
        typeLabel = 'Texte';
        break;
      case 'design':
        icon = '★';
        typeLabel = 'Design';
        break;
      case 'element':
        icon = '■';
        typeLabel = layer.isPremium ? 'Élément PRO' : 'Élément';
        break;
      default:
        icon = '?';
        typeLabel = 'Autre';
    }

    item.innerHTML = `
      <div class="ps-layer-icon">${icon}</div>
      <div class="ps-layer-info">
        <div class="ps-layer-name">${layer.name}</div>
        <div class="ps-layer-type">${typeLabel}</div>
      </div>
      <div class="ps-layer-actions">
        <button class="ps-layer-action" data-action="up" title="Monter">↑</button>
        <button class="ps-layer-action" data-action="down" title="Descendre">↓</button>
        <button class="ps-layer-action danger" data-action="delete" title="Supprimer">×</button>
      </div>
    `;

    item.addEventListener('click', (e) => {
      if (e.target.closest('.ps-layer-action')) return;
      setActiveLayer(layer.id);
    });

    item.querySelectorAll('.ps-layer-action').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const action = btn.dataset.action;
        if (action === 'up') moveLayerUp(layer.id);
        else if (action === 'down') moveLayerDown(layer.id);
        else if (action === 'delete') removeLayer(layer.id);
      });
    });

    if (els.layersList) els.layersList.appendChild(item);
  });
}

// ============================================
// PRICE
// ============================================
function updatePrice() {
  const technique = state.techniques.find(t => t.value === state.currentTechnique);
  const techniquePrice = technique ? technique.price : 0;

  state.price.technique = techniquePrice;
  state.price.total = state.price.base + state.price.technique;

  if (els.priceBase) els.priceBase.textContent = formatPrice(state.price.base);
  if (els.priceTechnique) els.priceTechnique.textContent = formatPrice(state.price.technique);
  if (els.priceTotal) els.priceTotal.textContent = formatPrice(state.price.total);
  if (els.ctaPrice) els.ctaPrice.textContent = formatPrice(state.price.total);
}

// ============================================
// TECHNIQUE RENDER PREVIEW (Lightbox images)
// ============================================
function initPreview() {
  if (els.btnPreview) {
    els.btnPreview.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      showTechniqueRender();
    });
  }
}

function showTechniqueRender() {
  const technique = state.techniques.find(t => t.value === state.currentTechnique);

  if (!technique || !technique.images || technique.images.length === 0) {
    alert('Aucune image de rendu disponible pour cette technique.');
    return;
  }

  let lightbox = document.getElementById('techniqueLightbox');

  if (!lightbox) {
    lightbox = document.createElement('div');
    lightbox.id = 'techniqueLightbox';
    lightbox.className = 'ps-lightbox';
    lightbox.innerHTML = `
      <div class="ps-lightbox-overlay"></div>
      <div class="ps-lightbox-content">
        <div class="ps-lightbox-header">
          <h3 class="ps-lightbox-title"></h3>
          <button class="ps-lightbox-close" type="button">×</button>
        </div>
        <div class="ps-lightbox-body"></div>
      </div>
    `;
    document.body.appendChild(lightbox);

    lightbox.querySelector('.ps-lightbox-overlay').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeTechniqueRender();
    });
    lightbox.querySelector('.ps-lightbox-close').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeTechniqueRender();
    });
  }

  const title = lightbox.querySelector('.ps-lightbox-title');
  const body = lightbox.querySelector('.ps-lightbox-body');

  title.textContent = `Rendu réel : ${technique.label}`;

  const disclaimerHtml = `
    <div class="ps-lightbox-disclaimer">
      <strong>Information</strong><br>
      Les images présentées correspondent à un rendu indicatif de la technique sélectionnée.
      Le résultat final peut légèrement varier selon le produit et le support.
    </div>
  `;

  const imagesHtml = technique.images.map(url =>
    `<div class="ps-lightbox-image"><img src="${url}" alt="Rendu ${technique.label}" loading="lazy"></div>`
  ).join('');

  body.innerHTML = disclaimerHtml + imagesHtml;

  lightbox.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeTechniqueRender() {
  const lightbox = document.getElementById('techniqueLightbox');
  if (lightbox) {
    lightbox.classList.remove('open');
    document.body.style.overflow = '';
  }
}

// ============================================
// ADD TO CART
// ============================================
function initAddToCart() {
  if (!els.btnAddToCart) return;

  els.btnAddToCart.addEventListener('click', async (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (state.layers.length === 0) {
      alert('Ajoutez au moins un élément (texte, design ou forme) avant de continuer.');
      return;
    }

    const payload = {
      product_id: state.productId,
      color_id: state.currentColorId || 0,
      size: null,
      technique: state.currentTechnique,
      view: state.currentView,
      layers: state.layers.map(l => {
        const base = {
          type: l.type,
          name: l.name,
          x: l.x,
          y: l.y
        };

        if (l.type === 'text') {
          return {
            ...base,
            content: l.text,
            font_id: l.fontId,
            font_family: l.fontFamily,
            font_size: l.fontSize,
            color: l.color,
            align: l.align
          };
        } else if (l.type === 'design') {
          return {
            ...base,
            design_id: l.designId,
            image: l.image
          };
        } else if (l.type === 'element') {
          return {
            ...base,
            element_id: l.elementId,
            image: l.image,
            is_premium: l.isPremium,
            price: l.price
          };
        }

        return base;
      })
    };

    try {
      els.btnAddToCart.disabled = true;
      els.btnAddToCart.textContent = 'Ajout en cours...';

      const response = await fetch(`${CONFIG.apiBase}/cart/add-config.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.ok) {
        window.location.href = result.redirect || '/public/cart.php';
      } else {
        alert(result.error || 'Erreur lors de l\'ajout au panier.');
        els.btnAddToCart.disabled = false;
        els.btnAddToCart.textContent = 'Ajouter au panier';
      }
    } catch (error) {
      console.error('Erreur panier:', error);
      alert('Erreur de connexion. Veuillez réessayer.');
      els.btnAddToCart.disabled = false;
      els.btnAddToCart.textContent = 'Ajouter au panier';
    }
  });
}

// ============================================
// INIT
// ============================================
async function init() {
  // Charger les données depuis l'API (produit + assets + couleurs en parallèle)
  const [productLoaded, assetsLoaded, colorsLoaded] = await Promise.all([
    loadProductData(),
    loadAssetsData(),
    loadTextColorsData()
  ]);

  if (!productLoaded) {
    return;
  }

  // Charger les CSS des polices
  loadFontCSS();

  // Render UI depuis les données API
  renderProductInfo();
  renderPrintZone();

  // Initialiser les interactions
  initTabs();
  initPreview();
  initAddToCart();

  // Initialiser le prix
  updatePrice();
  updateLayersList();
}

// Start
document.addEventListener('DOMContentLoaded', init);
