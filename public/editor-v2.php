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
                <span class="ps-tab-icon">T</span>
                Texte
            </button>
            <button class="ps-tab" data-tab="designs">
                <span class="ps-tab-icon">★</span>
                Design
            </button>
            <button class="ps-tab" data-tab="elements">
                <span class="ps-tab-icon">■</span>
                Éléments
            </button>
            <button class="ps-tab" data-tab="layers">
                <span class="ps-tab-icon">☰</span>
                Calques
            </button>
        </nav>

        <!-- TAB CONTENT -->
        <div class="ps-tab-content">

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

                <div class="ps-inline-group">
                    <div class="ps-form-group">
                        <label class="ps-label">Taille</label>
                        <input type="range" class="ps-range" id="fontSize" min="12" max="72" value="24">
                    </div>
                    <div class="ps-form-group">
                        <label class="ps-label">Couleur</label>
                        <input type="color" class="ps-color-input" id="textColor" value="#000000">
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
