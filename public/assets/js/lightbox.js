/**
 * PERSONNALY - Lightbox Component
 * Modal plein écran pour preview produit avec texte personnalisé
 * Mobile-first avec support pinch-zoom
 */

(function() {
    'use strict';

    // Créer le conteneur Lightbox si non existant
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
                <div class="lightbox-image-container">
                    <img src="" alt="Preview" class="lightbox-image" id="lightbox-img">
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
            </div>
        `;
        document.body.appendChild(lightbox);
        injectStyles();
        initLightboxEvents();
    }

    // Injecter les styles CSS
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
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
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
                padding: 20px;
                overflow: hidden;
                touch-action: pinch-zoom;
            }
            .lightbox-image {
                max-width: 80vw;
                max-height: 70vh;
                object-fit: contain;
                border-radius: 8px;
                transition: transform 0.2s;
            }
            .lightbox-text {
                position: absolute;
                font-weight: 700;
                color: #FF1493;
                text-shadow: 1px 1px 2px rgba(255,255,255,0.9);
                white-space: nowrap;
                pointer-events: none;
                transform: translate(-50%, -50%);
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
            }
        `;
        document.head.appendChild(styles);
    }

    // État du zoom
    let currentZoom = 1;
    const minZoom = 0.5;
    const maxZoom = 3;
    const zoomStep = 0.25;

    // Initialiser les événements
    function initLightboxEvents() {
        const lightbox = document.getElementById('personnaly-lightbox');
        const backdrop = lightbox.querySelector('.lightbox-backdrop');
        const closeBtn = lightbox.querySelector('.lightbox-close');
        const img = document.getElementById('lightbox-img');
        const zoomLevel = lightbox.querySelector('.lightbox-zoom-level');

        // Fermer sur clic backdrop
        backdrop.addEventListener('click', closeLightbox);

        // Fermer sur bouton
        closeBtn.addEventListener('click', closeLightbox);

        // Fermer sur Escape
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
        lightbox.querySelector('.lightbox-image-container').addEventListener('wheel', function(e) {
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
    }

    // Ouvrir la lightbox
    function openLightbox(options) {
        createLightboxContainer();

        const lightbox = document.getElementById('personnaly-lightbox');
        const img = document.getElementById('lightbox-img');
        const textEl = document.getElementById('lightbox-text');
        const zoomLevel = lightbox.querySelector('.lightbox-zoom-level');

        // Reset zoom
        currentZoom = 1;
        img.style.transform = 'scale(1)';
        zoomLevel.textContent = '100%';

        // Image
        img.src = options.imageSrc || '';
        img.alt = options.imageAlt || 'Preview';

        // Texte personnalisé
        if (options.text) {
            textEl.style.display = 'block';
            textEl.textContent = options.text;
            textEl.style.fontFamily = options.font || 'Poppins, sans-serif';
            textEl.style.fontSize = options.fontSize || '2rem';
            textEl.style.left = (options.textX || 50) + '%';
            textEl.style.top = (options.textY || 50) + '%';
            textEl.style.color = options.textColor || '#FF1493';
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
        }
    }

    // Exposer l'API globale
    window.PersonnalyLightbox = {
        open: openLightbox,
        close: closeLightbox
    };

    // Auto-init au chargement
    document.addEventListener('DOMContentLoaded', createLightboxContainer);

})();
