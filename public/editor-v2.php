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

            <!-- PRODUCT COLORS (always visible at top) -->
            <div class="ps-product-colors-wrapper" id="productColorsWrapper" style="display: none;">
                <div class="ps-product-colors" id="productColorSelector"></div>
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

<!-- Configuration initiale -->
<script>
window.__EDITOR_CONFIG = {
    productId: <?= $productId ?>,
    apiBase: '/public/api'
};
</script>

<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="/editor-v2/editor.js"></script>

</body>
</html>
