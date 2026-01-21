/**
 * PERSONNALY - Configurateur de personnalisation produit (HTML/CSS/JS pur)
 * Version: 3.0 (REFONTE - Sans Konva, Avec toutes fonctionnalités)
 * Date: 2026-01-21
 *
 * Architecture:
 * - Image produit en CSS background-image
 * - Textes/éléments = <div> draggables avec interact.js
 * - Même comportement desktop + mobile
 * - Aucune dépendance canvas/Konva
 *
 * Features complètes:
 * - Onglets: Texte (polices, techniques, couleurs), Design (couleur produit, tailles), Calques
 * - Drag & drop tactile
 * - Changement couleur produit
 * - Calcul prix avec techniques
 */

(function() {
    'use strict';

    // ===========================================
    // CONFIGURATION
    // ===========================================
    const CONFIG = {
        maxElements: 10,
        snapThreshold: 5,
        snapEnabled: true,
        autoSaveKey: 'personnaly_design_draft',
        autoSaveInterval: 5000,
    };

    // ===========================================
    // FONT LOADING
    // ===========================================
    const loadedFonts = new Set(['Inter', 'Arial', 'Helvetica', 'sans-serif', 'serif']);

    function loadGoogleFont(fontFamily) {
        if (loadedFonts.has(fontFamily)) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const familyEncoded = fontFamily.replace(/\s+/g, '+');
            const url = `https://fonts.googleapis.com/css2?family=${familyEncoded}:wght@400;500;600;700&display=swap`;

            const existingLink = document.querySelector(`link[href*="${familyEncoded}"]`);
            if (existingLink) {
                loadedFonts.add(fontFamily);
                resolve();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;

            link.onload = () => {
                console.log(`[Configurator] Font loaded: ${fontFamily}`);
                loadedFonts.add(fontFamily);
                resolve();
            };

            link.onerror = () => {
                console.warn(`[Configurator] Failed to load font: ${fontFamily}`);
                reject(new Error(`Failed to load font: ${fontFamily}`));
            };

            document.head.appendChild(link);
        });
    }

    function preloadFonts() {
        const fonts = window.__FONTS_DATA || [];
        console.log('[Configurator] Preloading fonts:', fonts.map(f => f.value || f.family));

        fonts.forEach(font => {
            const family = font.value || font.family;
            if (family && !loadedFonts.has(family)) {
                loadGoogleFont(family).catch(() => {});
            }
        });
    }

    // ===========================================
    // STATE
    // ===========================================
    const state = {
        elements: [],
        selectedElement: null,
        currentView: 'front',
        selectedProductColor: 'original',
        selectedSize: 'M',
        selectedTechnique: null,
        selectedFont: null,
        selectedTextColor: '#000000',
        basePrice: 0,
        nextZIndex: 1,
    };

    let DOM = {};

    // ===========================================
    // INIT
    // ===========================================
    function init() {
        console.log('[Configurator] Starting init (HTML mode)...');

        // Cache DOM
        cacheDOM();

        // Preload fonts
        preloadFonts();

        // Load product data
        state.basePrice = window.__PRODUCT_DATA?.basePrice || 0;
        state.selectedTechnique = window.__SELECTED_TECHNIQUE || 'flex';

        // Setup event listeners
        setupEventListeners();

        // Load initial image
        updateProductImage();

        // Initial price
        updatePrice();

        console.log('[Configurator] Init complete');
    }

    function cacheDOM() {
        DOM = {
            // Product
            product: document.getElementById('product'),
            layers: document.getElementById('layers'),

            // Controls
            textInput: document.getElementById('cfgTextInput'),
            addTextBtn: document.getElementById('cfgAddText'),

            // Font dropdown
            fontDropdown: document.getElementById('cfgFontDropdown'),
            fontTrigger: document.getElementById('cfgFontTrigger'),
            fontList: document.getElementById('cfgFontList'),
            fontPreview: document.getElementById('cfgFontPreview'),

            // Technique dropdown
            techniqueDropdown: document.getElementById('cfgTechniqueDropdown'),
            techniqueTrigger: document.getElementById('cfgTechniqueTrigger'),
            techniqueList: document.getElementById('cfgTechniqueList'),
            techniquePreview: document.getElementById('cfgTechniquePreview'),
            techniquePriceBadge: document.getElementById('cfgTechniquePriceBadge'),

            // Color
            textColorInput: document.getElementById('cfgTextColor'),
            colorPreviewsContainer: document.getElementById('cfgColorPreviews'),

            // Product colors
            productColors: document.querySelectorAll('.cfg-product-color-swatch'),

            // Size
            sizeButtons: document.querySelectorAll('.cfg-size-btn'),

            // View toggle
            viewBtns: document.querySelectorAll('.cfg-view-btn'),

            // Price
            priceValue: document.querySelector('.cfg-price-value'),
            mobilePriceValue: document.querySelector('.cfg-mobile-price-value'),
            productPrice: document.getElementById('cfgProductPrice'),
            mobilePrice: document.getElementById('cfgMobilePrice'),

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

        const frontUrl = DOM.product.dataset.front;
        const backUrl = DOM.product.dataset.back;

        let imageUrl;

        // Color change handling
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

        if (state.elements.length >= CONFIG.maxElements) {
            showNotification(`Maximum ${CONFIG.maxElements} éléments atteints`, 'error');
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
        el.style.color = state.selectedTextColor || '#1A1A2E';
        el.style.padding = '4px 8px';
        el.style.zIndex = state.nextZIndex++;
        el.dataset.x = 0;
        el.dataset.y = 0;

        // Apply selected font
        if (state.selectedFont) {
            el.style.fontFamily = state.selectedFont;
        }

        if (DOM.layers) {
            DOM.layers.appendChild(el);
        }

        // Store in state
        state.elements.push({
            id: layerId,
            type: 'text',
            element: el,
            text: text,
            x: 50,
            y: 40,
            font: state.selectedFont,
            color: state.selectedTextColor,
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

        // Add elements price (if paid)
        state.elements.forEach(el => {
            if (el.price) {
                total += el.price;
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
        // Add text button (desktop)
        DOM.addTextBtn?.addEventListener('click', () => {
            const text = DOM.textInput?.value.trim();
            if (text) {
                addTextLayer(text);
            }
        });

        DOM.textInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const text = DOM.textInput.value.trim();
                if (text) {
                    addTextLayer(text);
                }
            }
        });

        // Font dropdown
        DOM.fontList?.querySelectorAll('.cfg-dropdown-item').forEach(item => {
            item.addEventListener('click', async () => {
                const fontFamily = item.dataset.font;
                const label = item.dataset.label;

                // Load font
                try {
                    await loadGoogleFont(fontFamily);
                } catch (e) {
                    console.warn('Font load failed:', e);
                }

                // Update preview
                if (DOM.fontPreview) {
                    DOM.fontPreview.textContent = label;
                    DOM.fontPreview.style.fontFamily = fontFamily;
                }

                // Update selection
                DOM.fontList.querySelectorAll('.cfg-dropdown-item').forEach(i =>
                    i.classList.remove('selected')
                );
                item.classList.add('selected');

                // Close dropdown
                DOM.fontTrigger?.classList.remove('open');
                DOM.fontList?.classList.remove('open');

                // Store font
                state.selectedFont = fontFamily;
            });
        });

        DOM.fontTrigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            DOM.fontTrigger.classList.toggle('open');
            DOM.fontList?.classList.toggle('open');
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

        DOM.techniqueTrigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            DOM.techniqueTrigger.classList.toggle('open');
            DOM.techniqueList?.classList.toggle('open');
        });

        // Text color input
        DOM.textColorInput?.addEventListener('input', (e) => {
            state.selectedTextColor = e.target.value;
        });

        // Product color swatches
        DOM.productColors?.forEach(colorEl => {
            colorEl.addEventListener('click', () => {
                const colorName = colorEl.dataset.color;

                // Update selection
                DOM.productColors.forEach(c => c.classList.remove('selected'));
                colorEl.classList.add('selected');

                // Update state and image
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

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.cfg-modern-dropdown')) {
                DOM.fontTrigger?.classList.remove('open');
                DOM.fontList?.classList.remove('open');
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

                // Open drawer
                openMobileDrawer(tool);
            });
        });

        // Mobile drawer close
        DOM.mobileDrawerClose?.addEventListener('click', () => {
            closeMobileDrawer();
        });
    }

    // ===========================================
    // MOBILE DRAWER
    // ===========================================
    function openMobileDrawer(tool) {
        if (!DOM.mobileDrawer) return;

        const titles = {
            text: 'Texte',
            design: 'Design',
            layers: 'Calques',
            elements: 'Éléments',
        };

        if (DOM.mobileDrawerTitle) {
            DOM.mobileDrawerTitle.textContent = titles[tool] || 'Options';
        }

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

            // If text tool, add event listeners
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
        console.log(`[Notification ${type}]:`, message);
    }

    // ===========================================
    // START
    // ===========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
