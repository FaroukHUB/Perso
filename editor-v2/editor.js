/**
 * PERSONNALY - Editor V2 JavaScript
 * State-driven, mobile-first, no canvas
 */

// ============================================
// STATE CENTRAL
// ============================================
const state = {
  productId: 1,
  productName: 'T-Shirt Classic',
  view: 'front',
  layers: [],
  activeLayerId: null,
  price: {
    base: 29.90,
    technique: 0,
    total: 29.90
  },
  technique: 'dtg',
  textSettings: {
    text: '',
    fontFamily: 'Inter',
    fontSize: 24,
    color: '#000000',
    align: 'left'
  }
};

// ============================================
// MOCK DATA
// ============================================
const DESIGNS = [
  { id: 'd1', name: 'Coeur', svg: '<svg viewBox="0 0 24 24" fill="#FF69B4"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' },
  { id: 'd2', name: 'Etoile', svg: '<svg viewBox="0 0 24 24" fill="#FFD700"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>' },
  { id: 'd3', name: 'Eclair', svg: '<svg viewBox="0 0 24 24" fill="#3DFFC0"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>' },
  { id: 'd4', name: 'Flamme', svg: '<svg viewBox="0 0 24 24" fill="#FF6B35"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z"/></svg>' },
  { id: 'd5', name: 'Papillon', svg: '<svg viewBox="0 0 24 24" fill="#9B59B6"><path d="M12 2C9.5 2 7.5 4 7.5 6.5c0 1.5.7 2.8 1.8 3.6-.8.4-1.5 1-2 1.7-1.5-1.5-3.8-2.3-6.3-2.3 0 4.5 3 8 7 9v1.5c0 1.1.9 2 2 2s2-.9 2-2v-1.5c4-1 7-4.5 7-9-2.5 0-4.8.8-6.3 2.3-.5-.7-1.2-1.3-2-1.7 1.1-.8 1.8-2.1 1.8-3.6C16.5 4 14.5 2 12 2z"/></svg>' },
  { id: 'd6', name: 'Licorne', svg: '<svg viewBox="0 0 24 24" fill="#FF69B4"><path d="M19 3l-4 5h3l-5 7 2-4h-3l4-8M5 21v-2h14v2H5m2.5-4c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5m4 0c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5m4 0c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5z"/></svg>' }
];

const SHAPES = [
  { id: 's1', name: 'Cercle', svg: '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="#FF69B4"/></svg>' },
  { id: 's2', name: 'Carré', svg: '<svg viewBox="0 0 100 100"><rect x="5" y="5" width="90" height="90" fill="#3DFFC0"/></svg>' },
  { id: 's3', name: 'Triangle', svg: '<svg viewBox="0 0 100 100"><polygon points="50,5 95,95 5,95" fill="#FFD700"/></svg>' },
  { id: 's4', name: 'Losange', svg: '<svg viewBox="0 0 100 100"><polygon points="50,5 95,50 50,95 5,50" fill="#9B59B6"/></svg>' },
  { id: 's5', name: 'Hexagone', svg: '<svg viewBox="0 0 100 100"><polygon points="50,3 93,25 93,75 50,97 7,75 7,25" fill="#FF6B35"/></svg>' },
  { id: 's6', name: 'Etoile', svg: '<svg viewBox="0 0 100 100"><polygon points="50,5 61,40 98,40 68,62 79,97 50,75 21,97 32,62 2,40 39,40" fill="#1A1A2E"/></svg>' }
];

const TECHNIQUES = {
  dtg: { name: 'DTG', price: 0 },
  broderie: { name: 'Broderie', price: 5 },
  flex: { name: 'Flex', price: 3 },
  serigraphie: { name: 'Sérigraphie', price: 2 }
};

// ============================================
// DOM ELEMENTS
// ============================================
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const els = {
  editor: $('#editor'),
  printArea: $('#printArea'),
  productImage: $('#productImage'),
  productTitle: $('#productTitle'),

  // Tabs
  tabs: $$('.ps-tab'),
  panels: $$('.ps-panel'),

  // Text controls
  textInput: $('#textInput'),
  fontFamily: $('#fontFamily'),
  fontSize: $('#fontSize'),
  textColor: $('#textColor'),
  alignBtns: $$('.ps-align-btn'),
  btnAddText: $('#btnAddText'),

  // Technique
  technique: $('#technique'),

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

function getProductData() {
  // Récupère les données injectées par PHP si disponibles
  if (window.__PRODUCT_DATA_V2) {
    return window.__PRODUCT_DATA_V2;
  }
  return null;
}

// ============================================
// TABS
// ============================================
function initTabs() {
  els.tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const tabId = tab.dataset.tab;

      // Update tabs
      els.tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      // Update panels
      els.panels.forEach(p => p.classList.remove('active'));
      $(`#panel-${tabId}`).classList.add('active');
    });
  });
}

