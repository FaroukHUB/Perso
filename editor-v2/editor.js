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

  // Au chargement : aucun panneau affiché, aucun onglet actif
  els.tabs.forEach(t => t.classList.remove('active'));
  els.panels.forEach(p => p.classList.remove('active'));

  els.tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const tabId = tab.dataset.tab;

      // Texte tab → ouvrir modal plein écran (pas de panneau inline)
      if (tabId === 'text') {
        openTextModal();
        return;
      }

      els.tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      els.panels.forEach(p => p.classList.remove('active'));
      const panel = $(`#panel-${tabId}`);
      if (panel) panel.classList.add('active');
    });
  });
}

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

      <div class="ps-form-row">
        <div class="ps-form-group ps-form-group-flex">
          <label class="ps-label">Taille</label>
          <div class="ps-range-wrapper">
            <input type="range" class="ps-range" id="modalFontSize" min="12" max="72" value="24">
            <span class="ps-range-value" id="modalFontSizeValue">24px</span>
          </div>
        </div>
        <div class="ps-form-group ps-form-group-color">
          <label class="ps-label">Couleur</label>
          <input type="color" class="ps-color-input ps-color-input-lg" id="modalTextColor" value="#000000">
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

  // Couleur
  modal.querySelector('#modalTextColor').addEventListener('input', (e) => {
    state.textSettings.color = e.target.value;
  });

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

  return modal;
}

function syncTextModalState() {
  if (!textModalInstance) return;

  const textInput = textModalInstance.querySelector('#modalTextInput');
  const fontBtn = textModalInstance.querySelector('#modalFontBtn .ps-selector-text');
  const fontSize = textModalInstance.querySelector('#modalFontSize');
  const fontSizeValue = textModalInstance.querySelector('#modalFontSizeValue');
  const textColor = textModalInstance.querySelector('#modalTextColor');
  const alignBtns = textModalInstance.querySelectorAll('.ps-align-btn');
  const techniqueBtn = textModalInstance.querySelector('#modalTechniqueBtn .ps-selector-text');

  textInput.value = state.textSettings.text || '';
  fontSize.value = state.textSettings.fontSize;
  fontSizeValue.textContent = state.textSettings.fontSize + 'px';
  textColor.value = state.textSettings.color;

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
// DESIGNS GRID
// ============================================
function initDesignsGrid() {
  if (!els.designsGrid) return;
  els.designsGrid.innerHTML = '';

  if (state.designs.length === 0) {
    els.designsGrid.innerHTML = '<div class="ps-grid-loading">Aucun design disponible</div>';
    return;
  }

  state.designs.forEach(category => {
    if (state.designs.length > 1) {
      const catTitle = document.createElement('div');
      catTitle.className = 'ps-grid-category-title';
      catTitle.textContent = category.category_name;
      catTitle.style.cssText = 'grid-column: 1 / -1; font-size: 12px; font-weight: 600; color: var(--gray); text-transform: uppercase; margin: 8px 0 4px;';
      els.designsGrid.appendChild(catTitle);
    }

    category.items.forEach(design => {
      const item = document.createElement('div');
      item.className = 'ps-grid-item';
      item.title = design.name;

      if (design.image) {
        item.innerHTML = `<img src="${design.image}" alt="${design.name}" loading="lazy">`;
      } else {
        item.innerHTML = `<span style="font-size: 10px; color: var(--gray);">${design.name}</span>`;
      }

      item.addEventListener('click', () => addDesignLayer(design));
      els.designsGrid.appendChild(item);
    });
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
// ELEMENTS GRID
// ============================================
function initElementsGrid() {
  if (!els.elementsGrid) return;
  els.elementsGrid.innerHTML = '';

  if (state.elements.length === 0) {
    els.elementsGrid.innerHTML = '<div class="ps-grid-loading">Aucun élément disponible</div>';
    return;
  }

  state.elements.forEach(category => {
    if (state.elements.length > 1) {
      const catTitle = document.createElement('div');
      catTitle.className = 'ps-grid-category-title';
      catTitle.textContent = category.category_name;
      catTitle.style.cssText = 'grid-column: 1 / -1; font-size: 12px; font-weight: 600; color: var(--gray); text-transform: uppercase; margin: 8px 0 4px;';
      els.elementsGrid.appendChild(catTitle);
    }

    category.items.forEach(element => {
      const item = document.createElement('div');
      item.className = 'ps-grid-item';
      item.title = element.name;

      let premiumBadge = '';
      if (element.is_premium) {
        premiumBadge = '<span style="position: absolute; top: 4px; right: 4px; background: var(--gradient-pink); color: white; font-size: 8px; padding: 2px 4px; border-radius: 4px;">PRO</span>';
        item.style.position = 'relative';
      }

      if (element.image) {
        item.innerHTML = `${premiumBadge}<img src="${element.image}" alt="${element.name}" loading="lazy">`;
      } else {
        item.innerHTML = `${premiumBadge}<span style="font-size: 10px; color: var(--gray);">${element.name}</span>`;
      }

      item.addEventListener('click', () => addElementLayer(element));
      els.elementsGrid.appendChild(item);
    });
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
    x: 50,
    y: 50
  };

  state.layers.push(layer);
  renderLayer(layer);
  setActiveLayer(layer.id);
  updateLayersList();
  updatePrice();

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
  div.style.transform = 'translate(-50%, -50%)';

  if (layer.type === 'text') {
    div.classList.add('ps-layer-text');
    div.textContent = layer.text;
    div.style.fontFamily = layer.fontFamily;
    div.style.fontSize = layer.fontSize + 'px';
    div.style.color = layer.color;
    div.style.textAlign = layer.align;
  } else if (layer.type === 'design' || layer.type === 'element') {
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
  // Charger les données depuis l'API (produit + assets en parallèle)
  const [productLoaded, assetsLoaded] = await Promise.all([
    loadProductData(),
    loadAssetsData()
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
  initDesignsGrid();
  initElementsGrid();
  initPreview();
  initAddToCart();

  // Initialiser le prix
  updatePrice();
  updateLayersList();
}

// Start
document.addEventListener('DOMContentLoaded', init);
