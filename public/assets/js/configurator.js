/**
 * PERSONNALY - Configurateur de personnalisation produit (HTML/CSS/JS pur)
 * Version: 3.0 (REFONTE TOTALE - Sans Konva)
 * Date: 2026-01-20
 *
 * Architecture:
 * - Image produit en CSS background-image
 * - Textes/éléments = <div> draggables avec interact.js
 * - Même comportement desktop + mobile
 * - Aucune dépendance canvas/Konva
 */

(function() {
    'use strict';

    // ===========================================
    // STATE
    // ===========================================
    const state = {
        layers: [],
        selectedLayer: null,
        currentView: 'front',
        selectedProductColor: 'original',
        selectedSize: 'M',
        selectedTechnique: null,
        basePrice: 0,
    };

    let DOM = {};

    // ===========================================
    // INITIALIZATION
    // ===========================================
    function init() {
        console.log('[Configurator] Starting init (HTML mode)...');

        // Cache DOM
        cacheDOM();

        // Load product data
        state.basePrice = window.__PRODUCT_DATA?.basePrice || 0;
        state.selectedTechnique = window.__SELECTED_TECHNIQUE || 'flex';

        // Setup event listeners
        setupEventListeners();

        // Initialize price
        updatePrice();

        // Load initial product image
        updateProductImage();

        console.log('[Configurator] ✅ Initialized (HTML mode)');
    }

    function cacheDOM() {
        DOM = {
            // Product
            product: document.getElementById('product'),
            layers: document.getElementById('layers'),

            // Controls
            textInput: document.getElementById('cfgTextInput'),
            addTextBtn: document.getElementById('cfgAddText'),

            // Technique
            techniqueDropdown: document.getElementById('cfgTechniqueDropdown'),
            techniqueTrigger: document.getElementById('cfgTechniqueTrigger'),
            techniqueList: document.getElementById('cfgTechniqueList'),
            techniquePreview: document.getElementById('cfgTechniquePreview'),
            techniquePriceBadge: document.getElementById('cfgTechniquePriceBadge'),

            // Product colors
            productColors: document.querySelectorAll('.cfg-product-color-swatch'),

            // Size
            sizeButtons: document.querySelectorAll('.cfg-size-btn'),

            // Price
            priceValue: document.querySelector('.cfg-price-value'),
            mobilePriceValue: document.querySelector('.cfg-mobile-price-value'),
            productPrice: document.getElementById('cfgProductPrice'),
            mobilePrice: document.getElementById('cfgMobilePrice'),

            // View toggle
            viewBtns: document.querySelectorAll('.cfg-view-btn'),

            // Mobile
            mobileTabs: document.querySelectorAll('.cfg-mobile-tab'),
            mobileDrawer: document.getElementById('cfgMobileDrawer'),
            mobileDrawerTitle: document.getElementById('cfgMobileDrawerTitle'),
            mobileDrawerContent: document.getElementById('cfgMobileDrawerContent'),
            mobileDrawerClose: document.getElementById('cfgMobileDrawerClose'),
        };
    }

    // ===========================================
    // PRODUCT IMAGE (CSS BACKGROUND)
    // ===========================================
    function updateProductImage() {
        if (!DOM.product) {
            console.error('[Configurator] #product element not found');
            return;
        }

        // PRIORITÉ: Lire les data-attributes directement
        const frontUrl = DOM.product.dataset.front;
        const backUrl = DOM.product.dataset.back;

        let imageUrl;

        // Gestion changement couleur
        const colorImages = window.__COLOR_IMAGES || {};

        if (state.selectedProductColor === 'original') {
            imageUrl = state.currentView === 'front' ? frontUrl : backUrl;
        } else {
            const colorData = colorImages[state.selectedProductColor];
            if (colorData) {
                imageUrl = state.currentView === 'front'
                    ? colorData.front
                    : colorData.back;
            } else {
                // Fallback vers image originale
                imageUrl = state.currentView === 'front' ? frontUrl : backUrl;
            }
        }

        if (imageUrl) {
            DOM.product.style.backgroundImage = `url('${imageUrl}')`;
            console.log('[Configurator] Image loaded:', imageUrl);
        } else {
            console.warn('[Configurator] No image URL found');
        }
    }

    // ===========================================
    // TEXT LAYERS (HTML DRAGGABLE)
    // ===========================================
    function addTextLayer(text) {
        if (!text || !text.trim()) {
            showNotification('Veuillez saisir un texte', 'error');
            return;
        }

        const layerId = 'layer-' + Date.now();

        const el = document.createElement('div');
        el.className = 'layer text-layer';
        el.id = layerId;
        el.textContent = text;
        el.style.position = 'absolute';
        el.style.left = '50%';
        el.style.top = '40%';
        el.style.transform = 'translate(-50%, -50%)';
        el.style.cursor = 'move';
        el.style.userSelect = 'none';
        el.style.fontSize = '24px';
        el.style.fontWeight = 'bold';
        el.style.color = '#1A1A2E';
        el.style.padding = '4px 8px';
        el.dataset.x = 0;
        el.dataset.y = 0;

        if (DOM.layers) {
            DOM.layers.appendChild(el);
        }

        // Store in state
        state.layers.push({
            id: layerId,
            type: 'text',
            element: el,
            text: text,
            x: 50,
            y: 40,
        });

        // Make draggable
        makeDraggable(el);

        // Clear input
        if (DOM.textInput) {
            DOM.textInput.value = '';
        }

        showNotification('Texte ajouté !', 'success');
    }

    // ===========================================
    // DRAG & DROP (INTERACT.JS)
    // ===========================================
    function makeDraggable(element) {
        // Check if interact.js is loaded
        if (typeof interact === 'undefined') {
            console.warn('[Configurator] interact.js not loaded, using basic drag');
            makeBasicDraggable(element);
            return;
        }

        interact(element).draggable({
            listeners: {
                move(event) {
                    const target = event.target;
                    const x = (parseFloat(target.dataset.x) || 0) + event.dx;
                    const y = (parseFloat(target.dataset.y) || 0) + event.dy;

                    target.style.transform = `translate(${x}px, ${y}px)`;
                    target.dataset.x = x;
                    target.dataset.y = y;
                }
            },
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ]
        });
    }

    // Fallback basic drag (si interact.js pas chargé)
    function makeBasicDraggable(element) {
        let isDragging = false;
        let startX, startY, initialX, initialY;

        element.addEventListener('mousedown', startDrag);
        element.addEventListener('touchstart', startDrag);

        function startDrag(e) {
            isDragging = true;
            const touch = e.type === 'touchstart' ? e.touches[0] : e;
            startX = touch.clientX;
            startY = touch.clientY;
            initialX = parseFloat(element.dataset.x) || 0;
            initialY = parseFloat(element.dataset.y) || 0;

            document.addEventListener('mousemove', drag);
            document.addEventListener('touchmove', drag);
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);

            e.preventDefault();
        }

        function drag(e) {
            if (!isDragging) return;
            const touch = e.type === 'touchmove' ? e.touches[0] : e;
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            const x = initialX + dx;
            const y = initialY + dy;

            element.style.transform = `translate(${x}px, ${y}px)`;
            element.dataset.x = x;
            element.dataset.y = y;
        }

        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);
        }
    }

    // ===========================================
    // PRICE UPDATE (UNIFIED)
    // ===========================================
    function updatePrice() {
        let total = state.basePrice;

        // Add technique price
        const techniques = window.__TECHNIQUES_DATA || [];
        const technique = techniques.find(t => t.value === state.selectedTechnique);
        if (technique && technique.price) {
            total += technique.price;
        }

        // Add layers price (if paid elements)
        state.layers.forEach(layer => {
            if (layer.price) {
                total += layer.price;
            }
        });

        const formatted = formatPrice(total);

        // Update all price displays
        if (DOM.priceValue) DOM.priceValue.textContent = formatted;
        if (DOM.mobilePriceValue) DOM.mobilePriceValue.textContent = formatted;
        if (DOM.productPrice) DOM.productPrice.textContent = formatted;
        if (DOM.mobilePrice) DOM.mobilePrice.textContent = formatted;
    }

    function formatPrice(price) {
        return price.toFixed(2).replace('.', ',') + ' €';
    }

    // ===========================================
    // EVENT LISTENERS
    // ===========================================
    function setupEventListeners() {
        // Add text button
        DOM.addTextBtn?.addEventListener('click', () => {
            const text = DOM.textInput?.value.trim();
            if (text) {
                addTextLayer(text);
            }
        });

        // Text input - Enter key
        DOM.textInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const text = DOM.textInput.value.trim();
                if (text) {
                    addTextLayer(text);
                }
            }
        });

        // Product colors
        DOM.productColors?.forEach(colorEl => {
            colorEl.addEventListener('click', () => {
                DOM.productColors.forEach(c => c.classList.remove('selected'));
                colorEl.classList.add('selected');

                const colorName = colorEl.dataset.color;
                state.selectedProductColor = colorName;
                updateProductImage();
            });
        });

        // Size buttons
        DOM.sizeButtons?.forEach(btn => {
            btn.addEventListener('click', () => {
                DOM.sizeButtons.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                const radio = btn.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    state.selectedSize = radio.value;
                }
            });
        });

        // View toggle (Face/Dos)
        DOM.viewBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                if (!view) return;

                // Update active state
                DOM.viewBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Update state and image
                state.currentView = view;
                updateProductImage();
            });
        });

        // Technique dropdown
        DOM.techniqueList?.querySelectorAll('.cfg-dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                const technique = item.dataset.technique;
                const label = item.dataset.label;
                const price = parseFloat(item.dataset.price) || 0;

                // Update preview
                if (DOM.techniquePreview) DOM.techniquePreview.textContent = label;
                if (DOM.techniquePriceBadge) {
                    DOM.techniquePriceBadge.textContent = price > 0
                        ? `+${price.toFixed(2).replace('.', ',')} €`
                        : 'Inclus';
                }

                // Update selection
                DOM.techniqueList.querySelectorAll('.cfg-dropdown-item').forEach(i =>
                    i.classList.remove('selected')
                );
                item.classList.add('selected');

                // Close dropdown
                DOM.techniqueTrigger?.classList.remove('open');
                DOM.techniqueList?.classList.remove('open');

                // Store and update price
                state.selectedTechnique = technique;
                window.__SELECTED_TECHNIQUE = technique;
                updatePrice();
            });
        });

        // Technique trigger
        DOM.techniqueTrigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            DOM.techniqueTrigger.classList.toggle('open');
            DOM.techniqueList?.classList.toggle('open');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.cfg-modern-dropdown')) {
                DOM.techniqueTrigger?.classList.remove('open');
                DOM.techniqueList?.classList.remove('open');
            }
        });

        // Mobile tabs
        DOM.mobileTabs?.forEach(tab => {
            tab.addEventListener('click', () => {
                const tool = tab.dataset.tool;

                // Update active state
                DOM.mobileTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Open drawer with tool content
                openMobileDrawer(tool);
            });
        });

        // Mobile drawer close button
        DOM.mobileDrawerClose?.addEventListener('click', () => {
            closeMobileDrawer();
        });
    }

    // ===========================================
    // MOBILE DRAWER
    // ===========================================
    function openMobileDrawer(tool) {
        if (!DOM.mobileDrawer) return;

        // Set drawer title
        const titles = {
            text: 'Texte',
            design: 'Design',
            layers: 'Calques',
            elements: 'Éléments',
        };

        if (DOM.mobileDrawerTitle) {
            DOM.mobileDrawerTitle.textContent = titles[tool] || 'Options';
        }

        // Populate drawer content based on tool
        if (DOM.mobileDrawerContent) {
            let content = '';

            switch (tool) {
                case 'text':
                    content = `
                        <div style="padding: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Ajouter du texte</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text"
                                       id="cfgMobileTextInput"
                                       placeholder="Votre texte..."
                                       style="flex: 1; padding: 10px; border: 1px solid #e5e5e5; border-radius: 6px; font-size: 14px;">
                                <button type="button"
                                        id="cfgMobileAddText"
                                        style="padding: 10px 20px; background: #FF69B4; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
                                    Ajouter
                                </button>
                            </div>
                        </div>
                    `;
                    break;

                case 'design':
                    content = '<div style="padding: 16px; text-align: center; color: #666;">Options de design à venir</div>';
                    break;

                case 'layers':
                    content = '<div style="padding: 16px; text-align: center; color: #666;">Gestion des calques à venir</div>';
                    break;

                case 'elements':
                    content = '<div style="padding: 16px; text-align: center; color: #666;">Éléments à venir</div>';
                    break;

                default:
                    content = '<div style="padding: 16px; text-align: center; color: #666;">Outil non disponible</div>';
            }

            DOM.mobileDrawerContent.innerHTML = content;

            // If text tool, add event listener to the add button
            if (tool === 'text') {
                setTimeout(() => {
                    const mobileTextInput = document.getElementById('cfgMobileTextInput');
                    const mobileAddTextBtn = document.getElementById('cfgMobileAddText');

                    mobileAddTextBtn?.addEventListener('click', () => {
                        const text = mobileTextInput?.value.trim();
                        if (text) {
                            addTextLayer(text);
                            mobileTextInput.value = '';
                            closeMobileDrawer();
                        }
                    });

                    mobileTextInput?.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            const text = mobileTextInput.value.trim();
                            if (text) {
                                addTextLayer(text);
                                mobileTextInput.value = '';
                                closeMobileDrawer();
                            }
                        }
                    });
                }, 0);
            }
        }

        // Show drawer
        DOM.mobileDrawer.classList.add('open');
    }

    function closeMobileDrawer() {
        if (!DOM.mobileDrawer) return;
        DOM.mobileDrawer.classList.remove('open');
    }

    // ===========================================
    // NOTIFICATIONS
    // ===========================================
    function showNotification(message, type = 'info') {
        // Simple notification (peut être amélioré)
        console.log(`[Notification ${type}]:`, message);

        // Create toast if needed
        const toast = document.createElement('div');
        toast.className = `cfg-toast cfg-toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'error' ? '#ff4444' : '#3dffc0'};
            color: ${type === 'error' ? 'white' : '#1a8a6a'};
            padding: 12px 24px;
            border-radius: 8px;
            z-index: 9999;
            animation: slideUp 0.3s ease;
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // ===========================================
    // AUTO-INIT
    // ===========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ===========================================
    // EXPOSE API
    // ===========================================
    window.PersonnalyConfigurator = {
        init,
        addTextLayer,
        updatePrice,
        updateProductImage,
    };

})();
