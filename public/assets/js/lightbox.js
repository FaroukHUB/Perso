/**
 * PERSONNALY - Lightbox Component v2
 * Extension du configurateur : même position, même zone, drag actif
 *
 * CHANGEMENT DE DIRECTION (2026-01):
 * - Le configurateur est INDICATIF, pas photoréaliste
 * - Aucun effet matière CSS
 * - Texte propre, lisible, position fidèle
 */

(function() {
    'use strict';

    // État du composant
    let currentZoom = 1;
    const minZoom = 0.5;
    const maxZoom = 3;
    const zoomStep = 0.25;

    // État du drag & drop
    let isDragging = false;
    let startX, startY;
    let currentX, currentY;
    let printZone = null;
    let onPositionChange = null;

    // Créer le conteneur Lightbox
    function createLightboxContainer() {
        if (document.getElementById('personnaly-lightbox')) return;

        const lightbox = document.createElement('div');
        lightbox.id = 'personnaly-lightbox';
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-backdrop"></div>
            <div class="lightbox-content">
                <button class="lightbox-close" aria-label="Fermer">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <div class="lightbox-image-container" id="lightbox-container">
                    <img src="" alt="Preview" class="lightbox-image" id="lightbox-img">
                    <!-- Zone d'impression (même structure que configurateur) -->
                    <div class="lightbox-print-zone" id="lightbox-print-zone">
                        <span class="lightbox-zone-label"></span>
                    </div>
                    <!-- Texte draggable -->
                    <span class="lightbox-text" id="lightbox-text"></span>
                </div>
                <div class="lightbox-controls">
                    <button class="lightbox-zoom-btn" data-action="zoom-out" aria-label="Dézoomer">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            <line x1="8" y1="11" x2="14" y2="11"/>
                        </svg>
                    </button>
                    <span class="lightbox-zoom-level">100%</span>
                    <button class="lightbox-zoom-btn" data-action="zoom-in" aria-label="Zoomer">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            <line x1="11" y1="8" x2="11" y2="14"/>
                            <line x1="8" y1="11" x2="14" y2="11"/>
                        </svg>
                    </button>
                </div>
                <p class="lightbox-hint">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 9l-3 3 3 3"/>
                        <path d="M9 5l3-3 3 3"/>
                        <path d="M15 19l-3 3-3-3"/>
                        <path d="M19 9l3 3-3 3"/>
                    </svg>
                    Glissez le texte pour ajuster sa position
                </p>
            </div>
        `;
        document.body.appendChild(lightbox);
        injectStyles();
        initLightboxEvents();
    }

    // Styles CSS
    function injectStyles() {
        if (document.getElementById('lightbox-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'lightbox-styles';
        styles.textContent = `
            .lightbox {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10000;
                display: none;
                align-items: center;
                justify-content: center;
            }
            .lightbox.active {
                display: flex;
            }
            .lightbox-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(13, 13, 13, 0.95);
                backdrop-filter: blur(10px);
            }
            .lightbox-content {
                position: relative;
                z-index: 1;
                max-width: 90vw;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                animation: lightbox-appear 0.3s ease;
            }
            @keyframes lightbox-appear {
                from { opacity: 0; transform: scale(0.9); }
                to { opacity: 1; transform: scale(1); }
            }
            .lightbox-close {
                position: absolute;
                top: -40px;
                right: 0;
                background: rgba(255, 255, 255, 0.1);
                border: none;
                color: white;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            .lightbox-close:hover {
                background: #FF69B4;
            }
            .lightbox-image-container {
                position: relative;
                background: white;
                border-radius: 16px;
                overflow: hidden;
                touch-action: none;
            }
            .lightbox-image {
                display: block;
                max-width: 80vw;
                max-height: 70vh;
                object-fit: contain;
                border-radius: 8px;
                transition: transform 0.2s;
                user-select: none;
                -webkit-user-drag: none;
            }

            /* Zone d'impression - visible au drag */
            .lightbox-print-zone {
                position: absolute;
                border: 2px dashed transparent;
                border-radius: 8px;
                pointer-events: none;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }
            .lightbox-print-zone.active {
                border-color: rgba(255, 105, 180, 0.6);
                background: rgba(255, 105, 180, 0.08);
            }
            .lightbox-zone-label {
                position: absolute;
                top: -22px;
                left: 50%;
                transform: translateX(-50%);
                font-size: 10px;
                color: #FF69B4;
                background: white;
                padding: 2px 8px;
                border-radius: 4px;
                white-space: nowrap;
                opacity: 0;
                transition: opacity 0.3s;
            }
            .lightbox-print-zone.active .lightbox-zone-label {
                opacity: 1;
            }

            /* Texte - style INDICATIF (propre, lisible, pas d'effet matière) */
            .lightbox-text {
                position: absolute;
                font-weight: 600;
                white-space: nowrap;
                transform: translate(-50%, -50%);
                cursor: grab;
                user-select: none;
                transition: transform 0.05s ease-out;
                /* Ombre légère pour lisibilité sur tous fonds */
                text-shadow:
                    0 1px 2px rgba(0, 0, 0, 0.1),
                    0 0 1px rgba(255, 255, 255, 0.8);
            }
            .lightbox-text:active,
            .lightbox-text.dragging {
                cursor: grabbing;
                transform: translate(-50%, -50%) scale(1.02);
            }
            .lightbox-text.empty {
                opacity: 0.4;
                font-style: italic;
            }

            /* Indicateur technique - simple badge */
            .lightbox-technique-badge {
                position: absolute;
                bottom: 12px;
                left: 50%;
                transform: translateX(-50%);
                padding: 4px 12px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                border-radius: 12px;
            }

            .lightbox-controls {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-top: 20px;
                background: rgba(255, 255, 255, 0.1);
                padding: 10px 20px;
                border-radius: 30px;
            }
            .lightbox-zoom-btn {
                background: transparent;
                border: none;
                color: white;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            .lightbox-zoom-btn:hover {
                background: rgba(255, 105, 180, 0.3);
            }
            .lightbox-zoom-level {
                color: white;
                font-size: 14px;
                font-weight: 600;
                min-width: 50px;
                text-align: center;
            }
            .lightbox-hint {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 12px;
                color: rgba(255, 255, 255, 0.6);
                font-size: 12px;
            }
            .lightbox-hint svg {
                opacity: 0.6;
            }

            @media (max-width: 768px) {
                .lightbox-content {
                    max-width: 95vw;
                }
                .lightbox-image {
                    max-width: 90vw;
                    max-height: 60vh;
                }
                .lightbox-close {
                    top: -45px;
                    right: 5px;
                }
                .lightbox-hint {
                    font-size: 11px;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    // Initialiser les événements
    function initLightboxEvents() {
        const lightbox = document.getElementById('personnaly-lightbox');
        const backdrop = lightbox.querySelector('.lightbox-backdrop');
        const closeBtn = lightbox.querySelector('.lightbox-close');
        const img = document.getElementById('lightbox-img');
        const container = document.getElementById('lightbox-container');
        const textEl = document.getElementById('lightbox-text');
        const zoomLevel = lightbox.querySelector('.lightbox-zoom-level');

        // Fermer
        backdrop.addEventListener('click', closeLightbox);
        closeBtn.addEventListener('click', closeLightbox);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.classList.contains('active')) {
                closeLightbox();
            }
        });

        // Zoom buttons
        lightbox.querySelectorAll('.lightbox-zoom-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.action;
                if (action === 'zoom-in' && currentZoom < maxZoom) {
                    currentZoom += zoomStep;
                } else if (action === 'zoom-out' && currentZoom > minZoom) {
                    currentZoom -= zoomStep;
                }
                updateZoom();
            });
        });

        // Zoom molette
        container.addEventListener('wheel', function(e) {
            e.preventDefault();
            if (e.deltaY < 0 && currentZoom < maxZoom) {
                currentZoom += zoomStep;
            } else if (e.deltaY > 0 && currentZoom > minZoom) {
                currentZoom -= zoomStep;
            }
            updateZoom();
        });

        function updateZoom() {
            img.style.transform = `scale(${currentZoom})`;
            zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
        }

        // === DRAG & DROP ===

        function getEventCoords(e) {
            if (e.touches && e.touches.length > 0) {
                return { x: e.touches[0].clientX, y: e.touches[0].clientY };
            }
            return { x: e.clientX, y: e.clientY };
        }

        function constrainToZone(x, y) {
            if (!printZone) return { x, y };

            const minX = printZone.x;
            const maxX = printZone.x + printZone.width;
            x = Math.max(minX, Math.min(maxX, x));

            const minY = printZone.y;
            const maxY = printZone.y + printZone.height;
            y = Math.max(minY, Math.min(maxY, y));

            return { x, y };
        }

        function startDrag(e) {
            if (textEl.classList.contains('empty')) return;

            e.preventDefault();
            e.stopPropagation();
            isDragging = true;

            const coords = getEventCoords(e);
            startX = coords.x;
            startY = coords.y;

            textEl.classList.add('dragging');

            // Afficher la zone d'impression
            const zoneEl = document.getElementById('lightbox-print-zone');
            if (zoneEl) zoneEl.classList.add('active');
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();

            const coords = getEventCoords(e);
            const rect = container.getBoundingClientRect();

            // Position en % du container
            let newX = ((coords.x - rect.left) / rect.width) * 100;
            let newY = ((coords.y - rect.top) / rect.height) * 100;

            // Contraindre à la zone d'impression
            const constrained = constrainToZone(newX, newY);
            currentX = constrained.x;
            currentY = constrained.y;

            // Mettre à jour la position
            textEl.style.left = currentX + '%';
            textEl.style.top = currentY + '%';
        }

        function endDrag(e) {
            if (!isDragging) return;

            isDragging = false;
            textEl.classList.remove('dragging');

            // Masquer la zone d'impression
            const zoneEl = document.getElementById('lightbox-print-zone');
            if (zoneEl) zoneEl.classList.remove('active');

            // Callback pour synchroniser avec le configurateur
            if (onPositionChange && typeof onPositionChange === 'function') {
                onPositionChange(currentX, currentY);
            }
        }

        // Touch events (mobile)
        textEl.addEventListener('touchstart', startDrag, { passive: false });
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('touchend', endDrag, { passive: true });

        // Mouse events (desktop)
        textEl.addEventListener('mousedown', startDrag);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', endDrag);

        // Empêcher le drag natif
        textEl.addEventListener('dragstart', e => e.preventDefault());
    }

    // Ouvrir la lightbox
    function openLightbox(options) {
        createLightboxContainer();

        const lightbox = document.getElementById('personnaly-lightbox');
        const img = document.getElementById('lightbox-img');
        const textEl = document.getElementById('lightbox-text');
        const zoneEl = document.getElementById('lightbox-print-zone');
        const zoneLabelEl = zoneEl.querySelector('.lightbox-zone-label');
        const zoomLevelEl = lightbox.querySelector('.lightbox-zoom-level');

        // Reset zoom
        currentZoom = 1;
        img.style.transform = 'scale(1)';
        zoomLevelEl.textContent = '100%';

        // Image
        img.src = options.imageSrc || '';
        img.alt = options.imageAlt || 'Preview';

        // Zone d'impression
        printZone = options.printZone || null;
        if (printZone) {
            zoneEl.style.left = printZone.x + '%';
            zoneEl.style.top = printZone.y + '%';
            zoneEl.style.width = printZone.width + '%';
            zoneEl.style.height = printZone.height + '%';
            zoneLabelEl.textContent = printZone.label || 'Zone d\'impression';
            zoneEl.style.display = 'block';
        } else {
            zoneEl.style.display = 'none';
        }

        // Position actuelle
        currentX = options.textX || 50;
        currentY = options.textY || 50;

        // Callback de synchronisation
        onPositionChange = options.onPositionChange || null;

        // Texte personnalisé
        if (options.text) {
            textEl.style.display = 'block';
            textEl.textContent = options.text;
            textEl.style.fontFamily = options.font || 'Poppins, sans-serif';
            textEl.style.fontSize = options.fontSize || '2rem';
            textEl.style.left = currentX + '%';
            textEl.style.top = currentY + '%';
            textEl.style.color = options.textColor || '#FF1493';
            textEl.classList.remove('empty');
        } else {
            textEl.style.display = 'none';
        }

        // Afficher
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Fermer la lightbox
    function closeLightbox() {
        const lightbox = document.getElementById('personnaly-lightbox');
        if (lightbox) {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';

            // Reset état
            isDragging = false;
            const zoneEl = document.getElementById('lightbox-print-zone');
            if (zoneEl) zoneEl.classList.remove('active');
        }
    }

    // Mettre à jour le texte depuis l'extérieur
    function updateText(options) {
        const textEl = document.getElementById('lightbox-text');
        if (!textEl) return;

        if (options.text !== undefined) {
            textEl.textContent = options.text;
            textEl.classList.toggle('empty', !options.text);
        }
        if (options.font) textEl.style.fontFamily = options.font;
        if (options.fontSize) textEl.style.fontSize = options.fontSize;
        if (options.textColor) textEl.style.color = options.textColor;
        if (options.textX !== undefined) {
            currentX = options.textX;
            textEl.style.left = currentX + '%';
        }
        if (options.textY !== undefined) {
            currentY = options.textY;
            textEl.style.top = currentY + '%';
        }
    }

    // Exposer l'API globale
    window.PersonnalyLightbox = {
        open: openLightbox,
        close: closeLightbox,
        updateText: updateText
    };

    // Auto-init au chargement
    document.addEventListener('DOMContentLoaded', createLightboxContainer);

})();
