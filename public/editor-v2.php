<?php
/**
 * PERSONNALY - Éditeur V2
 * Page de chargement - Toutes les données viennent de l'API
 *
 * ARCHITECTURE :
 * - Cette page charge uniquement le shell HTML/CSS/JS
 * - Les données produit sont chargées via /public/api/editor/product.php
 * - Les designs/éléments sont chargés via /public/api/editor/assets.php
 * - Aucune donnée métier n'est hardcodée ici
 */

// Récupérer l'ID produit depuis l'URL
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 1;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Personnaliser | Personnaly</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">

    <!-- CSS Global Personnaly (variables + base) - DOIT être chargé EN PREMIER -->
    <link rel="stylesheet" href="/public/assets/css/style.css">

    <!-- Editor V2 CSS (utilise les variables globales) -->
    <link rel="stylesheet" href="/editor-v2/editor.css">
</head>
<body>

<!-- FONT PRELOAD REGISTRY (invisible, force le navigateur à télécharger les polices) -->
<div id="ps-font-preload" aria-hidden="true"></div>

<!-- HEADER -->
<header class="ps-header">
    <div class="ps-header-left">
        <h1 id="productTitle">Chargement...</h1>
        <span class="ps-badge">V2</span>
    </div>
    <a href="/" class="ps-back-link">Retour</a>
</header>