// ============================================
// DESIGNS GRID
// ============================================
function initDesignsGrid() {
  els.designsGrid.innerHTML = '';

  DESIGNS.forEach(design => {
    const item = document.createElement('div');
    item.className = 'ps-grid-item';
    item.innerHTML = design.svg;
    item.title = design.name;
    item.addEventListener('click', () => addDesignLayer(design));
    els.designsGrid.appendChild(item);
  });
}

function addDesignLayer(design) {
  const layer = {
    id: generateId(),
    type: 'design',
    name: design.name,
    svg: design.svg,
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
// ELEMENTS/SHAPES GRID
// ============================================
function initElementsGrid() {
  els.elementsGrid.innerHTML = '';

  SHAPES.forEach(shape => {
    const item = document.createElement('div');
    item.className = 'ps-grid-item';
    item.innerHTML = shape.svg;
    item.title = shape.name;
    item.addEventListener('click', () => addShapeLayer(shape));
    els.elementsGrid.appendChild(item);
  });
}

function addShapeLayer(shape) {
  const layer = {
    id: generateId(),
    type: 'shape',
    name: shape.name,
    svg: shape.svg,
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
  // Update text settings on input
  els.textInput.addEventListener('input', (e) => {
    state.textSettings.text = e.target.value;
    updateActiveTextLayer();
  });

  els.fontFamily.addEventListener('change', (e) => {
    state.textSettings.fontFamily = e.target.value;
    updateActiveTextLayer();
  });

  els.fontSize.addEventListener('input', (e) => {
    state.textSettings.fontSize = parseInt(e.target.value);
    updateActiveTextLayer();
  });

  els.textColor.addEventListener('input', (e) => {
    state.textSettings.color = e.target.value;
    updateActiveTextLayer();
  });

  // Alignment
  els.alignBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      els.alignBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.textSettings.align = btn.dataset.align;
      updateActiveTextLayer();
    });
  });

  // Add text button
  els.btnAddText.addEventListener('click', addTextLayer);
}

function addTextLayer() {
  const text = state.textSettings.text || 'Votre texte';

  const layer = {
    id: generateId(),
    type: 'text',
    name: text.substring(0, 15) + (text.length > 15 ? '...' : ''),
    text: text,
    fontFamily: state.textSettings.fontFamily,
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
  els.textInput.value = '';
  state.textSettings.text = '';
}

function updateActiveTextLayer() {
  const layer = state.layers.find(l => l.id === state.activeLayerId);
  if (!layer || layer.type !== 'text') return;

  layer.text = state.textSettings.text || layer.text;
  layer.fontFamily = state.textSettings.fontFamily;
  layer.fontSize = state.textSettings.fontSize;
  layer.color = state.textSettings.color;
  layer.align = state.textSettings.align;
  layer.name = layer.text.substring(0, 15) + (layer.text.length > 15 ? '...' : '');

  // Update DOM
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
  } else if (layer.type === 'design' || layer.type === 'shape') {
    div.classList.add('ps-layer-' + layer.type);
    div.innerHTML = layer.svg;
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

  // Update layer DOM
  $$('.ps-layer').forEach(el => {
    el.classList.toggle('active', el.dataset.layerId === layerId);
  });

  // Update layer list
  $$('.ps-layer-item').forEach(el => {
    el.classList.toggle('active', el.dataset.layerId === layerId);
  });

  // Load text settings if text layer
  const layer = state.layers.find(l => l.id === layerId);
  if (layer && layer.type === 'text') {
    els.textInput.value = layer.text;
    els.fontFamily.value = layer.fontFamily;
    els.fontSize.value = layer.fontSize;
    els.textColor.value = layer.color;

    els.alignBtns.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.align === layer.align);
    });

    state.textSettings = {
      text: layer.text,
      fontFamily: layer.fontFamily,
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

  // Remove from DOM
  const div = $(`[data-layer-id="${layerId}"]`);
  if (div) div.remove();

  // Clear active if was active
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
  // Reorder DOM elements based on state
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
  els.layersEmpty.style.display = hasLayers ? 'none' : 'block';
  els.layersList.innerHTML = '';

  // Reverse order (top layer first in UI)
  [...state.layers].reverse().forEach(layer => {
    const item = document.createElement('div');
    item.className = 'ps-layer-item' + (layer.id === state.activeLayerId ? ' active' : '');
    item.dataset.layerId = layer.id;

    const icon = layer.type === 'text' ? 'T' : (layer.type === 'design' ? '★' : '■');
    const typeLabel = layer.type === 'text' ? 'Texte' : (layer.type === 'design' ? 'Design' : 'Forme');

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

    // Click to select
    item.addEventListener('click', (e) => {
      if (e.target.closest('.ps-layer-action')) return;
      setActiveLayer(layer.id);
    });

    // Actions
    item.querySelectorAll('.ps-layer-action').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const action = btn.dataset.action;
        if (action === 'up') moveLayerUp(layer.id);
        else if (action === 'down') moveLayerDown(layer.id);
        else if (action === 'delete') removeLayer(layer.id);
      });
    });

    els.layersList.appendChild(item);
  });
}

