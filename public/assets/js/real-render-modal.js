/**
 * PERSONNALY - Modal "Rendu Réel" par technique
 * Affiche des photos macro réelles pour rassurer le client
 * Charge les images depuis la base de données via API
 *
 * Usage:
 * PersonnalyRealRender.open('broderie');
 * PersonnalyRealRender.close();
 */

(function() {
    'use strict';

    // Configuration de fallback (descriptions par défaut si API non disponible)
    const fallbackConfig = {
        broderie: {
            label: 'Broderie',
            description: 'Fil brodé directement sur le textile. Relief et durabilité premium.'
        },
        flex: {
            label: 'Flex',
            description: 'Film thermocollant découpé. Rendu lisse et brillant.'
        },
        flock: {
            label: 'Flock',
            description: 'Effet velours au toucher. Texture douce et mate.'
        },
        sublimation: {
            label: 'Sublimation',
            description: 'Impression intégrée au tissu. Idéal pour polyester.'
        }
    };

    // Créer le conteneur du modal
    function createModalContainer() {
        if (document.getElementById('real-render-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'real-render-modal';
        modal.className = 'real-render-modal';
        modal.innerHTML = `
            <div class="rrm-backdrop"></div>
            <div class="rrm-content">
                <button class="rrm-close" aria-label="Fermer">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                <h2 class="rrm-title">Rendu réel – <span id="rrm-technique-name">Technique</span></h2>
                <p class="rrm-subtitle" id="rrm-technique-desc">Description de la technique</p>

                <div class="rrm-gallery" id="rrm-gallery">
                    <!-- Images chargées dynamiquement -->
                </div>

                <div class="rrm-disclaimer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <p>Ces images montrent un exemple réel de la technique sélectionnée.<br>
                    Le rendu final peut légèrement varier selon le textile, la couleur du support et la taille du marquage.</p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        injectStyles();
        initModalEvents();
    }

    // Styles CSS
    function injectStyles() {
        if (document.getElementById('real-render-modal-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'real-render-modal-styles';
        styles.textContent = `
            .real-render-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10001;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .real-render-modal.active {
                display: flex;
            }
            .rrm-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(13, 13, 13, 0.95);
                backdrop-filter: blur(10px);
            }
            .rrm-content {
                position: relative;
                z-index: 1;
                background: white;
                border-radius: 20px;
                padding: 32px;
                max-width: 800px;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
                animation: rrm-appear 0.3s ease;
            }
            @keyframes rrm-appear {
                from { opacity: 0; transform: scale(0.95) translateY(20px); }
                to { opacity: 1; transform: scale(1) translateY(0); }
            }
            .rrm-close {
                position: absolute;
                top: 16px;
                right: 16px;
                background: #f5f5f5;
                border: none;
                color: #333;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            .rrm-close:hover {
                background: #FF69B4;
                color: white;
            }
            .rrm-title {
                margin: 0 0 8px 0;
                font-size: 24px;
                font-weight: 700;
                color: #1a1a2e;
            }
            .rrm-title span {
                color: #FF69B4;
            }
            .rrm-subtitle {
                margin: 0 0 24px 0;
                font-size: 14px;
                color: #666;
            }
            .rrm-gallery {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
                margin-bottom: 24px;
            }
            .rrm-image-container {
                position: relative;
                border-radius: 12px;
                overflow: hidden;
                background: #f8f8f8;
                aspect-ratio: 1;
            }
            .rrm-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s;
            }
            .rrm-image-container:hover .rrm-image {
                transform: scale(1.05);
            }
            .rrm-image-zoom {
                position: absolute;
                bottom: 8px;
                right: 8px;
                background: rgba(0, 0, 0, 0.6);
                color: white;
                border: none;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.2s;
            }
            .rrm-image-container:hover .rrm-image-zoom {
                opacity: 1;
            }
            .rrm-image-zoom:hover {
                background: #FF69B4;
            }
            .rrm-disclaimer {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 16px;
                background: #f9f9f9;
                border-radius: 12px;
                border-left: 4px solid #FF69B4;
            }
            .rrm-disclaimer svg {
                flex-shrink: 0;
                color: #FF69B4;
                margin-top: 2px;
            }
            .rrm-disclaimer p {
                margin: 0;
                font-size: 13px;
                color: #555;
                line-height: 1.5;
            }
            .rrm-no-images {
                text-align: center;
                padding: 40px;
                color: #888;
            }
            .rrm-no-images svg {
                width: 48px;
                height: 48px;
                margin-bottom: 12px;
                opacity: 0.5;
            }

            /* Zoom plein écran */
            .rrm-fullscreen {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10002;
                background: rgba(0, 0, 0, 0.95);
                display: none;
                align-items: center;
                justify-content: center;
            }
            .rrm-fullscreen.active {
                display: flex;
            }
            .rrm-fullscreen img {
                max-width: 95vw;
                max-height: 95vh;
                object-fit: contain;
            }
            .rrm-fullscreen-close {
                position: absolute;
                top: 20px;
                right: 20px;
                background: rgba(255, 255, 255, 0.1);
                border: none;
                color: white;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .rrm-fullscreen-close:hover {
                background: #FF69B4;
            }

            @media (max-width: 600px) {
                .rrm-content {
                    padding: 24px 20px;
                    border-radius: 16px;
                }
                .rrm-title {
                    font-size: 20px;
                    padding-right: 40px;
                }
                .rrm-gallery {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 12px;
                }
                .rrm-close {
                    top: 12px;
                    right: 12px;
                    width: 36px;
                    height: 36px;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    // Événements du modal
    function initModalEvents() {
        const modal = document.getElementById('real-render-modal');
        const backdrop = modal.querySelector('.rrm-backdrop');
        const closeBtn = modal.querySelector('.rrm-close');

        backdrop.addEventListener('click', closeModal);
        closeBtn.addEventListener('click', closeModal);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Fermer d'abord le fullscreen s'il est ouvert
                const fullscreen = document.querySelector('.rrm-fullscreen.active');
                if (fullscreen) {
                    fullscreen.classList.remove('active');
                    fullscreen.remove();
                } else if (modal.classList.contains('active')) {
                    closeModal();
                }
            }
        });
    }

    // Charger les images depuis l'API
    async function loadTechniqueImages(technique) {
        try {
            const response = await fetch('/api/technique-images.php?technique=' + encodeURIComponent(technique));
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Erreur chargement images technique:', error);
            return null;
        }
    }

    // Ouvrir le modal
    async function openModal(technique) {
        createModalContainer();

        const modal = document.getElementById('real-render-modal');
        const gallery = document.getElementById('rrm-gallery');
        const titleSpan = document.getElementById('rrm-technique-name');
        const descP = document.getElementById('rrm-technique-desc');

        const techKey = technique.toLowerCase();
        const fallback = fallbackConfig[techKey] || { label: technique, description: '' };

        // Afficher le loading
        titleSpan.textContent = fallback.label;
        descP.textContent = fallback.description || 'Technique de personnalisation';
        gallery.innerHTML = `
            <div class="rrm-loading" style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">
                <div style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #FF69B4; border-radius: 50%; animation: rrm-spin 1s linear infinite; margin: 0 auto 12px;"></div>
                <p>Chargement des images...</p>
            </div>
        `;

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Charger depuis l'API
        const data = await loadTechniqueImages(technique);

        if (data && data.success) {
            titleSpan.textContent = data.label || fallback.label;
            descP.textContent = data.description || fallback.description || 'Technique de personnalisation';

            if (data.images && data.images.length > 0) {
                const imagesHtml = data.images.map(imgUrl => {
                    const fullPath = '/public' + imgUrl;
                    return `
                        <div class="rrm-image-container">
                            <img src="${fullPath}" alt="${data.label} - exemple" class="rrm-image"
                                 onerror="this.parentElement.style.display='none'">
                            <button class="rrm-image-zoom" data-src="${fullPath}" aria-label="Agrandir">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    <line x1="11" y1="8" x2="11" y2="14"/>
                                    <line x1="8" y1="11" x2="14" y2="11"/>
                                </svg>
                            </button>
                        </div>
                    `;
                }).join('');

                gallery.innerHTML = imagesHtml;

                // Événements zoom
                gallery.querySelectorAll('.rrm-image-zoom').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        openFullscreen(this.dataset.src);
                    });
                });

                gallery.querySelectorAll('.rrm-image').forEach(img => {
                    img.addEventListener('click', function() {
                        openFullscreen(this.src);
                    });
                    img.style.cursor = 'zoom-in';
                });
            } else {
                gallery.innerHTML = `
                    <div class="rrm-no-images">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21,15 16,10 5,21"/>
                        </svg>
                        <p>Images de référence bientôt disponibles pour cette technique.</p>
                    </div>
                `;
            }
        } else {
            gallery.innerHTML = `
                <div class="rrm-no-images">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21,15 16,10 5,21"/>
                    </svg>
                    <p>Images de référence non disponibles pour cette technique.</p>
                </div>
            `;
        }
    }

    // Ouvrir en plein écran
    function openFullscreen(src) {
        // Supprimer l'ancien s'il existe
        const existing = document.querySelector('.rrm-fullscreen');
        if (existing) existing.remove();

        const fullscreen = document.createElement('div');
        fullscreen.className = 'rrm-fullscreen active';
        fullscreen.innerHTML = `
            <img src="${src}" alt="Vue agrandie">
            <button class="rrm-fullscreen-close" aria-label="Fermer">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        `;

        document.body.appendChild(fullscreen);

        // Fermer au clic
        fullscreen.addEventListener('click', function() {
            fullscreen.classList.remove('active');
            setTimeout(() => fullscreen.remove(), 200);
        });

        fullscreen.querySelector('.rrm-fullscreen-close').addEventListener('click', function(e) {
            e.stopPropagation();
            fullscreen.classList.remove('active');
            setTimeout(() => fullscreen.remove(), 200);
        });
    }

    // Fermer le modal
    function closeModal() {
        const modal = document.getElementById('real-render-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // API globale
    window.PersonnalyRealRender = {
        open: openModal,
        close: closeModal
    };

    // Auto-init
    document.addEventListener('DOMContentLoaded', createModalContainer);

})();