<!-- EDITOR -->
<div class="ps-editor" id="editor">

    <!-- PREVIEW -->
    <div class="ps-preview">
        <!-- View Toggle - EN HAUT avec indicateur -->
        <div class="ps-view-toggle-header">
            <button class="ps-view-btn active" data-view="front">
                <span class="ps-view-indicator"></span>
                Face
            </button>
            <button class="ps-view-btn" data-view="back">
                <span class="ps-view-indicator"></span>
                Dos
            </button>
        </div>

        <div class="ps-product-frame">
            <img class="ps-product-image" src="/editor-v2/tshirt-front.svg" alt="Produit" id="productImage">
            <div class="ps-print-area" id="printArea">
                <!-- Layers dynamiques -->
            </div>
            <div class="ps-print-area-debug" id="printAreaDebug"></div>
        </div>

        <!-- Preview Actions - SOUS le cadre, centré -->
        <div class="ps-preview-actions">
            <button class="ps-btn ps-btn-secondary ps-btn-sm" id="btnPreview">
                Voir le rendu réel
            </button>
            <button class="ps-btn ps-btn-primary ps-btn-sm" id="btnShare">
                📤 Partager
            </button>
        </div>
    </div>

    <!-- Close Preview (hidden by default) -->
    <button class="ps-btn ps-btn-ghost ps-preview-close" id="btnClosePreview" style="display:none;">
        Fermer l'aperçu
    </button>

    <!-- CONTROLS -->
    <div class="ps-controls">

        <!-- TABS -->
        <nav class="ps-tabs">
            <button class="ps-tab active" data-tab="text">
                <span class="ps-tab-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7V4h16v3"></path>
                        <path d="M9 20h6"></path>
                        <path d="M12 4v16"></path>
                    </svg>
                </span>
                Texte
            </button>
            <button class="ps-tab" data-tab="designs">
                <span class="ps-tab-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                </span>
                Design
            </button>
            <button class="ps-tab" data-tab="elements">
                <span class="ps-tab-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    </svg>
                </span>
                Éléments
            </button>
            <button class="ps-tab" data-tab="layers">
                <span class="ps-tab-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5"></path>
                        <path d="M2 12l10 5 10-5"></path>
                    </svg>
                </span>
                Calques
            </button>
        </nav>

        <!-- TAB CONTENT -->
        <div class="ps-tab-content">

            <!-- PRODUCT CONFIGURATION (always visible at top) -->
            <div class="ps-form-group" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #E5E7EB;">
                <label class="ps-label">👕 Configuration du produit</label>

                <!-- Sélecteur de couleur -->
                <div class="ps-form-group" style="margin-top: 15px;">
                    <label class="ps-label" style="font-size: 13px; margin-bottom: 8px;">Couleur</label>
                    <div id="colorSelector" class="ps-color-selector">
                        <!-- Couleurs générées par JS -->
                    </div>
                </div>

                <!-- Sélecteur de taille -->
                <div class="ps-form-group" style="margin-top: 15px;">
                    <label class="ps-label" style="font-size: 13px; margin-bottom: 8px;">Taille</label>
                    <select class="ps-select" id="sizeSelector">
                        <option value="">Sélectionnez une taille</option>
                        <!-- Tailles générées par JS -->
                    </select>
                </div>
            </div>

            <!-- TAB: TEXTE -->
            <div class="ps-panel active" id="panel-text">

                <div class="ps-form-group">
                    <label class="ps-label">Votre texte</label>
                    <input type="text" class="ps-input" id="textInput" placeholder="Entrez votre texte...">
                </div>

                <!-- Custom Select: Police -->
                <div class="ps-form-group">
                    <label class="ps-label">Police</label>
                    <div class="ps-custom-select" id="fontSelector" data-value="">
                        <div class="ps-custom-select-trigger">
                            <span class="ps-custom-select-text">Choisir une police</span>
                            <span class="ps-custom-select-arrow">▼</span>
                        </div>
                    </div>
                </div>

                <div class="ps-form-group">
                    <label class="ps-label">Taille</label>
                    <input type="range" class="ps-range" id="fontSize" min="12" max="72" value="24">
                </div>

                <div class="ps-form-group">
                    <label class="ps-label">Couleur</label>
                    <div class="ps-color-swatches" id="colorSwatches">
                        <!-- Couleurs générées dynamiquement depuis l'API -->
                    </div>
                </div>

                <div class="ps-form-group">
                    <label class="ps-label">Alignement</label>
                    <div class="ps-align-group">
                        <button class="ps-align-btn active" data-align="left" title="Gauche">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm0 4h12v2H3V7zm0 4h18v2H3v-2zm0 4h12v2H3v-2zm0 4h18v2H3v-2z"/></svg>
                        </button>
                        <button class="ps-align-btn" data-align="center" title="Centre">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm3 4h12v2H6V7zm-3 4h18v2H3v-2zm3 4h12v2H6v-2zm-3 4h18v2H3v-2z"/></svg>
                        </button>
                        <button class="ps-align-btn" data-align="right" title="Droite">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3V3zm6 4h12v2H9V7zm-6 4h18v2H3v-2zm6 4h12v2H9v-2zm-6 4h18v2H3v-2z"/></svg>
                        </button>
                    </div>
                </div>

                <button class="ps-btn ps-btn-primary ps-btn-block" id="btnAddText">
                    + Ajouter le texte
                </button>

                <!-- Contrôles du texte actif (desktop) -->
                <div class="ps-text-controls disabled" id="textLayerControls">
                    <div class="ps-text-controls-header">
                        <span class="ps-text-controls-title">Modifier le texte</span>
                        <button class="ps-btn-delete" data-action="delete" title="Supprimer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="ps-text-controls-row">
                        <div class="ps-text-controls-group">
                            <label class="ps-label">Style</label>
                            <div class="ps-style-btns">
                                <button class="ps-style-btn" data-action="bold" title="Gras">B</button>
                                <button class="ps-style-btn" data-action="italic" title="Italique"><i>I</i></button>
                            </div>
                        </div>
                        <div class="ps-text-controls-group">
                            <label class="ps-label">Dimensions</label>
                            <div class="ps-size-btns">
                                <button class="ps-size-btn" data-action="decrease" title="Réduire">−</button>
                                <button class="ps-size-btn" data-action="increase" title="Agrandir">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="ps-text-controls-group">
                        <label class="ps-label">Rotation <span id="rotationValue">0°</span></label>
                        <input type="range" class="ps-range" id="rotationSlider" min="-180" max="180" value="0">
                    </div>

                    <div class="ps-text-controls-group">
                        <label class="ps-label">Déplacer</label>
                        <div class="ps-move-grid">
                            <button class="ps-move-btn" data-direction="up" title="Haut">↑</button>
                            <button class="ps-move-btn" data-direction="left" title="Gauche">←</button>
                            <button class="ps-move-btn" data-direction="right" title="Droite">→</button>
                            <button class="ps-move-btn" data-direction="down" title="Bas">↓</button>
                        </div>
                    </div>

                    <div class="ps-text-controls-empty">
                        <span>Sélectionnez un texte pour le modifier</span>
                    </div>
                </div>

                <!-- Custom Select: Technique d'impression -->
                <div class="ps-form-group" style="margin-top: 24px;">
                    <label class="ps-label">Technique d'impression</label>
                    <div class="ps-custom-select" id="techniqueSelector" data-value="">
                        <div class="ps-custom-select-trigger">
                            <span class="ps-custom-select-text">Choisir une technique</span>
                            <span class="ps-custom-select-arrow">▼</span>
                        </div>
                    </div>
                </div>

                <!-- Prix -->
                <div class="ps-price-card">
                    <div class="ps-price-line">
                        <span>Prix de base</span>
                        <span id="priceBase">--,-- €</span>
                    </div>
                    <div class="ps-price-line">
                        <span>Technique</span>
                        <span id="priceTechnique">0,00 €</span>
                    </div>
                    <div class="ps-price-line ps-price-total">
                        <span>Total</span>
                        <span id="priceTotal">--,-- €</span>
                    </div>
                </div>

            </div>

            <!-- TAB: DESIGNS -->
            <div class="ps-panel" id="panel-designs">

                <p class="ps-label">Choisir un design</p>
                <div class="ps-grid" id="designsGrid">
                    <div class="ps-grid-loading">Chargement...</div>
                </div>
            </div>

            <!-- TAB: ELEMENTS -->
            <div class="ps-panel" id="panel-elements">
                <p class="ps-label">Ajouter un élément</p>
                <div class="ps-grid" id="elementsGrid">
                    <div class="ps-grid-loading">Chargement...</div>
                </div>
            </div>

            <!-- TAB: CALQUES -->
            <div class="ps-panel" id="panel-layers">
                <div class="ps-layers-list" id="layersList">
                    <!-- Calques générés dynamiquement -->
                </div>
                <div class="ps-empty" id="layersEmpty">
                    <div class="ps-empty-icon">📁</div>
                    <p class="ps-empty-text">Aucun calque.<br>Ajoutez du texte ou un design.</p>
                </div>
            </div>

        </div>

        <!-- CTA STICKY -->
        <div class="ps-cta-sticky">
            <div class="ps-cta-price">
                <div class="ps-cta-price-label">Total</div>
                <div class="ps-cta-price-value" id="ctaPrice">--,-- €</div>
            </div>
            <button class="ps-btn ps-btn-primary" id="btnAddToCart">
                Ajouter au panier
            </button>
        </div>

    </div>

