// STATE CENTRAL
const state = {
  view: 'front',
  product: {
    color: 'white',
    size: 'M'
  },
  layers: [],
  activeLayerId: null,
  price: {
    base: 20,
    technique: 0,
    total: 20
  }
};

// CONSTANTS
const TECHNIQUE_PRICE_PER_LAYER = 5;

// ELEMENTS
const elements = {
  printArea: document.getElementById('printArea'),
  btnAddText: document.getElementById('btnAddText'),
  textControls: document.getElementById('textControls'),
  textInput: document.getElementById('textInput'),
  fontFamily: document.getElementById('fontFamily'),
  textColor: document.getElementById('textColor'),
  priceBase: document.getElementById('priceBase'),
  priceTechnique: document.getElementById('priceTechnique'),
  priceTotal: document.getElementById('priceTotal')
};

// UTILS
function generateId() {
  return 'layer_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

function formatPrice(price) {
  return price.toFixed(2).replace('.', ',') + ' €';
}

// LAYERS
function createLayer(type, data) {
  const layer = {
    id: generateId(),
    type: type,
    x: 50,
    y: 50,
    ...data
  };

  state.layers.push(layer);
  renderLayer(layer);
  setActiveLayer(layer.id);
  updatePrice();

  return layer;
}

function renderLayer(layer) {
  const div = document.createElement('div');
  div.className = 'ps-layer';
  div.dataset.layerId = layer.id;
  div.style.left = layer.x + '%';
  div.style.top = layer.y + '%';

  if (layer.type === 'text') {
    div.textContent = layer.text;
    div.style.fontFamily = layer.fontFamily;
    div.style.color = layer.color;
    div.style.fontSize = '18px';
  }

  elements.printArea.appendChild(div);

  // Drag & drop
  interact(div)
    .draggable({
      listeners: {
        move(event) {
          const layer = state.layers.find(l => l.id === event.target.dataset.layerId);
          if (!layer) return;

          // Calcul relatif à la print-area
          const printArea = elements.printArea.getBoundingClientRect();
          const deltaX = (event.dx / printArea.width) * 100;
          const deltaY = (event.dy / printArea.height) * 100;

          layer.x += deltaX;
          layer.y += deltaY;

          event.target.style.left = layer.x + '%';
          event.target.style.top = layer.y + '%';
        }
      }
    })
    .on('tap', (event) => {
      setActiveLayer(event.target.dataset.layerId);
    });
}

function setActiveLayer(layerId) {
  state.activeLayerId = layerId;

  // UI update
  document.querySelectorAll('.ps-layer').forEach(el => {
    el.classList.toggle('active', el.dataset.layerId === layerId);
  });

  // Load layer data into controls
  const layer = state.layers.find(l => l.id === layerId);
  if (layer && layer.type === 'text') {
    elements.textControls.style.display = 'flex';
    elements.textInput.value = layer.text;
    elements.fontFamily.value = layer.fontFamily;
    elements.textColor.value = layer.color;
  }
}

function updateActiveLayer(updates) {
  const layer = state.layers.find(l => l.id === state.activeLayerId);
  if (!layer) return;

  Object.assign(layer, updates);

  const div = document.querySelector(`[data-layer-id="${layer.id}"]`);
  if (div && layer.type === 'text') {
    div.textContent = layer.text;
    div.style.fontFamily = layer.fontFamily;
    div.style.color = layer.color;
  }
}

// PRICE
function updatePrice() {
  const techniqueCount = state.layers.length;
  state.price.technique = techniqueCount * TECHNIQUE_PRICE_PER_LAYER;
  state.price.total = state.price.base + state.price.technique;

  elements.priceBase.textContent = formatPrice(state.price.base);
  elements.priceTechnique.textContent = formatPrice(state.price.technique);
  elements.priceTotal.textContent = formatPrice(state.price.total);
}

// EVENT HANDLERS
elements.btnAddText.addEventListener('click', () => {
  createLayer('text', {
    text: 'Votre texte',
    fontFamily: 'Arial',
    color: '#000000'
  });
});

elements.textInput.addEventListener('input', (e) => {
  updateActiveLayer({ text: e.target.value });
});

elements.fontFamily.addEventListener('change', (e) => {
  updateActiveLayer({ fontFamily: e.target.value });
});

elements.textColor.addEventListener('input', (e) => {
  updateActiveLayer({ color: e.target.value });
});

// INIT
updatePrice();
