/**
 * PERSONNALY - Editor V2 JavaScript
 *
 * ARCHITECTURE :
 * - Toutes les données viennent de l'API /public/api/editor/product.php
 * - Aucun mock, aucune donnée hardcodée
 * - Le prix est calculé côté backend (frontend = estimation visuelle uniquement)
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
  designs: [],      // Groupés par catégorie
  elements: [],     // Groupés par catégorie

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
// DESIGNS & ELEMENTS (chargés depuis l'API)
// ============================================
// Note: Les designs et éléments sont chargés dynamiquement
// depuis /public/api/editor/assets.php

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
  tabs: null, // Set after DOM ready
  panels: null,

  // Text controls
  textInput: $('#textInput'),
  fontSelector: $('#fontSelector'),
  fontSize: $('#fontSize'),
  textColor: $('#textColor'),
  alignBtns: null,
  btnAddText: $('#btnAddText'),

  // Technique (custom select)
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
  btnAddToCart: $('#btnAddToCart'),

  // Bottom Sheet
  bottomsheet: $('#bottomsheet'),
  bottomsheetOverlay: $('#bottomsheetOverlay'),
  bottomsheetTitle: $('#bottomsheetTitle'),
  bottomsheetContent: $('#bottomsheetContent'),
  bottomsheetClose: $('#bottomsheetClose')
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
// BOTTOM SHEET
// ============================================
let currentBottomSheetTarget = null;

function openBottomSheet(title, options, onSelect, selectedValue = null) {
  if (!els.bottomsheet || !els.bottomsheetOverlay) return;

  els.bottomsheetTitle.textContent = title;
  els.bottomsheetContent.innerHTML = '';

  options.forEach(opt => {
    const btn = document.createElement('button');
    btn.className = 'ps-option' + (opt.value === selectedValue ? ' selected' : '');
    btn.type = 'button';

    // Structure selon le type d'option
    let previewHtml = '';
    let infoHtml = '';

    if (opt.preview === 'font') {
      // Font preview: Nom + Sample rendu avec la police
      infoHtml = `
        <div class="ps-option-info ps-option-font-info">
          <div class="ps-option-label">${opt.label}</div>
          <div class="ps-option-font-sample" style="font-family: '${opt.fontFamily || 'inherit'}'">Aa Bb Cc 123</div>
        </div>
      `;
    } else {
      if (opt.preview === 'image' && opt.image) {
        previewHtml = `<div class="ps-option-preview"><img src="${opt.image}" alt="${opt.label}"></div>`;
      }
      infoHtml = `
        <div class="ps-option-info">
          <div class="ps-option-label">${opt.label}</div>
          ${opt.desc ? `<div class="ps-option-desc">${opt.desc}</div>` : ''}
        </div>
      `;
    }

    let priceHtml = '';
    if (opt.price !== undefined && opt.price > 0) {
      priceHtml = `<span class="ps-option-price">+${formatPrice(opt.price)}</span>`;
    } else if (opt.price === 0) {
      priceHtml = `<span class="ps-option-price included">Inclus</span>`;
    }

    btn.innerHTML = `
      ${previewHtml}
      ${infoHtml}
      ${priceHtml}
      <div class="ps-option-check"></div>
    `;

    btn.addEventListener('click', () => {
      onSelect(opt);
      closeBottomSheet();
    });

    els.bottomsheetContent.appendChild(btn);
  });

  els.bottomsheet.classList.add('open');
  els.bottomsheetOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeBottomSheet() {
  if (!els.bottomsheet || !els.bottomsheetOverlay) return;

  els.bottomsheet.classList.remove('open');
  els.bottomsheetOverlay.classList.remove('open');
  document.body.style.overflow = '';
  currentBottomSheetTarget = null;
}

function initBottomSheet() {
  if (els.bottomsheetClose) {
    els.bottomsheetClose.addEventListener('click', closeBottomSheet);
  }
  if (els.bottomsheetOverlay) {
    els.bottomsheetOverlay.addEventListener('click', closeBottomSheet);
  }
}

function updateCustomSelectText(selector, text) {
  const textEl = selector.querySelector('.ps-custom-select-text');
  if (textEl) {
    textEl.textContent = text;
    textEl.classList.remove('placeholder');
  }
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

    // Log dev pour debug
    if (state.designs.length === 0 && state.elements.length === 0) {
      console.info('[Editor] Aucun design/élément trouvé dans l\'admin. L\'UI affichera un état vide.');
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

    // Stocker les données
    state.product = data.product;
    state.colors = data.colors || [];
    state.printZones = data.print_zones || [];
    state.techniques = data.techniques || [];
    state.fonts = data.fonts || [];
    state.loaded = true;

    // Initialiser les valeurs par défaut
    const defaultColor = state.colors.find(c => c.is_default) || state.colors[0];
    if (defaultColor) {
      state.currentColorId = defaultColor.id;
    }

    const defaultTechnique = state.techniques[0];
    if (defaultTechnique) {
      state.currentTechnique = defaultTechnique.value;
    }

    // Prix de base
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

  // Titre
  if (els.productTitle) {
    els.productTitle.textContent = state.product.name;
  }

  // Image par défaut
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

function renderTechniques() {
  if (!els.techniqueSelector) return;

  // Afficher la technique par défaut
  const defaultTech = state.techniques.find(t => t.value === state.currentTechnique) || state.techniques[0];
  if (defaultTech) {
    const priceLabel = defaultTech.price > 0 ? ` (+${formatPrice(defaultTech.price)})` : '';
    updateCustomSelectText(els.techniqueSelector, defaultTech.label + priceLabel);
    els.techniqueSelector.dataset.value = defaultTech.value;
  }

  // Click handler pour ouvrir le bottom sheet
  els.techniqueSelector.addEventListener('click', () => {
    const options = state.techniques.map(t => ({
      value: t.value,
      label: t.label,
      desc: t.description || null,
      price: t.price,
      preview: t.images && t.images.length > 0 ? 'image' : null,
      image: t.images && t.images[0] ? t.images[0].url : null
    }));

    openBottomSheet('Technique d\'impression', options, (opt) => {
      state.currentTechnique = opt.value;
      const priceLabel = opt.price > 0 ? ` (+${formatPrice(opt.price)})` : '';
      updateCustomSelectText(els.techniqueSelector, opt.label + priceLabel);
      els.techniqueSelector.dataset.value = opt.value;
      updatePrice();
    }, state.currentTechnique);
  });
}

function renderFonts() {
  if (!els.fontSelector) return;

  // Charger les CSS des polices
  state.fonts.forEach(font => {
    if (font.css_url && !document.querySelector(`link[href="${font.css_url}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = font.css_url;
      document.head.appendChild(link);
    }
  });

  // Définir la police par défaut
  if (state.fonts.length > 0) {
    state.textSettings.fontFamily = state.fonts[0].family;
    state.textSettings.fontId = state.fonts[0].id;
    updateCustomSelectText(els.fontSelector, state.fonts[0].label);
    els.fontSelector.dataset.value = state.fonts[0].family;
  }

  // Click handler pour ouvrir le bottom sheet
  els.fontSelector.addEventListener('click', () => {
    const options = state.fonts.map(f => ({
      value: f.family,
      label: f.label,
      desc: f.category,
      fontFamily: f.family,
      fontId: f.id,
      preview: 'font'
    }));

    openBottomSheet('Choisir une police', options, (opt) => {
      state.textSettings.fontFamily = opt.value;
      state.textSettings.fontId = opt.fontId;
      updateCustomSelectText(els.fontSelector, opt.label);
      els.fontSelector.dataset.value = opt.value;
      updateActiveTextLayer();
    }, state.textSettings.fontFamily);
  });
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

    // Mettre à jour aussi la zone d'impression réelle
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

  els.tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const tabId = tab.dataset.tab;

      // Texte tab → ouvrir modal plein écran (mobile UX)
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
// DESIGNS GRID
// ============================================
function initDesignsGrid() {
  if (!els.designsGrid) return;
  els.designsGrid.innerHTML = '';

  if (state.designs.length === 0) {
    els.designsGrid.innerHTML = '<div class="ps-grid-loading">Aucun design disponible</div>';
    return;
  }

  // Afficher tous les designs (groupés par catégorie)
  state.designs.forEach(category => {
    // Titre de catégorie (optionnel)
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

  // Afficher tous les éléments (groupés par catégorie)
  state.elements.forEach(category => {
    // Titre de catégorie (optionnel)
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

      // Badge premium si applicable
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
function initTextControls() {
  els.alignBtns = $$('.ps-align-btn');

  if (els.textInput) {
    els.textInput.addEventListener('input', (e) => {
      state.textSettings.text = e.target.value;
      updateActiveTextLayer();
    });
  }

  // Note: fontSelector est géré dans renderFonts() via bottom sheet

  if (els.fontSize) {
    els.fontSize.addEventListener('input', (e) => {
      state.textSettings.fontSize = parseInt(e.target.value);
      updateActiveTextLayer();
    });
  }

  if (els.textColor) {
    els.textColor.addEventListener('input', (e) => {
      state.textSettings.color = e.target.value;
      updateActiveTextLayer();
    });
  }

  if (els.alignBtns) {
    els.alignBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        els.alignBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        state.textSettings.align = btn.dataset.align;
        updateActiveTextLayer();
      });
    });
  }

  if (els.btnAddText) {
    els.btnAddText.addEventListener('click', addTextLayer);
  }
}

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

  // Reset input
  if (els.textInput) {
    els.textInput.value = '';
    state.textSettings.text = '';
  }
}

function updateActiveTextLayer() {
  const layer = state.layers.find(l => l.id === state.activeLayerId);
  if (!layer || layer.type !== 'text') return;

  layer.text = state.textSettings.text || layer.text;
  layer.fontFamily = state.textSettings.fontFamily;
  layer.fontId = state.textSettings.fontId;
  layer.fontSize = state.textSettings.fontSize;
  layer.color = state.textSettings.color;
  layer.align = state.textSettings.align;
  layer.name = layer.text.substring(0, 15) + (layer.text.length > 15 ? '...' : '');

  const div = $(`[data-layer-id="${layer.id}"]`);
  if (div) {
    div.textContent = layer.text;
    div.style.fontFamily = layer.fontFamily;
    div.style.fontSize = layer.fontSize + 'px';
    div.style.color = layer.color;
    div.style.textAlign = layer.align;
  }

  updateLayersList();
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

  // Make draggable
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
    if (els.textInput) els.textInput.value = layer.text;

    // Mettre à jour le custom select de police
    if (els.fontSelector) {
      const font = state.fonts.find(f => f.family === layer.fontFamily);
      if (font) {
        updateCustomSelectText(els.fontSelector, font.label);
        els.fontSelector.dataset.value = font.family;
      }
    }

    if (els.fontSize) els.fontSize.value = layer.fontSize;
    if (els.textColor) els.textColor.value = layer.color;

    if (els.alignBtns) {
      els.alignBtns.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.align === layer.align);
      });
    }

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
// TECHNIQUE & PRICE
// ============================================
function initTechnique() {
  // La technique est maintenant gérée via le custom select dans renderTechniques()
  // Cette fonction est conservée pour la cohérence
}

function updatePrice() {
  // Trouver le prix de la technique sélectionnée
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
// TECHNIQUE RENDER PREVIEW (Images réelles)
// ============================================
function initPreview() {
  if (els.btnPreview) {
    els.btnPreview.addEventListener('click', showTechniqueRender);
  }
}

function showTechniqueRender() {
  // Trouver la technique sélectionnée
  const technique = state.techniques.find(t => t.value === state.currentTechnique);

  if (!technique || !technique.images || technique.images.length === 0) {
    alert('Aucune image de rendu disponible pour cette technique.');
    return;
  }

  // Créer le lightbox si n'existe pas
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

    // Event listeners
    lightbox.querySelector('.ps-lightbox-overlay').addEventListener('click', closeTechniqueRender);
    lightbox.querySelector('.ps-lightbox-close').addEventListener('click', closeTechniqueRender);
  }

  // Remplir le contenu
  const title = lightbox.querySelector('.ps-lightbox-title');
  const body = lightbox.querySelector('.ps-lightbox-body');

  title.textContent = `Rendu réel : ${technique.label}`;

  // Disclaimer + images
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

  // Ouvrir
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
// TEXT MODAL (Fullscreen Mobile)
// ============================================
function openTextModal() {
  let modal = document.getElementById('textModal');

  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'textModal';
    modal.className = 'ps-text-modal';
    modal.innerHTML = `
      <div class="ps-text-modal-header">
        <h2 class="ps-text-modal-title">Ajouter du texte</h2>
        <button class="ps-text-modal-close" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <div class="ps-text-modal-body">
        <div class="ps-form-group">
          <label class="ps-label">Votre texte</label>
          <input type="text" class="ps-input ps-input-lg" id="modalTextInput" placeholder="Entrez votre texte...">
        </div>

        <div class="ps-form-group">
          <label class="ps-label">Police</label>
          <div class="ps-custom-select ps-custom-select-lg" id="modalFontSelector" data-value="">
            <div class="ps-custom-select-trigger">
              <span class="ps-custom-select-text">Choisir une police</span>
              <span class="ps-custom-select-arrow">▼</span>
            </div>
          </div>
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
          <div class="ps-custom-select ps-custom-select-lg" id="modalTechniqueSelector" data-value="">
            <div class="ps-custom-select-trigger">
              <span class="ps-custom-select-text">Choisir une technique</span>
              <span class="ps-custom-select-arrow">▼</span>
            </div>
          </div>
        </div>
      </div>
      <div class="ps-text-modal-footer">
        <button class="ps-btn ps-btn-primary ps-btn-block ps-btn-lg" id="modalBtnAddText">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Ajouter le texte
        </button>
      </div>
    `;
    document.body.appendChild(modal);

    // Event listener fermeture - ISOLÉ avec stopPropagation
    modal.querySelector('.ps-text-modal-close').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeTextModal();
    });

    // Sync avec state et init contrôles modal
    initTextModalControls(modal);
  }

  // Sync les valeurs actuelles
  syncTextModalValues(modal);

  // Ouvrir
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';

  // Focus sur l'input
  setTimeout(() => {
    modal.querySelector('#modalTextInput').focus();
  }, 300);
}

function closeTextModal() {
  const modal = document.getElementById('textModal');
  if (modal && modal.classList.contains('open')) {
    modal.classList.remove('open');
    document.body.style.overflow = '';

    // Bloquer temporairement les clics pour éviter les événements fantômes
    modal.style.pointerEvents = 'none';
    setTimeout(() => {
      modal.style.pointerEvents = '';
    }, 400);
  }
}

function initTextModalControls(modal) {
  const textInput = modal.querySelector('#modalTextInput');
  const fontSelector = modal.querySelector('#modalFontSelector');
  const fontSize = modal.querySelector('#modalFontSize');
  const fontSizeValue = modal.querySelector('#modalFontSizeValue');
  const textColor = modal.querySelector('#modalTextColor');
  const alignBtns = modal.querySelectorAll('.ps-align-btn');
  const techniqueSelector = modal.querySelector('#modalTechniqueSelector');
  const btnAdd = modal.querySelector('#modalBtnAddText');

  // Text input
  textInput.addEventListener('input', (e) => {
    state.textSettings.text = e.target.value;
  });

  // Font size avec affichage valeur
  fontSize.addEventListener('input', (e) => {
    state.textSettings.fontSize = parseInt(e.target.value);
    fontSizeValue.textContent = e.target.value + 'px';
  });

  // Color
  textColor.addEventListener('input', (e) => {
    state.textSettings.color = e.target.value;
  });

  // Alignment
  alignBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      alignBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.textSettings.align = btn.dataset.align;
    });
  });

  // Font selector → bottom sheet (click isolé)
  fontSelector.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    const options = state.fonts.map(f => ({
      value: f.family,
      label: f.label,
      fontFamily: f.family,
      fontId: f.id,
      preview: 'font'
    }));

    openBottomSheet('Choisir une police', options, (opt) => {
      state.textSettings.fontFamily = opt.value;
      state.textSettings.fontId = opt.fontId;
      updateCustomSelectText(fontSelector, opt.label);
      fontSelector.dataset.value = opt.value;
    }, state.textSettings.fontFamily);
  });

  // Technique selector → bottom sheet (click isolé)
  techniqueSelector.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    const options = state.techniques.map(t => ({
      value: t.value,
      label: t.label,
      desc: t.description || null,
      price: t.price
    }));

    openBottomSheet('Technique d\'impression', options, (opt) => {
      state.currentTechnique = opt.value;
      const priceLabel = opt.price > 0 ? ` (+${formatPrice(opt.price)})` : '';
      updateCustomSelectText(techniqueSelector, opt.label + priceLabel);
      techniqueSelector.dataset.value = opt.value;
      updatePrice();

      // Sync aussi le sélecteur principal
      if (els.techniqueSelector) {
        updateCustomSelectText(els.techniqueSelector, opt.label + priceLabel);
        els.techniqueSelector.dataset.value = opt.value;
      }
    }, state.currentTechnique);
  });

  // Ajouter texte (click isolé)
  btnAdd.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    addTextLayer();
    closeTextModal();
  });
}

function syncTextModalValues(modal) {
  const textInput = modal.querySelector('#modalTextInput');
  const fontSelector = modal.querySelector('#modalFontSelector');
  const fontSize = modal.querySelector('#modalFontSize');
  const fontSizeValue = modal.querySelector('#modalFontSizeValue');
  const textColor = modal.querySelector('#modalTextColor');
  const alignBtns = modal.querySelectorAll('.ps-align-btn');
  const techniqueSelector = modal.querySelector('#modalTechniqueSelector');

  // Sync values
  textInput.value = state.textSettings.text || '';
  fontSize.value = state.textSettings.fontSize;
  fontSizeValue.textContent = state.textSettings.fontSize + 'px';
  textColor.value = state.textSettings.color;

  // Font
  const currentFont = state.fonts.find(f => f.family === state.textSettings.fontFamily);
  if (currentFont) {
    updateCustomSelectText(fontSelector, currentFont.label);
    fontSelector.dataset.value = currentFont.family;
  }

  // Technique
  const currentTech = state.techniques.find(t => t.value === state.currentTechnique);
  if (currentTech) {
    const priceLabel = currentTech.price > 0 ? ` (+${formatPrice(currentTech.price)})` : '';
    updateCustomSelectText(techniqueSelector, currentTech.label + priceLabel);
    techniqueSelector.dataset.value = currentTech.value;
  }

  // Alignment
  alignBtns.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.align === state.textSettings.align);
  });
}

// ============================================
// ADD TO CART
// ============================================
function initAddToCart() {
  if (!els.btnAddToCart) return;

  els.btnAddToCart.addEventListener('click', async () => {
    if (state.layers.length === 0) {
      alert('Ajoutez au moins un élément (texte, design ou forme) avant de continuer.');
      return;
    }

    // Construire le payload selon la spécification
    const payload = {
      product_id: state.productId,
      color_id: state.currentColorId || 0,
      size: null, // À implémenter si sélecteur de taille ajouté
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
  // Initialiser le bottom sheet en premier
  initBottomSheet();

  // Charger les données depuis l'API (produit + assets en parallèle)
  const [productLoaded, assetsLoaded] = await Promise.all([
    loadProductData(),
    loadAssetsData()
  ]);

  if (!productLoaded) {
    return;
  }

  // Render UI depuis les données API
  renderProductInfo();
  renderTechniques();
  renderFonts();
  renderPrintZone();

  // Initialiser les interactions
  initTabs();
  initDesignsGrid();
  initElementsGrid();
  initTextControls();
  initTechnique();
  initPreview();
  initAddToCart();

  // Initialiser le prix
  updatePrice();
  updateLayersList();
}

// Start
document.addEventListener('DOMContentLoaded', init);