</div>

<!-- Bottom Sheet pour selects custom -->
<div class="ps-bottomsheet-overlay" id="bottomsheetOverlay"></div>
<div class="ps-bottomsheet" id="bottomsheet">
    <div class="ps-bottomsheet-header">
        <h3 class="ps-bottomsheet-title" id="bottomsheetTitle">Sélection</h3>
        <button class="ps-bottomsheet-close" id="bottomsheetClose">×</button>
    </div>
    <div class="ps-bottomsheet-content" id="bottomsheetContent">
        <!-- Options générées dynamiquement -->
    </div>
</div>

<!-- Loading Overlay -->
<div class="ps-loading" id="loadingOverlay">
    <div class="ps-loading-spinner"></div>
    <p>Chargement de l'éditeur...</p>
</div>

<!-- MODAL PARTAGE -->
<div id="shareModal" class="ps-share-modal">
    <div class="ps-share-overlay"></div>
    <div class="ps-share-content">
        <div class="ps-share-header">
            <h3>Partager mon design</h3>
            <button class="ps-share-close" id="btnCloseShare">&times;</button>
        </div>
        <div class="ps-share-body">
            <p class="ps-share-text">Partagez votre création avec vos amis !</p>
            <div class="ps-share-buttons">
                <button class="ps-share-btn ps-share-facebook" id="btnShareFacebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <span>Facebook</span>
                </button>
                <button class="ps-share-btn ps-share-twitter" id="btnShareTwitter">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    <span>Twitter / X</span>
                </button>
                <button class="ps-share-btn ps-share-whatsapp" id="btnShareWhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    <span>WhatsApp</span>
                </button>
                <button class="ps-share-btn ps-share-copy" id="btnCopyLink">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                    <span>Copier le lien</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Configuration initiale -->
<script>
window.__EDITOR_CONFIG = {
    productId: <?= $productId ?>,
    apiBase: '/public/api'
};
</script>

<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="/editor-v2/editor.js"></script>

</body>
</html>
