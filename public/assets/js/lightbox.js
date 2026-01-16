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
                    <span class="lightbox-technique-indicator" id="lightbox-technique"></span>
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
                font-weight: 600;
                color: #FF1493;
                white-space: nowrap;
                pointer-events: none;
                transform: translate(-50%, -50%);
                transition: all 0.3s ease;
            }
            /* Techniques - styles visuels distincts */
            .lightbox-text.technique-broderie {
                text-shadow:
                    1px 1px 0px rgba(0, 0, 0, 0.3),
                    2px 2px 0px rgba(0, 0, 0, 0.2),
                    -0.5px -0.5px 0px rgba(255, 255, 255, 0.4),
                    3px 3px 2px rgba(0, 0, 0, 0.15);
                -webkit-text-stroke: 0.3px rgba(0, 0, 0, 0.1);
                font-weight: 700;
                letter-spacing: 0.5px;
            }
            .lightbox-text.technique-flex {
                text-shadow: none;
                -webkit-font-smoothing: antialiased;
                filter: contrast(1.05) brightness(1.02);
                font-weight: 600;
                letter-spacing: 0.3px;
            }
            .lightbox-text.technique-flock {
                text-shadow:
                    0 0 2px currentColor,
                    0 0 4px rgba(0, 0, 0, 0.1);
                filter: blur(0.2px) contrast(0.95);
                opacity: 0.95;
                font-weight: 600;
            }
            .lightbox-text.technique-sublimation {
                text-shadow: none;
                opacity: 0.85;
                filter: blur(0.3px) saturate(0.9);
                mix-blend-mode: multiply;
                font-weight: 500;
            }
            .lightbox-technique-indicator {
                position: absolute;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                padding: 6px 16px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                border-radius: 20px;
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

    // Labels des techniques pour l'indicateur
    const techniqueLabels = {
        'flex': 'FLEX',
        'flock': 'FLOCK',
        'broderie': 'BRODERIE',
        'sublimation': 'SUBLIMATION'
    };

    // Ouvrir la lightbox
    function openLightbox(options) {
        createLightboxContainer();

        const lightbox = document.getElementById('personnaly-lightbox');
        const img = document.getElementById('lightbox-img');
        const textEl = document.getElementById('lightbox-text');
        const techniqueEl = document.getElementById('lightbox-technique');
        const zoomLevel = lightbox.querySelector('.lightbox-zoom-level');

        // Reset zoom
        currentZoom = 1;
        img.style.transform = 'scale(1)';
        zoomLevel.textContent = '100%';

        // Image
        img.src = options.imageSrc || '';
        img.alt = options.imageAlt || 'Preview';

        // Technique - retirer les anciennes classes
        textEl.classList.remove('technique-flex', 'technique-flock', 'technique-broderie', 'technique-sublimation');

        // Technique - appliquer la nouvelle classe
        const technique = options.technique || 'flex';
        textEl.classList.add('technique-' + technique);

        // Indicateur de technique
        if (options.text && techniqueEl) {
            techniqueEl.style.display = 'block';
            techniqueEl.textContent = techniqueLabels[technique] || technique.toUpperCase();
        } else if (techniqueEl) {
            techniqueEl.style.display = 'none';
        }

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