// ============================================
// TECHNIQUE & PRICE
// ============================================
function initTechnique() {
  els.technique.addEventListener('change', (e) => {
    state.technique = e.target.value;
    updatePrice();
  });
}

function updatePrice() {
  const techniquePrice = TECHNIQUES[state.technique]?.price || 0;
  state.price.technique = techniquePrice;
  state.price.total = state.price.base + state.price.technique;

  els.priceBase.textContent = formatPrice(state.price.base);
  els.priceTechnique.textContent = formatPrice(state.price.technique);
  els.priceTotal.textContent = formatPrice(state.price.total);
  els.ctaPrice.textContent = formatPrice(state.price.total);
}

// ============================================
// PREVIEW MODE
// ============================================
function initPreview() {
  els.btnPreview.addEventListener('click', () => {
    els.editor.classList.add('preview-mode');
    els.btnClosePreview.style.display = 'block';
  });

  els.btnClosePreview.addEventListener('click', () => {
    els.editor.classList.remove('preview-mode');
    els.btnClosePreview.style.display = 'none';
  });
}

// ============================================
// ADD TO CART
// ============================================
function initAddToCart() {
  els.btnAddToCart.addEventListener('click', async () => {
    if (state.layers.length === 0) {
      alert('Ajoutez au moins un élément avant de continuer.');
      return;
    }

    const payload = {
      productId: state.productId,
      productName: state.productName,
      view: state.view,
      technique: state.technique,
      layers: state.layers.map(l => ({
        type: l.type,
        name: l.name,
        x: l.x,
        y: l.y,
        ...(l.type === 'text' ? {
          text: l.text,
          fontFamily: l.fontFamily,
          fontSize: l.fontSize,
          color: l.color,
          align: l.align
        } : {
          svg: l.svg
        })
      })),
      price: state.price.total
    };

    try {
      els.btnAddToCart.disabled = true;
      els.btnAddToCart.textContent = 'Ajout en cours...';

      const response = await fetch('/public/api/cart/add-config.php', {
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
      console.error('Cart error:', error);
      alert('Erreur de connexion. Veuillez réessayer.');
      els.btnAddToCart.disabled = false;
      els.btnAddToCart.textContent = 'Ajouter au panier';
    }
  });
}

// ============================================
// INIT
// ============================================
function init() {
  // Load product data from PHP if available
  const productData = getProductData();
  if (productData) {
    state.productId = productData.productId;
    state.productName = productData.productName;
    state.price.base = productData.basePrice;

    if (productData.imageFront) {
      els.productImage.src = productData.imageFront;
    }
    if (els.productTitle) {
      els.productTitle.textContent = productData.productName;
    }
  }

  // Initialize all modules
  initTabs();
  initDesignsGrid();
  initElementsGrid();
  initTextControls();
  initTechnique();
  initPreview();
  initAddToCart();

  // Initial price update
  updatePrice();
  updateLayersList();
}

// Start
document.addEventListener('DOMContentLoaded', init);
