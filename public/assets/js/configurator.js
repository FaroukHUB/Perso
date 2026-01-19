/**
 * PERSONNALY - Configurateur de personnalisation produit (Konva.js)
 * Version: 2.0 (DESIGN-2)
 * Date: 2026-01-18
 *
 * Features:
 * - Canvas Konva.js pour drag & drop
 * - Panneau outils avec onglets
 * - Drawer propriétés contextuel
 * - Snap magnétique soft
 * - Sérialisation JSON + localStorage
 * - Export image preview
 */

(function() {
    'use strict';

    // ===========================================
    // CONFIGURATION
    // ===========================================
    const CONFIG = {
        maxElements: 10,
        snapThreshold: 5, // px
        snapEnabled: true,
        autoSaveKey: 'personnaly_design_draft',
        autoSaveInterval: 5000, // ms
    };

    // ===========================================
    // FONT LOADING
    // ===========================================
    const loadedFonts = new Set(['Inter', 'Arial', 'Helvetica', 'sans-serif', 'serif']);

    /**
     * Load a Google Font dynamically
     * @param {string} fontFamily - The font family name
     * @returns {Promise} - Resolves when font is loaded
     */
    function loadGoogleFont(fontFamily) {
        if (loadedFonts.has(fontFamily)) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const familyEncoded = fontFamily.replace(/\s+/g, '+');
            const url = `https://fonts.googleapis.com/css2?family=${familyEncoded}:wght@400;500;600;700&display=swap`;

            // Check if already loaded
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

    /**
     * Preload all fonts from __FONTS_DATA
     */
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
        stage: null,
        layer: null,
        productImage: null,
        elements: [],
        selectedElement: null,
        currentView: 'front', // 'front' or 'back'
        currentTool: 'design', // 'design', 'photo', 'text', 'layers'
        selectedProductColor: null, // Current product color name
        isDragging: false,
        zoomLevel: 1,
        printZone: null,
        snapGuides: { horizontal: null, vertical: null },
    };

    // ===========================================
    // DOM REFERENCES
    // ===========================================
    let DOM = {};

    // ===========================================
    // INITIALIZATION
    // ===========================================
    function init() {
        console.log('[Configurator] Starting init...');

        // Check if Konva is loaded
        if (typeof Konva === 'undefined') {
            console.error('[Configurator] Konva.js NOT LOADED!');
            initFallbackMode();
            return;
        }
        console.log('[Configurator] Konva.js loaded OK');

        // Preload all fonts
        preloadFonts();

        // Cache DOM elements
        cacheDOM();
        console.log('[Configurator] DOM cached, stageContainer:', DOM.stageContainer);

        // Initialize default technique
        const firstTechniquePill = document.querySelector('.cfg-technique-pill.selected');
        if (firstTechniquePill) {
            window.__SELECTED_TECHNIQUE = firstTechniquePill.dataset.technique;
        }

        if (!DOM.stageContainer) {
            console.error('[Configurator] stageContainer NOT FOUND!');
            return;
        }

        // Initialize Konva stage
        initKonvaStage();
        console.log('[Configurator] Stage initialized:', state.stage);

        // Load product image
        loadProductImage();

        // Setup event listeners
        setupEventListeners();
        console.log('[Configurator] Event listeners setup');

        // Load draft from localStorage
        loadDraft();

        // Start autosave
        startAutoSave();

        // Initialize price display
        updatePrice();

        console.log('[Configurator] ✅ Initialized successfully');
    }

    function cacheDOM() {
        DOM = {
            configurator: document.querySelector('.configurator-v2'),
            stageContainer: document.querySelector('.cfg-canvas-stage'),
            toolTabs: document.querySelectorAll('.cfg-tab'),
            toolPanels: document.querySelectorAll('.cfg-tool-panel'),
            viewBtns: document.querySelectorAll('.cfg-view-btn'),
            drawer: document.querySelector('.cfg-drawer'),
            drawerTitle: document.querySelector('.cfg-drawer-title'),
            drawerContent: document.querySelector('.cfg-drawer-content'),
            drawerClose: document.querySelector('.cfg-drawer-close'),
            // YourSurprise style controls
            textInput: document.getElementById('cfgTextInput'),
            textCounter: document.getElementById('cfgTextCount'),
            fontSelect: document.getElementById('cfgFontSelect'),
            colorPicker: document.getElementById('cfgColorPicker'),
            colorDropdown: document.getElementById('cfgColorDropdown'),
            styleBtns: document.querySelectorAll('.cfg-style-btn'),
            alignBtns: document.querySelectorAll('.cfg-align-btn'),
            dimBtns: document.querySelectorAll('.cfg-dim-btn'),
            rotationSlider: document.getElementById('cfgRotation'),
            moveBtns: document.querySelectorAll('.cfg-move-btn'),
            layerBtns: document.querySelectorAll('.cfg-layer-btn'),
            deleteBtn: document.getElementById('cfgDeleteElement'),
            addTextBtn: document.getElementById('cfgAddText'),
            addTextMainBtn: document.getElementById('cfgAddTextMain'),
            toolsTitle: document.querySelector('.cfg-tools-title'),
            // Photo upload
            uploadZone: document.getElementById('cfgUploadZone'),
            imageUpload: document.getElementById('cfgImageUpload'),
            // Product color
            productColors: document.querySelectorAll('.cfg-product-color-swatch'),
            // Legacy
            colorSwatches: document.querySelectorAll('.cfg-color-swatch'),
            techniqueItems: document.querySelectorAll('.cfg-technique-item'),
            layersList: document.querySelector('.cfg-layers-list'),
            layersCount: document.querySelector('.cfg-layers-count'),
            zoomIn: document.querySelector('.cfg-zoom-btn[data-action="zoom-in"]'),
            zoomOut: document.querySelector('.cfg-zoom-btn[data-action="zoom-out"]'),
            zoomReset: document.querySelector('.cfg-zoom-btn[data-action="zoom-reset"]'),
            zoomLevel: document.querySelector('.cfg-zoom-level'),
            snapToggle: document.querySelector('.cfg-snap-toggle input'),
            undoBtn: document.querySelector('.cfg-action-btn[data-action="undo"]'),
            redoBtn: document.querySelector('.cfg-action-btn[data-action="redo"]'),
            saveBtn: document.getElementById('cfgSaveBtn'),
            shareBtn: document.getElementById('cfgShareBtn'),
            addCartBtn: document.querySelector('.cfg-add-cart-btn'),
            priceValue: document.querySelector('.cfg-price-value'),
            // Mobile
            mobileTabs: document.querySelectorAll('.cfg-mobile-tab'),
            mobileDrawer: document.querySelector('.cfg-mobile-drawer'),
            mobileDrawerTitle: document.querySelector('.cfg-mobile-drawer-title'),
            mobileDrawerContent: document.querySelector('.cfg-mobile-drawer-content'),
            mobileDrawerClose: document.querySelector('.cfg-mobile-drawer-close'),
            mobileCartBtn: document.querySelector('.cfg-mobile-cart-btn'),
            mobilePriceValue: document.querySelector('.cfg-mobile-price-value'),
        };
    }

    // ===========================================
    // KONVA STAGE
    // ===========================================
    function initKonvaStage() {
        if (!DOM.stageContainer) return;

        const containerWidth = DOM.stageContainer.offsetWidth;
        const containerHeight = DOM.stageContainer.offsetHeight || containerWidth;

        state.stage = new Konva.Stage({
            container: DOM.stageContainer,
            width: containerWidth,
            height: containerHeight,
        });

        state.layer = new Konva.Layer();
        state.stage.add(state.layer);

        // Handle window resize
        window.addEventListener('resize', debounce(handleResize, 250));
    }

    function handleResize() {
        if (!state.stage || !DOM.stageContainer) return;

        const containerWidth = DOM.stageContainer.offsetWidth;
        const containerHeight = DOM.stageContainer.offsetHeight || containerWidth;

        state.stage.width(containerWidth);
        state.stage.height(containerHeight);

        // Reposition elements proportionally
        repositionElements(containerWidth, containerHeight);
    }

    function repositionElements(width, height) {
        state.elements.forEach(el => {
            if (el.konvaNode) {
                // Convert percentage position to pixels
                const x = (el.position.x / 100) * width;
                const y = (el.position.y / 100) * height;
                el.konvaNode.position({ x, y });
            }
        });
        state.layer.batchDraw();
    }

    // ===========================================
    // PRODUCT IMAGE
    // ===========================================
    function loadProductImage() {
        const productData = window.__PRODUCT_DATA || {};
        const imageUrl = state.currentView === 'front'
            ? productData.imageFront
            : productData.imageBack;

        if (!imageUrl) {
            console.warn('[Configurator] No image URL for view:', state.currentView);
            return;
        }

        console.log('[Configurator] Loading product image:', imageUrl);

        // Create image element manually to handle CORS
        const imageObj = new Image();
        imageObj.crossOrigin = 'anonymous';

        imageObj.onload = function() {
            // Remove old product image
            if (state.productImage) {
                state.productImage.destroy();
            }

            const image = new Konva.Image({
                image: imageObj,
            });

            // Scale image to fit stage
            const stageWidth = state.stage.width();
            const stageHeight = state.stage.height();
            const scale = Math.min(
                (stageWidth * 0.85) / image.width(),
                (stageHeight * 0.85) / image.height()
            );

            image.setAttrs({
                x: (stageWidth - image.width() * scale) / 2,
                y: (stageHeight - image.height() * scale) / 2,
                scaleX: scale,
                scaleY: scale,
                listening: false, // Product image is not interactive
            });

            state.productImage = image;
            state.layer.add(image);
            image.moveToBottom();

            // Draw print zone
            drawPrintZone();

            state.layer.batchDraw();
            console.log('[Configurator] Product image loaded successfully');
        };

        imageObj.onerror = function(err) {
            console.error('[Configurator] Failed to load product image:', imageUrl, err);
            // Try without crossOrigin as fallback
            const fallbackImg = new Image();
            fallbackImg.onload = function() {
                if (state.productImage) {
                    state.productImage.destroy();
                }

                const image = new Konva.Image({
                    image: fallbackImg,
                });

                const stageWidth = state.stage.width();
                const stageHeight = state.stage.height();
                const scale = Math.min(
                    (stageWidth * 0.85) / image.width(),
                    (stageHeight * 0.85) / image.height()
                );

                image.setAttrs({
                    x: (stageWidth - image.width() * scale) / 2,
                    y: (stageHeight - image.height() * scale) / 2,
                    scaleX: scale,
                    scaleY: scale,
                    listening: false,
                });

                state.productImage = image;
                state.layer.add(image);
                image.moveToBottom();
                drawPrintZone();
                state.layer.batchDraw();
                console.log('[Configurator] Product image loaded via fallback');
            };
            fallbackImg.onerror = function() {
                console.error('[Configurator] Fallback also failed for:', imageUrl);
            };
            fallbackImg.src = imageUrl;
        };

        imageObj.src = imageUrl;
    }

    /**
     * Change the product image (used when switching color variants)
     */
    function changeProductImage(imageUrl, colorName) {
        if (!imageUrl) {
            console.warn('[Configurator] No image URL for color:', colorName);
            return;
        }

        console.log('[Configurator] Changing product image:', imageUrl);

        const imageObj = new Image();
        imageObj.crossOrigin = 'anonymous';

        imageObj.onload = function() {
            // Remove old product image
            if (state.productImage) {
                state.productImage.destroy();
            }

            const image = new Konva.Image({
                image: imageObj,
            });

            // Scale image to fit stage
            const stageWidth = state.stage.width();
            const stageHeight = state.stage.height();
            const scale = Math.min(
                (stageWidth * 0.85) / image.width(),
                (stageHeight * 0.85) / image.height()
            );

            image.setAttrs({
                x: (stageWidth - image.width() * scale) / 2,
                y: (stageHeight - image.height() * scale) / 2,
                scaleX: scale,
                scaleY: scale,
                listening: false,
            });

            state.productImage = image;
            state.layer.add(image);
            image.moveToBottom();

            // Redraw print zone
            drawPrintZone();

            state.layer.batchDraw();
            console.log('[Configurator] Product image changed to:', colorName);
        };

        imageObj.onerror = function(err) {
            console.error('[Configurator] Failed to load variant image:', imageUrl, err);
            // Try fallback without CORS
            const fallbackImg = new Image();
            fallbackImg.onload = function() {
                if (state.productImage) {
                    state.productImage.destroy();
                }
                const image = new Konva.Image({ image: fallbackImg });
                const stageWidth = state.stage.width();
                const stageHeight = state.stage.height();
                const scale = Math.min(
                    (stageWidth * 0.85) / image.width(),
                    (stageHeight * 0.85) / image.height()
                );
                image.setAttrs({
                    x: (stageWidth - image.width() * scale) / 2,
                    y: (stageHeight - image.height() * scale) / 2,
                    scaleX: scale,
                    scaleY: scale,
                    listening: false,
                });
                state.productImage = image;
                state.layer.add(image);
                image.moveToBottom();
                drawPrintZone();
                state.layer.batchDraw();
            };
            fallbackImg.src = imageUrl;
        };

        imageObj.src = imageUrl;
    }

    function drawPrintZone() {
        const productData = window.__PRODUCT_DATA || {};
        const zones = productData.printZones || {};
        const zoneData = zones[state.currentView];

        if (!zoneData || !state.productImage) return;

        // Remove old print zone
        if (state.printZone) {
            state.printZone.destroy();
        }

        const imgX = state.productImage.x();
        const imgY = state.productImage.y();
        const imgWidth = state.productImage.width() * state.productImage.scaleX();
        const imgHeight = state.productImage.height() * state.productImage.scaleY();

        state.printZone = new Konva.Rect({
            x: imgX + (zoneData.x / 100) * imgWidth,
            y: imgY + (zoneData.y / 100) * imgHeight,
            width: (zoneData.width / 100) * imgWidth,
            height: (zoneData.height / 100) * imgHeight,
            stroke: 'rgba(255, 105, 180, 0.4)',
            strokeWidth: 2,
            dash: [8, 4],
            listening: false,
        });

        state.layer.add(state.printZone);
        state.printZone.moveToBottom();
        if (state.productImage) {
            state.printZone.moveAbove(state.productImage);
        }
    }

    // ===========================================
    // ELEMENT MANAGEMENT
    // ===========================================
    function addTextElement(text, options = {}) {
        console.log('[Configurator] addTextElement called with:', text);
        console.log('[Configurator] state.stage:', state.stage);
        console.log('[Configurator] state.layer:', state.layer);

        if (state.elements.length >= CONFIG.maxElements) {
            showNotification('Maximum ' + CONFIG.maxElements + ' éléments atteint', 'error');
            return null;
        }

        if (!state.stage || !state.layer) {
            console.error('[Configurator] Stage or layer not initialized!');
            return null;
        }

        const stageWidth = state.stage.width();
        const stageHeight = state.stage.height();
        console.log('[Configurator] Stage size:', stageWidth, 'x', stageHeight);

        // Get values from controls if not specified in options
        const fontFamily = options.fontFamily || DOM.fontSelect?.value || 'Inter';
        const fill = options.fill || DOM.colorPicker?.dataset.hex || '#1A1A2E';

        const textNode = new Konva.Text({
            x: options.x || stageWidth / 2,
            y: options.y || stageHeight / 2,
            text: text || 'Votre texte',
            fontSize: options.fontSize || 28,
            fontFamily: fontFamily,
            fill: fill,
            draggable: true,
            offsetX: 0,
            offsetY: 0,
            align: 'center',
        });

        // Center offset
        textNode.offsetX(textNode.width() / 2);
        textNode.offsetY(textNode.height() / 2);

        // Add transformer for selection
        const transformer = new Konva.Transformer({
            nodes: [textNode],
            enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
            rotateEnabled: true,
            borderStroke: '#FF69B4',
            anchorStroke: '#FF69B4',
            anchorFill: '#FFFFFF',
            anchorSize: 10,
            visible: false,
        });

        state.layer.add(textNode);
        state.layer.add(transformer);

        const element = {
            id: generateId(),
            type: 'text',
            konvaNode: textNode,
            transformer: transformer,
            position: {
                x: (textNode.x() / stageWidth) * 100,
                y: (textNode.y() / stageHeight) * 100,
            },
            properties: {
                text: text || 'Votre texte',
                fontFamily: options.fontFamily || 'Inter',
                fontSize: options.fontSize || 24,
                fill: options.fill || '#1A1A2E',
                rotation: 0,
                opacity: 1,
            },
            view: state.currentView,
            visible: true,
        };

        state.elements.push(element);

        // Setup drag events
        setupElementEvents(element);

        // Select this element
        selectElement(element);

        // Update layers panel
        updateLayersPanel();

        state.layer.batchDraw();

        return element;
    }

    function handleImageUpload(file) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            showNotification('Format non supporté. Utilisez JPG, PNG, WebP ou GIF.', 'error');
            return;
        }

        // Validate file size (10MB max)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            showNotification('Image trop volumineuse. Maximum 10 Mo.', 'error');
            return;
        }

        // Read file and add to canvas
        const reader = new FileReader();
        reader.onload = (e) => {
            addImageElement(e.target.result);
            showNotification('Image ajoutée !', 'success');
        };
        reader.onerror = () => {
            showNotification('Erreur lors de la lecture du fichier.', 'error');
        };
        reader.readAsDataURL(file);
    }

    function addImageElement(imageUrl, options = {}) {
        if (state.elements.length >= CONFIG.maxElements) {
            showNotification('Maximum ' + CONFIG.maxElements + ' éléments atteint', 'error');
            return null;
        }

        Konva.Image.fromURL(imageUrl, (image) => {
            const stageWidth = state.stage.width();
            const stageHeight = state.stage.height();

            // Scale image
            const maxSize = Math.min(stageWidth, stageHeight) * 0.3;
            const scale = Math.min(maxSize / image.width(), maxSize / image.height());

            image.setAttrs({
                x: options.x || stageWidth / 2,
                y: options.y || stageHeight / 2,
                scaleX: scale,
                scaleY: scale,
                draggable: true,
                offsetX: image.width() / 2,
                offsetY: image.height() / 2,
            });

            const transformer = new Konva.Transformer({
                nodes: [image],
                enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
                rotateEnabled: true,
                borderStroke: '#FF69B4',
                anchorStroke: '#FF69B4',
                anchorFill: '#FFFFFF',
                anchorSize: 10,
                visible: false,
            });

            state.layer.add(image);
            state.layer.add(transformer);

            const element = {
                id: generateId(),
                type: 'image',
                konvaNode: image,
                transformer: transformer,
                position: {
                    x: (image.x() / stageWidth) * 100,
                    y: (image.y() / stageHeight) * 100,
                },
                properties: {
                    url: imageUrl,
                    rotation: 0,
                    opacity: 1,
                    scaleX: scale,
                    scaleY: scale,
                },
                view: state.currentView,
                visible: true,
            };

            state.elements.push(element);
            setupElementEvents(element);
            selectElement(element);
            updateLayersPanel();
            state.layer.batchDraw();
        });
    }

    function setupElementEvents(element) {
        const node = element.konvaNode;

        node.on('click tap', () => {
            selectElement(element);
        });

        node.on('dragstart', () => {
            state.isDragging = true;
            node.moveToTop();
            element.transformer.moveToTop();
        });

        node.on('dragmove', () => {
            // Snap to guides
            if (CONFIG.snapEnabled) {
                applySnap(element);
            }

            // Constrain to print zone
            constrainToPrintZone(element);
        });

        node.on('dragend', () => {
            state.isDragging = false;
            hideSnapGuides();

            // Update position in percentage
            const stageWidth = state.stage.width();
            const stageHeight = state.stage.height();
            element.position.x = (node.x() / stageWidth) * 100;
            element.position.y = (node.y() / stageHeight) * 100;

            // Save draft
            saveDraft();
        });

        node.on('transformend', () => {
            element.properties.rotation = node.rotation();
            element.properties.scaleX = node.scaleX();
            element.properties.scaleY = node.scaleY();
            saveDraft();
        });

        // Double-click to edit text
        if (element.type === 'text') {
            node.on('dblclick dbltap', () => {
                editTextInline(element);
            });
        }
    }

    function selectElement(element) {
        // Deselect previous
        if (state.selectedElement && state.selectedElement.transformer) {
            state.selectedElement.transformer.visible(false);
        }

        state.selectedElement = element;

        if (element) {
            element.transformer.visible(true);
            openDrawer(element);
            highlightLayerItem(element.id);
            // Update YourSurprise controls with element values
            updateControlsFromElement(element);
        } else {
            closeDrawer();
            clearControls();
        }

        state.layer.batchDraw();
    }

    function updateControlsFromElement(element) {
        if (!element) return;

        // Update tools title
        if (DOM.toolsTitle) {
            DOM.toolsTitle.textContent = element.type === 'text' ? 'Texte' : 'Image';
        }

        if (element.type === 'text') {
            const props = element.properties;

            // Text input
            if (DOM.textInput) {
                DOM.textInput.value = props.text || '';
                if (DOM.textCounter) {
                    DOM.textCounter.textContent = (props.text || '').length;
                }
            }

            // Font select
            if (DOM.fontSelect) {
                DOM.fontSelect.value = props.fontFamily || 'Inter';
            }

            // Color picker
            if (DOM.colorPicker && props.fill) {
                DOM.colorPicker.style.backgroundColor = props.fill;
                DOM.colorPicker.dataset.hex = props.fill;
            }

            // Style buttons
            DOM.styleBtns?.forEach(btn => {
                const style = btn.dataset.style;
                const fontStyle = element.konvaNode.fontStyle() || '';
                if (style === 'bold') {
                    btn.classList.toggle('active', fontStyle.includes('bold'));
                } else if (style === 'italic') {
                    btn.classList.toggle('active', fontStyle.includes('italic'));
                }
            });

            // Align buttons
            DOM.alignBtns?.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.align === (props.align || 'center'));
            });

            // Rotation slider
            if (DOM.rotationSlider) {
                DOM.rotationSlider.value = props.rotation || 0;
            }
        }
    }

    function clearControls() {
        if (DOM.textInput) DOM.textInput.value = '';
        if (DOM.textCounter) DOM.textCounter.textContent = '0';
        if (DOM.rotationSlider) DOM.rotationSlider.value = 0;
        DOM.styleBtns?.forEach(btn => btn.classList.remove('active'));
        DOM.alignBtns?.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.align === 'center');
        });
    }

    function deleteElement(element) {
        if (!element) return;

        const index = state.elements.indexOf(element);
        if (index > -1) {
            state.elements.splice(index, 1);
        }

        element.konvaNode.destroy();
        element.transformer.destroy();

        if (state.selectedElement === element) {
            state.selectedElement = null;
            closeDrawer();
        }

        updateLayersPanel();
        state.layer.batchDraw();
        saveDraft();
    }

    // ===========================================
    // SNAP GUIDES
    // ===========================================
    function applySnap(element) {
        if (!state.printZone) return;

        const node = element.konvaNode;
        const zoneX = state.printZone.x();
        const zoneY = state.printZone.y();
        const zoneWidth = state.printZone.width();
        const zoneHeight = state.printZone.height();
        const zoneCenterX = zoneX + zoneWidth / 2;
        const zoneCenterY = zoneY + zoneHeight / 2;

        let snappedX = node.x();
        let snappedY = node.y();
        let showHGuide = false;
        let showVGuide = false;

        // Snap to center X
        if (Math.abs(node.x() - zoneCenterX) < CONFIG.snapThreshold) {
            snappedX = zoneCenterX;
            showVGuide = true;
        }

        // Snap to center Y
        if (Math.abs(node.y() - zoneCenterY) < CONFIG.snapThreshold) {
            snappedY = zoneCenterY;
            showHGuide = true;
        }

        // Snap to edges
        if (Math.abs(node.x() - zoneX) < CONFIG.snapThreshold) {
            snappedX = zoneX;
            showVGuide = true;
        }
        if (Math.abs(node.x() - (zoneX + zoneWidth)) < CONFIG.snapThreshold) {
            snappedX = zoneX + zoneWidth;
            showVGuide = true;
        }
        if (Math.abs(node.y() - zoneY) < CONFIG.snapThreshold) {
            snappedY = zoneY;
            showHGuide = true;
        }
        if (Math.abs(node.y() - (zoneY + zoneHeight)) < CONFIG.snapThreshold) {
            snappedY = zoneY + zoneHeight;
            showHGuide = true;
        }

        node.position({ x: snappedX, y: snappedY });

        // Show/hide guides
        toggleSnapGuide('horizontal', showHGuide, snappedY);
        toggleSnapGuide('vertical', showVGuide, snappedX);
    }

    function toggleSnapGuide(orientation, show, position) {
        // Create or update snap guide line
        if (!state.snapGuides[orientation]) {
            state.snapGuides[orientation] = new Konva.Line({
                points: orientation === 'horizontal'
                    ? [0, position, state.stage.width(), position]
                    : [position, 0, position, state.stage.height()],
                stroke: '#FF69B4',
                strokeWidth: 1,
                dash: [4, 4],
                listening: false,
            });
            state.layer.add(state.snapGuides[orientation]);
        }

        if (show) {
            if (orientation === 'horizontal') {
                state.snapGuides[orientation].points([0, position, state.stage.width(), position]);
            } else {
                state.snapGuides[orientation].points([position, 0, position, state.stage.height()]);
            }
            state.snapGuides[orientation].visible(true);
        } else {
            state.snapGuides[orientation].visible(false);
        }
    }

    function hideSnapGuides() {
        if (state.snapGuides.horizontal) state.snapGuides.horizontal.visible(false);
        if (state.snapGuides.vertical) state.snapGuides.vertical.visible(false);
        state.layer.batchDraw();
    }

    function constrainToPrintZone(element) {
        if (!state.printZone) return;

        const node = element.konvaNode;
        const zoneX = state.printZone.x();
        const zoneY = state.printZone.y();
        const zoneWidth = state.printZone.width();
        const zoneHeight = state.printZone.height();

        let x = node.x();
        let y = node.y();

        // Constrain X
        if (x < zoneX) x = zoneX;
        if (x > zoneX + zoneWidth) x = zoneX + zoneWidth;

        // Constrain Y
        if (y < zoneY) y = zoneY;
        if (y > zoneY + zoneHeight) y = zoneY + zoneHeight;

        node.position({ x, y });
    }

    // ===========================================
    // DRAWER (Properties panel)
    // ===========================================
    function openDrawer(element) {
        if (!DOM.drawer || !DOM.configurator) return;

        DOM.configurator.classList.add('drawer-open');

        if (DOM.drawerTitle) {
            DOM.drawerTitle.textContent = element.type === 'text' ? 'TEXTE' : 'IMAGE';
        }

        if (DOM.drawerContent) {
            DOM.drawerContent.innerHTML = renderDrawerContent(element);
            setupDrawerEvents(element);
        }
    }

    function closeDrawer() {
        if (!DOM.drawer || !DOM.configurator) return;
        DOM.configurator.classList.remove('drawer-open');
        selectElement(null);
    }

    function renderDrawerContent(element) {
        if (element.type === 'text') {
            return `
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Police</div>
                    <select class="cfg-prop-input" id="drawer-font">
                        ${renderFontOptions(element.properties.fontFamily)}
                    </select>
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Taille</div>
                    <input type="range" class="cfg-slider" id="drawer-size"
                           min="12" max="72" value="${element.properties.fontSize}">
                    <div style="text-align: center; margin-top: 4px; font-size: 0.85rem; color: var(--gray);">
                        ${element.properties.fontSize}px
                    </div>
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Couleur</div>
                    <div class="cfg-color-palette" id="drawer-colors">
                        ${renderColorSwatches(element.properties.fill)}
                    </div>
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Rotation</div>
                    <input type="range" class="cfg-slider" id="drawer-rotation"
                           min="-180" max="180" value="${element.properties.rotation || 0}">
                    <div style="text-align: center; margin-top: 4px; font-size: 0.85rem; color: var(--gray);">
                        ${element.properties.rotation || 0}°
                    </div>
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Opacité</div>
                    <input type="range" class="cfg-slider" id="drawer-opacity"
                           min="0" max="100" value="${(element.properties.opacity || 1) * 100}">
                </div>
                <button class="cfg-delete-btn" id="drawer-delete">
                    🗑️ Supprimer
                </button>
            `;
        } else {
            return `
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Position</div>
                    <div class="cfg-prop-row">
                        <div style="flex: 1;">
                            <input type="number" class="cfg-prop-input" id="drawer-pos-x"
                                   value="${Math.round(element.position.x)}">
                            <div class="cfg-prop-input-label">X %</div>
                        </div>
                        <div style="flex: 1;">
                            <input type="number" class="cfg-prop-input" id="drawer-pos-y"
                                   value="${Math.round(element.position.y)}">
                            <div class="cfg-prop-input-label">Y %</div>
                        </div>
                    </div>
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Taille</div>
                    <input type="range" class="cfg-slider" id="drawer-scale"
                           min="10" max="200" value="${(element.properties.scaleX || 1) * 100}">
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Rotation</div>
                    <input type="range" class="cfg-slider" id="drawer-rotation"
                           min="-180" max="180" value="${element.properties.rotation || 0}">
                </div>
                <div class="cfg-prop-group">
                    <div class="cfg-prop-label">Opacité</div>
                    <input type="range" class="cfg-slider" id="drawer-opacity"
                           min="0" max="100" value="${(element.properties.opacity || 1) * 100}">
                </div>
                <button class="cfg-delete-btn" id="drawer-delete">
                    🗑️ Supprimer
                </button>
            `;
        }
    }

    function renderFontOptions(selectedFont) {
        const fonts = window.__FONTS_DATA || [
            { value: 'Inter', label: 'Inter' },
            { value: 'Poppins', label: 'Poppins' },
        ];
        return fonts.map(f =>
            `<option value="${f.value}" ${f.value === selectedFont ? 'selected' : ''}>${f.label}</option>`
        ).join('');
    }

    function renderColorSwatches(selectedColor) {
        const colors = window.__TEXT_COLORS_DATA || [
            { value: 'noir', hex: '#1A1A2E' },
            { value: 'blanc', hex: '#FFFFFF' },
            { value: 'rose', hex: '#FF69B4' },
            { value: 'menthe', hex: '#3DFFC0' },
        ];
        return colors.map(c =>
            `<div class="cfg-color-swatch ${c.hex === selectedColor ? 'selected' : ''}"
                  style="background-color: ${c.hex}"
                  data-color="${c.value}"
                  data-hex="${c.hex}"></div>`
        ).join('');
    }

    function renderTechniqueOptions() {
        const techniques = window.__TECHNIQUES_DATA || [
            { value: 'flex', label: 'Flex', price: 0 },
            { value: 'flock', label: 'Flock', price: 2 },
            { value: 'broderie', label: 'Broderie', price: 5 },
        ];
        const selected = window.__SELECTED_TECHNIQUE || techniques[0]?.value || 'flex';
        return techniques.map(t =>
            `<option value="${t.value}" ${t.value === selected ? 'selected' : ''}>
                ${t.label}${t.price > 0 ? ' (+' + t.price.toFixed(2).replace('.', ',') + ' €)' : ' (Inclus)'}
            </option>`
        ).join('');
    }

    function renderMobileFontOptions() {
        const fonts = window.__FONTS_DATA || [
            { value: 'Poppins', label: 'Poppins' },
            { value: 'Inter', label: 'Inter' },
        ];
        return fonts.map((f, idx) =>
            `<div class="cfg-mobile-dropdown-item ${idx === 0 ? 'selected' : ''}"
                  data-font="${f.value}" data-label="${f.label}"
                  style="font-family: '${f.value}'">
                ${f.label}
            </div>`
        ).join('');
    }

    function renderMobileTechniqueOptions() {
        const techniques = window.__TECHNIQUES_DATA || [
            { value: 'flex', label: 'Flex', price: 0, description: '' },
        ];
        const selected = window.__SELECTED_TECHNIQUE || techniques[0]?.value || 'flex';
        return techniques.map(t =>
            `<div class="cfg-mobile-dropdown-item ${t.value === selected ? 'selected' : ''}"
                  data-technique="${t.value}" data-label="${t.label}"
                  data-price="${t.price}" data-desc="${t.description || ''}">
                <span class="item-name">${t.label}</span>
                <span class="item-badge ${t.price > 0 ? 'price' : ''}">${t.price > 0 ? '+' + t.price.toFixed(2).replace('.', ',') + ' €' : 'Inclus'}</span>
            </div>`
        ).join('');
    }

    function setupDrawerEvents(element) {
        // Font change
        const fontSelect = document.getElementById('drawer-font');
        if (fontSelect) {
            fontSelect.addEventListener('change', (e) => {
                element.properties.fontFamily = e.target.value;
                element.konvaNode.fontFamily(e.target.value);
                state.layer.batchDraw();
                saveDraft();
            });
        }

        // Size change
        const sizeSlider = document.getElementById('drawer-size');
        if (sizeSlider) {
            sizeSlider.addEventListener('input', (e) => {
                const size = parseInt(e.target.value);
                element.properties.fontSize = size;
                element.konvaNode.fontSize(size);
                element.konvaNode.offsetX(element.konvaNode.width() / 2);
                element.konvaNode.offsetY(element.konvaNode.height() / 2);
                e.target.nextElementSibling.textContent = size + 'px';
                state.layer.batchDraw();
            });
            sizeSlider.addEventListener('change', saveDraft);
        }

        // Color change
        const colorSwatches = document.querySelectorAll('#drawer-colors .cfg-color-swatch');
        colorSwatches.forEach(swatch => {
            swatch.addEventListener('click', () => {
                colorSwatches.forEach(s => s.classList.remove('selected'));
                swatch.classList.add('selected');
                const hex = swatch.dataset.hex;
                element.properties.fill = hex;
                element.konvaNode.fill(hex);
                state.layer.batchDraw();
                saveDraft();
            });
        });

        // Rotation change
        const rotationSlider = document.getElementById('drawer-rotation');
        if (rotationSlider) {
            rotationSlider.addEventListener('input', (e) => {
                const rotation = parseInt(e.target.value);
                element.properties.rotation = rotation;
                element.konvaNode.rotation(rotation);
                e.target.nextElementSibling.textContent = rotation + '°';
                state.layer.batchDraw();
            });
            rotationSlider.addEventListener('change', saveDraft);
        }

        // Opacity change
        const opacitySlider = document.getElementById('drawer-opacity');
        if (opacitySlider) {
            opacitySlider.addEventListener('input', (e) => {
                const opacity = parseInt(e.target.value) / 100;
                element.properties.opacity = opacity;
                element.konvaNode.opacity(opacity);
                state.layer.batchDraw();
            });
            opacitySlider.addEventListener('change', saveDraft);
        }

        // Scale change (for images)
        const scaleSlider = document.getElementById('drawer-scale');
        if (scaleSlider) {
            scaleSlider.addEventListener('input', (e) => {
                const scale = parseInt(e.target.value) / 100;
                element.properties.scaleX = scale;
                element.properties.scaleY = scale;
                element.konvaNode.scaleX(scale);
                element.konvaNode.scaleY(scale);
                state.layer.batchDraw();
            });
            scaleSlider.addEventListener('change', saveDraft);
        }

        // Delete
        const deleteBtn = document.getElementById('drawer-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                if (confirm('Supprimer cet élément ?')) {
                    deleteElement(element);
                }
            });
        }
    }

    // ===========================================
    // LAYERS PANEL
    // ===========================================
    function updateLayersPanel() {
        if (!DOM.layersList) return;

        const currentViewElements = state.elements.filter(el => el.view === state.currentView);

        if (DOM.layersCount) {
            DOM.layersCount.textContent = `${currentViewElements.length}/${CONFIG.maxElements}`;
        }

        DOM.layersList.innerHTML = currentViewElements.map(el => `
            <div class="cfg-layer-item ${state.selectedElement === el ? 'selected' : ''}"
                 data-id="${el.id}">
                <span class="cfg-layer-handle">≡</span>
                <span class="cfg-layer-icon">${el.type === 'text' ? '📝' : '🖼️'}</span>
                <span class="cfg-layer-name">${getElementDisplayName(el)}</span>
                <div class="cfg-layer-actions">
                    <button class="cfg-layer-btn ${el.visible ? '' : 'hidden'}"
                            data-action="toggle-visibility" title="Visibilité">
                        ${el.visible ? '👁️' : '👁️‍🗨️'}
                    </button>
                </div>
            </div>
        `).join('');

        // Setup layer events
        DOM.layersList.querySelectorAll('.cfg-layer-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (e.target.closest('.cfg-layer-btn')) return;
                const el = state.elements.find(el => el.id === item.dataset.id);
                if (el) selectElement(el);
            });

            item.querySelector('[data-action="toggle-visibility"]')?.addEventListener('click', () => {
                const el = state.elements.find(el => el.id === item.dataset.id);
                if (el) toggleElementVisibility(el);
            });
        });
    }

    function getElementDisplayName(element) {
        if (element.type === 'text') {
            return element.properties.text.substring(0, 20) + (element.properties.text.length > 20 ? '...' : '');
        }
        return 'Image';
    }

    function highlightLayerItem(elementId) {
        DOM.layersList?.querySelectorAll('.cfg-layer-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.id === elementId);
        });
    }

    function toggleElementVisibility(element) {
        element.visible = !element.visible;
        element.konvaNode.visible(element.visible);
        element.transformer.visible(element.visible && state.selectedElement === element);
        updateLayersPanel();
        state.layer.batchDraw();
        saveDraft();
    }

    // ===========================================
    // EVENT LISTENERS
    // ===========================================
    function setupEventListeners() {
        // Tool tabs
        DOM.toolTabs?.forEach(tab => {
            tab.addEventListener('click', () => {
                const tool = tab.dataset.tool;
                switchTool(tool);
            });
        });

        // View toggle (Face/Back)
        DOM.viewBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                switchView(view);
            });
        });

        // Text input - Enter to add
        DOM.textInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const text = DOM.textInput.value.trim();
                if (text) {
                    addTextElement(text);
                }
            }
        });

        // Text input - Character counter
        DOM.textInput?.addEventListener('input', (e) => {
            const count = e.target.value.length;
            if (DOM.textCounter) {
                DOM.textCounter.textContent = count;
            }
            // Update selected element text in real-time
            if (state.selectedElement && state.selectedElement.type === 'text') {
                state.selectedElement.properties.text = e.target.value;
                state.selectedElement.konvaNode.text(e.target.value);
                state.selectedElement.konvaNode.offsetX(state.selectedElement.konvaNode.width() / 2);
                state.layer.batchDraw();
                saveDraft();
            }
        });

        // Add text button (+ Extra texte)
        DOM.addTextBtn?.addEventListener('click', () => {
            console.log('[Configurator] Add text button clicked');
            const text = DOM.textInput?.value.trim() || 'Nouveau texte';
            console.log('[Configurator] Text to add:', text);
            const element = addTextElement(text);
            console.log('[Configurator] Element created:', element);
        });

        // Main add text button (Ajouter le texte)
        DOM.addTextMainBtn?.addEventListener('click', () => {
            const text = DOM.textInput?.value.trim();
            if (text) {
                addTextElement(text);
            } else {
                showNotification('Saisissez un texte', 'error');
            }
        });

        // Delete element button
        DOM.deleteBtn?.addEventListener('click', () => {
            if (state.selectedElement) {
                deleteElement(state.selectedElement);
            }
        });

        // Font select
        DOM.fontSelect?.addEventListener('change', async (e) => {
            if (state.selectedElement && state.selectedElement.type === 'text') {
                const fontFamily = e.target.value;
                console.log('[Configurator] Changing font to:', fontFamily);

                // Load font first (if not already loaded)
                try {
                    await loadGoogleFont(fontFamily);
                } catch (err) {
                    console.warn('[Configurator] Font load failed, applying anyway');
                }

                // Apply font
                state.selectedElement.properties.fontFamily = fontFamily;
                state.selectedElement.konvaNode.fontFamily(fontFamily);
                state.selectedElement.konvaNode.offsetX(state.selectedElement.konvaNode.width() / 2);
                state.layer.batchDraw();
                saveDraft();
            }
        });

        // Color picker click - toggle dropdown
        DOM.colorPicker?.addEventListener('click', () => {
            if (DOM.colorDropdown) {
                const isOpen = DOM.colorDropdown.style.display !== 'none';
                DOM.colorDropdown.style.display = isOpen ? 'none' : 'flex';
            }
        });

        // Color dropdown swatches
        DOM.colorDropdown?.querySelectorAll('.cfg-color-swatch').forEach(swatch => {
            swatch.addEventListener('click', () => {
                const hex = swatch.dataset.hex;
                // Update picker display
                if (DOM.colorPicker) {
                    DOM.colorPicker.style.backgroundColor = hex;
                    DOM.colorPicker.dataset.hex = hex;
                }
                // Update selected element
                if (state.selectedElement && state.selectedElement.type === 'text') {
                    state.selectedElement.properties.fill = hex;
                    state.selectedElement.konvaNode.fill(hex);
                    state.layer.batchDraw();
                    saveDraft();
                }
                // Mark selected
                DOM.colorDropdown.querySelectorAll('.cfg-color-swatch').forEach(s => s.classList.remove('selected'));
                swatch.classList.add('selected');
                // Close dropdown
                DOM.colorDropdown.style.display = 'none';
            });
        });

        // Product color swatches
        DOM.productColors?.forEach(swatch => {
            swatch.addEventListener('click', () => {
                const colorName = swatch.dataset.color;
                const frontImage = swatch.dataset.front;
                const backImage = swatch.dataset.back;

                console.log('[Configurator] Changing product color to:', colorName);

                // Track selected color
                state.selectedProductColor = colorName;

                // Mark selected
                DOM.productColors.forEach(s => s.classList.remove('selected'));
                swatch.classList.add('selected');

                // Update product image if variant images are available
                if (frontImage || backImage) {
                    changeProductImage(
                        state.currentView === 'front' ? frontImage : backImage,
                        colorName
                    );
                }

                saveDraft();
            });
        });

        // Initialize selected product color from DOM
        const initialColorSwatch = document.querySelector('.cfg-product-color-swatch.selected');
        if (initialColorSwatch) {
            state.selectedProductColor = initialColorSwatch.dataset.color;
        }

        // Size selection
        const sizeButtons = document.querySelectorAll('.cfg-size-btn');
        sizeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                sizeButtons.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                state.selectedSize = btn.dataset.size;
                saveDraft();
            });
        });

        // Initialize selected size from DOM
        const initialSizeBtn = document.querySelector('.cfg-size-btn.selected');
        if (initialSizeBtn) {
            state.selectedSize = initialSizeBtn.dataset.size;
        } else {
            state.selectedSize = 'M'; // Default
        }

        // Style buttons (Bold / Italic)
        DOM.styleBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!state.selectedElement || state.selectedElement.type !== 'text') return;

                const style = btn.dataset.style;
                btn.classList.toggle('active');

                if (style === 'bold') {
                    const isBold = btn.classList.contains('active');
                    state.selectedElement.properties.fontStyle = isBold ? 'bold' : 'normal';
                    state.selectedElement.konvaNode.fontStyle(isBold ? 'bold' : 'normal');
                } else if (style === 'italic') {
                    const isItalic = btn.classList.contains('active');
                    const currentStyle = state.selectedElement.konvaNode.fontStyle() || '';
                    const newStyle = isItalic
                        ? (currentStyle.includes('bold') ? 'bold italic' : 'italic')
                        : currentStyle.replace('italic', '').trim() || 'normal';
                    state.selectedElement.konvaNode.fontStyle(newStyle);
                }
                state.selectedElement.konvaNode.offsetX(state.selectedElement.konvaNode.width() / 2);
                state.layer.batchDraw();
                saveDraft();
            });
        });

        // Align buttons
        DOM.alignBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!state.selectedElement || state.selectedElement.type !== 'text') return;

                DOM.alignBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const align = btn.dataset.align;
                state.selectedElement.properties.align = align;
                state.selectedElement.konvaNode.align(align);
                state.layer.batchDraw();
                saveDraft();
            });
        });

        // Dimension buttons (+/-)
        DOM.dimBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!state.selectedElement) return;

                const action = btn.dataset.action;
                const node = state.selectedElement.konvaNode;
                const scaleChange = action === 'increase' ? 1.1 : 0.9;

                node.scaleX(node.scaleX() * scaleChange);
                node.scaleY(node.scaleY() * scaleChange);
                state.layer.batchDraw();
                saveDraft();
            });
        });

        // Rotation slider
        DOM.rotationSlider?.addEventListener('input', (e) => {
            if (!state.selectedElement) return;

            const rotation = parseInt(e.target.value);
            state.selectedElement.properties.rotation = rotation;
            state.selectedElement.konvaNode.rotation(rotation);
            state.layer.batchDraw();
        });

        DOM.rotationSlider?.addEventListener('change', () => {
            saveDraft();
        });

        // Move buttons (arrows)
        DOM.moveBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!state.selectedElement) return;

                const dir = btn.dataset.dir;
                const node = state.selectedElement.konvaNode;
                const step = 5; // pixels

                switch (dir) {
                    case 'left': node.x(node.x() - step); break;
                    case 'right': node.x(node.x() + step); break;
                    case 'up': node.y(node.y() - step); break;
                    case 'down': node.y(node.y() + step); break;
                }
                state.layer.batchDraw();
                saveDraft();
            });
        });

        // Layer buttons (front/back)
        DOM.layerBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!state.selectedElement) return;

                const action = btn.dataset.action;
                const node = state.selectedElement.konvaNode;

                if (action === 'front') {
                    node.moveToTop();
                    state.selectedElement.transformer.moveToTop();
                } else if (action === 'back') {
                    node.moveToBottom();
                    // Keep product image at bottom
                    if (state.productImage) {
                        state.productImage.moveToBottom();
                    }
                }
                state.layer.batchDraw();
                saveDraft();
                updateLayersPanel();
            });
        });

        // Photo upload - Click to open file picker
        DOM.uploadZone?.addEventListener('click', () => {
            DOM.imageUpload?.click();
        });

        // Photo upload - Drag and drop
        DOM.uploadZone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            DOM.uploadZone.classList.add('dragover');
        });

        DOM.uploadZone?.addEventListener('dragleave', () => {
            DOM.uploadZone.classList.remove('dragover');
        });

        DOM.uploadZone?.addEventListener('drop', (e) => {
            e.preventDefault();
            DOM.uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleImageUpload(files[0]);
            }
        });

        // Photo upload - File input change
        DOM.imageUpload?.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                handleImageUpload(file);
            }
        });

        // Color swatches in tools panel (legacy)
        DOM.colorSwatches?.forEach(swatch => {
            swatch.addEventListener('click', () => {
                if (state.selectedElement && state.selectedElement.type === 'text') {
                    const hex = swatch.dataset.hex;
                    state.selectedElement.properties.fill = hex;
                    state.selectedElement.konvaNode.fill(hex);
                    state.layer.batchDraw();
                    saveDraft();
                }
            });
        });

        // ===========================================
        // MODERN CUSTOM DROPDOWNS
        // ===========================================

        // Font dropdown
        const fontTrigger = document.getElementById('cfgFontTrigger');
        const fontList = document.getElementById('cfgFontList');
        const fontInput = document.getElementById('cfgFontSelect');
        const fontPreview = document.getElementById('cfgFontPreview');

        fontTrigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            fontTrigger.classList.toggle('open');
            fontList?.classList.toggle('open');
            // Close technique dropdown if open
            techniqueTrigger?.classList.remove('open');
            techniqueListEl?.classList.remove('open');
        });

        fontList?.querySelectorAll('.cfg-dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                const font = item.dataset.font;
                const label = item.dataset.label;

                // Update hidden input
                if (fontInput) fontInput.value = font;

                // Update preview
                if (fontPreview) {
                    fontPreview.textContent = label;
                    fontPreview.style.fontFamily = `'${font}'`;
                }

                // Update selection
                fontList.querySelectorAll('.cfg-dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');

                // Close dropdown
                fontTrigger?.classList.remove('open');
                fontList?.classList.remove('open');

                // Update selected element if exists
                if (state.selectedElement && state.selectedElement.type === 'text') {
                    state.selectedElement.properties.fontFamily = font;
                    state.selectedElement.konvaNode.fontFamily(font);
                    state.layer.batchDraw();
                    saveDraft();
                }
            });
        });

        // Technique dropdown
        const techniqueTrigger = document.getElementById('cfgTechniqueTrigger');
        const techniqueListEl = document.getElementById('cfgTechniqueList');
        const techniqueInput = document.getElementById('cfgTechniqueSelect');
        const techniquePreviewText = document.getElementById('cfgTechniquePreview');
        const techniquePriceBadge = document.getElementById('cfgTechniquePriceBadge');
        const techniqueDetails = document.getElementById('cfgTechniqueDetails');
        const techniquePreviewBtn = document.getElementById('cfgTechniquePreviewBtn');

        techniqueTrigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            techniqueTrigger.classList.toggle('open');
            techniqueListEl?.classList.toggle('open');
            // Close font dropdown if open
            fontTrigger?.classList.remove('open');
            fontList?.classList.remove('open');
        });

        techniqueListEl?.querySelectorAll('.cfg-dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                const technique = item.dataset.technique;
                const label = item.dataset.label;
                const price = parseFloat(item.dataset.price) || 0;
                const desc = item.dataset.desc || '';

                // Update hidden input
                if (techniqueInput) techniqueInput.value = technique;

                // Update preview
                if (techniquePreviewText) techniquePreviewText.textContent = label;
                if (techniquePriceBadge) {
                    techniquePriceBadge.textContent = price > 0 ? `+${price.toFixed(2).replace('.', ',')} €` : 'Inclus';
                }

                // Update description
                if (techniqueDetails) {
                    techniqueDetails.querySelector('.technique-description').textContent = desc;
                }

                // Update preview button
                if (techniquePreviewBtn) {
                    techniquePreviewBtn.dataset.technique = technique;
                }

                // Update selection
                techniqueListEl.querySelectorAll('.cfg-dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');

                // Close dropdown
                techniqueTrigger?.classList.remove('open');
                techniqueListEl?.classList.remove('open');

                // Store and update price
                window.__SELECTED_TECHNIQUE = technique;
                updatePrice();
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.cfg-modern-dropdown')) {
                fontTrigger?.classList.remove('open');
                fontList?.classList.remove('open');
                techniqueTrigger?.classList.remove('open');
                techniqueListEl?.classList.remove('open');
            }
        });

        // Technique preview button (pink button under canvas)
        techniquePreviewBtn?.addEventListener('click', () => {
            const technique = techniquePreviewBtn.dataset.technique || techniqueInput?.value || 'flex';
            if (window.PersonnalyRealRender) {
                window.PersonnalyRealRender.open(technique);
            }
        });

        // Drawer close
        DOM.drawerClose?.addEventListener('click', closeDrawer);

        // Zoom controls
        DOM.zoomIn?.addEventListener('click', () => zoom(0.1));
        DOM.zoomOut?.addEventListener('click', () => zoom(-0.1));
        DOM.zoomReset?.addEventListener('click', () => resetZoom());

        // Snap toggle
        DOM.snapToggle?.addEventListener('change', (e) => {
            CONFIG.snapEnabled = e.target.checked;
        });

        // Click on empty area to deselect
        state.stage?.on('click tap', (e) => {
            if (e.target === state.stage || e.target === state.productImage) {
                selectElement(null);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboard);

        // Save button
        DOM.saveBtn?.addEventListener('click', () => {
            saveDraft();
            // Visual feedback
            DOM.saveBtn.classList.add('saved');
            const originalText = DOM.saveBtn.innerHTML;
            DOM.saveBtn.innerHTML = '✓ Sauvegardé';
            setTimeout(() => {
                DOM.saveBtn.classList.remove('saved');
                DOM.saveBtn.innerHTML = originalText;
            }, 2000);
            showNotification('Design sauvegardé !', 'success');
        });

        // Share button
        DOM.shareBtn?.addEventListener('click', async () => {
            try {
                // Generate share URL with design data
                const shareData = generateShareData();
                const shareUrl = `${window.location.origin}${window.location.pathname}?v2=1&design=${shareData}`;

                // Try native share API first
                if (navigator.share) {
                    await navigator.share({
                        title: 'Mon design PERSONNALY',
                        text: 'Découvrez mon design personnalisé !',
                        url: shareUrl
                    });
                    showNotification('Partagé avec succès !', 'success');
                } else {
                    // Fallback: copy to clipboard
                    await navigator.clipboard.writeText(shareUrl);
                    showNotification('Lien copié dans le presse-papier !', 'success');
                }
            } catch (err) {
                console.error('[Configurator] Share failed:', err);
                showNotification('Erreur lors du partage', 'error');
            }
        });

        // Mobile tabs
        DOM.mobileTabs?.forEach(tab => {
            tab.addEventListener('click', () => {
                const tool = tab.dataset.tool;
                openMobileDrawer(tool);
            });
        });

        DOM.mobileDrawerClose?.addEventListener('click', closeMobileDrawer);

        // Form submission - populate hidden fields
        const form = document.getElementById('customizationForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                // Get cart data (JSON + preview image)
                const cartData = getCartData();

                // Populate hidden fields
                const jsonInput = document.getElementById('customizationJson');
                const previewInput = document.getElementById('previewImage');

                if (jsonInput && cartData.json) {
                    jsonInput.value = cartData.json;
                }
                if (previewInput && cartData.preview) {
                    previewInput.value = cartData.preview;
                }

                console.log('[Configurator] Form submitted with cart data');
            });
        }
    }

    function switchTool(tool) {
        state.currentTool = tool;

        DOM.toolTabs?.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tool === tool);
        });

        DOM.toolPanels?.forEach(panel => {
            panel.classList.toggle('active', panel.dataset.tool === tool);
        });
    }

    function switchView(view) {
        state.currentView = view;

        DOM.viewBtns?.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });

        // Hide elements from other view
        state.elements.forEach(el => {
            const shouldShow = el.view === view && el.visible;
            el.konvaNode.visible(shouldShow);
            el.transformer.visible(shouldShow && state.selectedElement === el);
        });

        // Load new product image - use color-specific image if available
        const colorImages = window.__COLOR_IMAGES || {};
        if (state.selectedProductColor && colorImages[state.selectedProductColor]) {
            const colorImg = colorImages[state.selectedProductColor];
            const imageUrl = view === 'front' ? colorImg.front : colorImg.back;
            if (imageUrl) {
                changeProductImage(imageUrl, state.selectedProductColor);
            } else {
                loadProductImage();
            }
        } else {
            loadProductImage();
        }

        // Update layers panel
        updateLayersPanel();

        state.layer.batchDraw();
    }

    function handleKeyboard(e) {
        // Delete selected element
        if ((e.key === 'Delete' || e.key === 'Backspace') && state.selectedElement) {
            // Don't delete if typing in input
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
                return;
            }
            e.preventDefault();
            deleteElement(state.selectedElement);
        }

        // Escape to deselect
        if (e.key === 'Escape') {
            selectElement(null);
        }
    }

    // ===========================================
    // MOBILE
    // ===========================================
    function openMobileDrawer(tool) {
        if (!DOM.mobileDrawer) return;

        DOM.mobileTabs?.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tool === tool);
        });

        DOM.mobileDrawer.classList.add('open');

        if (DOM.mobileDrawerTitle) {
            const titles = { text: '📝 Texte', photo: '🖼️ Photo', design: '🎨 Design', layers: '📦 Calques' };
            DOM.mobileDrawerTitle.textContent = titles[tool] || tool;
        }

        if (DOM.mobileDrawerContent) {
            DOM.mobileDrawerContent.innerHTML = renderMobileToolContent(tool);
            setupMobileToolEvents(tool);
        }
    }

    function closeMobileDrawer() {
        if (!DOM.mobileDrawer) return;
        DOM.mobileDrawer.classList.remove('open');
        DOM.mobileTabs?.forEach(tab => tab.classList.remove('active'));
    }

    function renderMobileToolContent(tool) {
        // Modern mobile tool content with custom dropdowns
        switch (tool) {
            case 'text':
                return `
                    <input type="text" class="cfg-mobile-input" placeholder="Tapez votre texte..." id="mobile-text-input">

                    <button class="cfg-mobile-add-btn" id="mobile-add-text">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Ajouter le texte
                    </button>

                    <div class="cfg-mobile-divider"></div>

                    <div class="cfg-mobile-section">
                        <div class="cfg-mobile-label">Police d'écriture</div>
                        <div class="cfg-mobile-dropdown" id="mobile-font-dropdown">
                            <div class="cfg-mobile-dropdown-trigger" id="mobile-font-trigger">
                                <span class="cfg-mobile-dropdown-text" id="mobile-font-preview">Poppins</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                            <div class="cfg-mobile-dropdown-list" id="mobile-font-list">
                                ${renderMobileFontOptions()}
                            </div>
                        </div>
                    </div>

                    <div class="cfg-mobile-section">
                        <div class="cfg-mobile-label">Couleur du texte</div>
                        <div class="cfg-mobile-colors" id="mobile-colors">
                            ${renderColorSwatches('#1A1A2E')}
                        </div>
                    </div>

                    <div class="cfg-mobile-section">
                        <div class="cfg-mobile-label">Technique de marquage</div>
                        <div class="cfg-mobile-dropdown" id="mobile-technique-dropdown">
                            <div class="cfg-mobile-dropdown-trigger" id="mobile-technique-trigger">
                                <span class="cfg-mobile-dropdown-text" id="mobile-technique-preview">${window.__TECHNIQUES_DATA?.[0]?.label || 'Flex'}</span>
                                <span class="cfg-mobile-dropdown-badge">${(window.__TECHNIQUES_DATA?.[0]?.price || 0) > 0 ? '+' + window.__TECHNIQUES_DATA[0].price.toFixed(2).replace('.', ',') + ' €' : 'Inclus'}</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                            <div class="cfg-mobile-dropdown-list" id="mobile-technique-list">
                                ${renderMobileTechniqueOptions()}
                            </div>
                        </div>
                    </div>
                `;
            case 'layers':
                const currentViewElements = state.elements.filter(el => el.view === state.currentView);
                return `
                    <div class="cfg-layers-header">
                        <span>Éléments</span>
                        <span class="cfg-layers-count">${currentViewElements.length}/${CONFIG.maxElements}</span>
                    </div>
                    <div class="cfg-layers-list" id="mobile-layers">
                        ${currentViewElements.map(el => `
                            <div class="cfg-layer-item" data-id="${el.id}">
                                <span class="cfg-layer-icon">${el.type === 'text' ? '📝' : '🖼️'}</span>
                                <span class="cfg-layer-name">${getElementDisplayName(el)}</span>
                            </div>
                        `).join('')}
                    </div>
                `;
            default:
                return '<p style="text-align: center; color: var(--gray);">Bientôt disponible</p>';
        }
    }

    function setupMobileToolEvents(tool) {
        if (tool === 'text') {
            const addBtn = document.getElementById('mobile-add-text');
            const textInput = document.getElementById('mobile-text-input');
            const colorPalette = document.getElementById('mobile-colors');

            // Mobile font dropdown
            const fontTrigger = document.getElementById('mobile-font-trigger');
            const fontList = document.getElementById('mobile-font-list');
            const fontPreview = document.getElementById('mobile-font-preview');
            let selectedFont = 'Poppins';

            fontTrigger?.addEventListener('click', (e) => {
                e.stopPropagation();
                fontList?.classList.toggle('open');
                // Close technique list
                document.getElementById('mobile-technique-list')?.classList.remove('open');
            });

            fontList?.querySelectorAll('.cfg-mobile-dropdown-item').forEach(item => {
                item.addEventListener('click', () => {
                    selectedFont = item.dataset.font;
                    if (fontPreview) {
                        fontPreview.textContent = item.dataset.label;
                        fontPreview.style.fontFamily = `'${selectedFont}'`;
                    }
                    fontList.querySelectorAll('.cfg-mobile-dropdown-item').forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    fontList.classList.remove('open');
                });
            });

            // Mobile technique dropdown
            const techTrigger = document.getElementById('mobile-technique-trigger');
            const techList = document.getElementById('mobile-technique-list');
            const techPreview = document.getElementById('mobile-technique-preview');

            techTrigger?.addEventListener('click', (e) => {
                e.stopPropagation();
                techList?.classList.toggle('open');
                // Close font list
                fontList?.classList.remove('open');
            });

            techList?.querySelectorAll('.cfg-mobile-dropdown-item').forEach(item => {
                item.addEventListener('click', () => {
                    const technique = item.dataset.technique;
                    window.__SELECTED_TECHNIQUE = technique;

                    if (techPreview) techPreview.textContent = item.dataset.label;

                    // Update desktop selector
                    const desktopInput = document.getElementById('cfgTechniqueSelect');
                    if (desktopInput) desktopInput.value = technique;

                    techList.querySelectorAll('.cfg-mobile-dropdown-item').forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    techList.classList.remove('open');
                    updatePrice();
                });
            });

            // Color selection
            let selectedColor = '#1A1A2E';
            colorPalette?.querySelectorAll('.cfg-color-swatch').forEach(swatch => {
                swatch.addEventListener('click', () => {
                    colorPalette.querySelectorAll('.cfg-color-swatch').forEach(s => s.classList.remove('selected'));
                    swatch.classList.add('selected');
                    selectedColor = swatch.dataset.hex;
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.cfg-mobile-dropdown')) {
                    fontList?.classList.remove('open');
                    techList?.classList.remove('open');
                }
            });

            // Add text button
            addBtn?.addEventListener('click', () => {
                const text = textInput?.value.trim();
                if (text) {
                    addTextElement(text, {
                        fontFamily: selectedFont,
                        fill: selectedColor,
                    });
                    closeMobileDrawer();
                }
            });
        }

        if (tool === 'layers') {
            document.querySelectorAll('#mobile-layers .cfg-layer-item').forEach(item => {
                item.addEventListener('click', () => {
                    const el = state.elements.find(el => el.id === item.dataset.id);
                    if (el) {
                        selectElement(el);
                        closeMobileDrawer();
                    }
                });
            });
        }
    }

    // ===========================================
    // ZOOM
    // ===========================================
    function zoom(delta) {
        state.zoomLevel = Math.max(0.5, Math.min(2, state.zoomLevel + delta));
        state.stage.scale({ x: state.zoomLevel, y: state.zoomLevel });
        updateZoomDisplay();
        state.stage.batchDraw();
    }

    function resetZoom() {
        state.zoomLevel = 1;
        state.stage.scale({ x: 1, y: 1 });
        updateZoomDisplay();
        state.stage.batchDraw();
    }

    function updateZoomDisplay() {
        if (DOM.zoomLevel) {
            DOM.zoomLevel.textContent = Math.round(state.zoomLevel * 100) + '%';
        }
    }

    // ===========================================
    // SERIALIZATION
    // ===========================================
    function serialize() {
        return {
            productId: window.__PRODUCT_DATA?.id,
            elements: state.elements.map(el => ({
                id: el.id,
                type: el.type,
                position: el.position,
                properties: el.properties,
                view: el.view,
                visible: el.visible,
            })),
            technique: window.__SELECTED_TECHNIQUE,
            selectedColor: state.selectedProductColor,
            timestamp: Date.now(),
        };
    }

    /**
     * Generate a compressed share data string for URL
     */
    function generateShareData() {
        const data = serialize();
        // Encode to base64 for URL safety
        try {
            const jsonStr = JSON.stringify(data);
            return btoa(encodeURIComponent(jsonStr));
        } catch (e) {
            console.error('[Configurator] Failed to generate share data:', e);
            return '';
        }
    }

    function deserialize(data) {
        if (!data || !data.elements) return;

        data.elements.forEach(elData => {
            if (elData.type === 'text') {
                const stageWidth = state.stage.width();
                const stageHeight = state.stage.height();
                addTextElement(elData.properties.text, {
                    x: (elData.position.x / 100) * stageWidth,
                    y: (elData.position.y / 100) * stageHeight,
                    fontFamily: elData.properties.fontFamily,
                    fontSize: elData.properties.fontSize,
                    fill: elData.properties.fill,
                });
            }
            // TODO: Handle image deserialization
        });
    }

    function saveDraft() {
        try {
            const data = serialize();
            localStorage.setItem(CONFIG.autoSaveKey, JSON.stringify(data));
        } catch (e) {
            console.warn('[Configurator] Failed to save draft:', e);
        }
    }

    function loadDraft() {
        try {
            const saved = localStorage.getItem(CONFIG.autoSaveKey);
            if (saved) {
                const data = JSON.parse(saved);
                // Only restore if same product
                if (data.productId === window.__PRODUCT_DATA?.id) {
                    deserialize(data);
                    showNotification('Brouillon restauré', 'success');
                }
            }
        } catch (e) {
            console.warn('[Configurator] Failed to load draft:', e);
        }
    }

    function startAutoSave() {
        setInterval(() => {
            if (state.elements.length > 0) {
                saveDraft();
            }
        }, CONFIG.autoSaveInterval);
    }

    // ===========================================
    // EXPORT
    // ===========================================
    function exportToImage() {
        if (!state.stage) return null;

        // Hide transformers for export
        state.elements.forEach(el => el.transformer.visible(false));
        hideSnapGuides();

        const dataUrl = state.stage.toDataURL({
            pixelRatio: 2,
            mimeType: 'image/png',
        });

        // Restore transformers
        if (state.selectedElement) {
            state.selectedElement.transformer.visible(true);
        }

        return dataUrl;
    }

    function getCartData() {
        return {
            json: serialize(),
            preview: exportToImage(),
        };
    }

    // ===========================================
    // UTILITIES
    // ===========================================
    function generateId() {
        return 'el_' + Math.random().toString(36).substr(2, 9);
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function showNotification(message, type = 'info') {
        // Simple notification - can be enhanced
        console.log(`[Configurator] ${type}: ${message}`);
    }

    function updatePrice() {
        // Update price display based on technique
        const basePrice = window.__PRODUCT_DATA?.basePrice || 0;
        const techniquePrice = getTechniquePrice();
        const total = basePrice + techniquePrice;

        if (DOM.priceValue) {
            DOM.priceValue.textContent = formatPrice(total);
        }
        if (DOM.mobilePriceValue) {
            DOM.mobilePriceValue.textContent = formatPrice(total);
        }
    }

    function getTechniquePrice() {
        const techniques = window.__TECHNIQUES_DATA || [];
        const selected = window.__SELECTED_TECHNIQUE;
        const technique = techniques.find(t => t.value === selected);
        return technique?.price || 0;
    }

    function formatPrice(price) {
        return price.toFixed(2).replace('.', ',') + ' €';
    }

    function editTextInline(element) {
        // Create inline text editor
        const node = element.konvaNode;
        const stageBox = state.stage.container().getBoundingClientRect();
        const textPosition = node.getAbsolutePosition();

        const input = document.createElement('input');
        input.type = 'text';
        input.value = element.properties.text;
        input.style.cssText = `
            position: absolute;
            left: ${stageBox.left + textPosition.x}px;
            top: ${stageBox.top + textPosition.y}px;
            transform: translate(-50%, -50%);
            font-family: ${node.fontFamily()};
            font-size: ${node.fontSize() * state.zoomLevel}px;
            color: ${node.fill()};
            background: white;
            border: 2px solid #FF69B4;
            border-radius: 4px;
            padding: 4px 8px;
            text-align: center;
            outline: none;
            z-index: 1000;
        `;

        document.body.appendChild(input);
        input.focus();
        input.select();

        const finish = () => {
            const newText = input.value.trim() || 'Texte';
            element.properties.text = newText;
            node.text(newText);
            node.offsetX(node.width() / 2);
            node.offsetY(node.height() / 2);
            state.layer.batchDraw();
            saveDraft();
            updateLayersPanel();
            input.remove();
        };

        input.addEventListener('blur', finish);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                finish();
            }
            if (e.key === 'Escape') {
                input.remove();
            }
        });
    }

    // ===========================================
    // FALLBACK MODE (No Konva)
    // ===========================================
    function initFallbackMode() {
        console.log('[Configurator] Running in fallback mode (DOM-based)');
        // The existing product.php drag & drop will work as fallback
    }

    // ===========================================
    // EXPOSE API
    // ===========================================
    window.PersonnalyConfigurator = {
        init,
        addTextElement,
        addImageElement,
        deleteElement,
        serialize,
        deserialize,
        exportToImage,
        getCartData,
    };

    // Auto-init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
