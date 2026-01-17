# PERSONNALY - Fichier de Progression

> **IMPORTANT** : Ce fichier doit être lu par Claude au début de chaque session et mis à jour après chaque tâche.

---

## Informations Projet

| Élément | Valeur |
|---------|--------|
| Nom | PERSONNALY |
| Domaine | personnaly.fr |
| Hébergement | o2switch (mutualisé) |
| Stack | PHP 7.4+ / MySQL / PDO |
| Base de données | zajr1824_persosaas |
| Design | Ultra-moderne • Girly • Rose + Vert Menthe + Noir |

---

## État Actuel

**Date dernière mise à jour** : 2026-01-17

**Phase actuelle** : P5 - PAGE D'ACCUEIL DYNAMIQUE ✅ TERMINÉ

**Statut global** : 🟢 PRÊT - Page d'accueil dynamique implémentée

---

## 🎯 VISION PERSONNALY (RAPPEL PERMANENT - MISE À JOUR 2026-01-17)

**PERSONNALY EST** :
- Un outil de personnalisation **moderne et visuel** (2026)
- Une expérience **rassurante** pour le client (zoom fidèle, photos réelles)
- Un système **administrable** sans code
- Un configurateur **INDICATIF** (position, police, couleur, technique)

**PERSONNALY N'EST PAS** :
- Un simulateur de rendu matière photoréaliste
- Un outil qui "promet" un rendu visuel trompeur
- Un formulaire figé "qui marche"

**RÈGLE CLÉ** :
> Le configurateur montre **OÙ** et **COMMENT** sera le texte.
> Le modal "Voir le rendu réel" montre **QUOI** (photos macro réelles).

---

## 📋 AUDIT COMPLET - 2026-01-16

### Conformité vs Prompt Initial

| Exigence | Statut | Détail |
|----------|--------|--------|
| **Aperçu temps réel** | ✅ OK | Drag & drop + preview texte live |
| **Polices administrables** | ✅ OK | Google + Custom, admin fonts.php |
| **Zones d'impression par produit** | ✅ OK | Admin product-zones.php |
| **Drag & drop contraint à zone** | ✅ OK | Position en %, contrainte zone |
| **Positions prédéfinies** | ✅ SUPPRIMÉ | Remplacé par drag & drop |
| **Gestion commandes admin** | ✅ OK | Liste, détail, statuts |
| **Gestion clients admin** | ✅ OK | Liste, fiche, historique |
| **Emails transactionnels** | ✅ OK | Confirmation + notification admin |
| **Panier + Checkout** | ✅ OK | Fonctionnels |
| **Images face/dos** | ✅ OK | Face + Dos + bascule client |
| **Couleurs par produit** | ✅ OK | Table product_colors + admin |
| **Couleurs texte client** | ✅ OK | 6 couleurs + preview temps réel |
| **Techniques client** | ✅ OK | Flex/Flock/Broderie + prix |
| **Styles visuels par technique** | ✅ OK | CSS distinct par technique |
| **Zoom/lightbox images** | ✅ OK | Composant réutilisable + technique |
| **Preview fidèle panier** | ✅ OK | Lightbox + texte personnalisé |
| **Packs thématiques** | ⚠️ EN COURS | P4.1-P4.4 terminés, P4.5-P4.6 en attente |

### Ce qui est FAIT ✅

1. **Infrastructure**
   - Structure MVC PHP/MySQL
   - Auth admin sessions + bcrypt
   - Design system (rose/menthe/noir)

2. **Admin**
   - Dashboard stats
   - CRUD Produits (1 image)
   - CRUD Commandes + détail
   - CRUD Clients + fiche
   - Polices (Google + Custom)
   - Zones d'impression par produit
   - Options (tailles, couleurs globales, couleurs texte, techniques)

3. **Client**
   - Page produit avec personnalisation
   - Preview drag & drop contraint
   - Polices dynamiques
   - Panier fonctionnel
   - Checkout + emails

### Ce qui est PARTIELLEMENT FAIT ⚠️

| Élément | Admin | Client |
|---------|-------|--------|
| Couleurs texte | ✅ OK | ✅ OK |
| Techniques perso | ✅ OK | ✅ OK |
| Preview panier | - | ✅ OK (Lightbox) |

### Ce qui reste à faire ❌

| # | Tâche | Priorité | Complexité |
|---|-------|----------|------------|
| ~~1~~ | ~~Images face/dos produit~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~2~~ | ~~Bascule Face/Dos côté client~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~3~~ | ~~Zones d'impression par vue~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~4~~ | ~~Couleurs par produit~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~5~~ | ~~Couleurs texte côté client~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~6~~ | ~~Techniques côté client + prix~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~7~~ | ~~Lightbox/zoom~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| ~~8~~ | ~~Preview panier fidèle~~ | ~~HAUTE~~ | ✅ TERMINÉ |
| 9 | ~~Packs thématiques (P4)~~ | ~~BASSE~~ | ✅ P4.1-P4.4 TERMINÉS |

---

## 🏗️ ARCHITECTURE VALIDÉE - 2026-01-16

### 1. Images Face/Dos

**SQL :**
```sql
ALTER TABLE products
  CHANGE image_url image_front_url VARCHAR(255) DEFAULT NULL,
  ADD COLUMN image_back_url VARCHAR(255) DEFAULT NULL AFTER image_front_url;
```

**Admin :**
- 2 zones upload distinctes (Face + Dos)
- Dos optionnel

**Client :**
- Boutons/onglets "Face / Dos"
- Preview bascule entre les deux
- Zone d'impression change selon la vue

### 2. Couleurs par produit

**Nouvelle table :**
```sql
CREATE TABLE product_colors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  color_id INT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (color_id) REFERENCES customization_options(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_product_color (product_id, color_id)
);
```

**Admin :**
- Dans product-form.php : checkboxes couleurs disponibles
- Si aucune sélectionnée → fallback toutes couleurs

### 3. Couleurs texte côté client

**Ajout dans product.php :**
- Section "Couleur du texte"
- Pastilles colorées (comme couleurs produit)
- Preview : couleur appliquée au texte
- Stockage : `{ "text_color": "#D4AF37", "text_color_name": "Doré" }`

### 4. Techniques côté client

**Ajout dans product.php :**
- Section "Technique de personnalisation"
- Cards avec : nom, description, prix (+X €)
- Prix total mis à jour en temps réel
- Stockage : `{ "technique": "broderie", "technique_price": 8.00 }`

### 5. Lightbox/Zoom

**Composant réutilisable :**
- Modal plein écran, fond sombre
- Image + texte overlay fidèle
- Fermeture : clic extérieur, ×, Escape
- Mobile : tap fullscreen, pinch-zoom

**Pages concernées :**
- product.php (preview cliquable)
- cart.php (miniatures cliquables)
- admin/order.php (détail commande)

### 6. Layout Configurateur 3 Colonnes

**Architecture :**
```
┌─────────────────────────────────────────────────────────────────────┐
│  GAUCHE (300px)    │   CENTRE (flex)      │   DROITE (320px)       │
│  Options visuelles │   Produit Hero       │   Options produit      │
│  ───────────────── │   ─────────────────  │   ──────────────────── │
│  • Texte perso     │   • Face/Dos toggle  │   • Nom + Prix         │
│  • Police          │   • Preview 450px    │   • Taille             │
│  • Couleur texte   │   • Zoom cliquable   │   • Couleur vêtement   │
│  • Technique       │   • Drag & drop      │   • Quantité           │
│                    │                      │   • Ajouter au panier  │
└─────────────────────────────────────────────────────────────────────┘
```

**Responsive :**
- 1200px+ : 3 colonnes (300px | flex | 320px)
- 1024px : Stack (produit en haut)
- 768px : Mobile accordion + sticky CTA
- 600px : Preview réduit, UI compacte

**Mobile :**
- Sections collapsibles (accordions)
- Produit hero toujours visible en premier
- CTA sticky en bas de page

---

## Historique des Tâches

### Sessions 1-5 (résumé)

| Tâche | Statut |
|-------|--------|
| Fondations PHP/MySQL | ✅ OK |
| Admin complet (produits, commandes, clients) | ✅ OK |
| Panier + Checkout | ✅ OK |
| Emails transactionnels | ✅ OK |
| P1 - Polices administrables | ✅ OK |
| P2 - Drag & drop preview | ✅ OK |
| P3 - Zones d'impression | ✅ OK |

### 2026-01-16 - Session 6 (Options v2)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Migration options (price, description) | ✅ OK | Colonnes ajoutées |
| Type text_color | ✅ OK | Admin OK |
| Type technique | ✅ OK | Admin OK avec prix |
| Refonte options.php | ✅ OK | 4 onglets |
| Fix generateCsrf() | ✅ OK | Alias ajouté |
| Fix getColors() | ✅ OK | Méthode restaurée |
| Couleurs texte client | ❌ TODO | En attente |
| Techniques client | ❌ TODO | En attente |

### 2026-01-16 - Session 7 (RECADRAGE)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Audit complet vs prompt | ✅ OK | Ce fichier |
| Architecture images face/dos | ✅ VALIDÉE | Implémentée |
| Architecture couleurs par produit | ✅ VALIDÉE | En attente |
| Architecture lightbox | ✅ VALIDÉE | En attente |

### 2026-01-16 - Session 8 (IMAGES FACE/DOS)

| Tâche | Statut | Notes |
|-------|--------|-------|
| SQL migration (image_front_url, image_back_url) | ✅ OK | migrate_images_face_back.sql |
| Product.php model | ✅ OK | Supporte 2 images |
| product-form.php (2 uploads) | ✅ OK | Zone Face + Zone Dos |
| product.php (bascule) | ✅ OK | Boutons Face/Dos, zones distinctes |
| index.php | ✅ OK | Utilise image_front_url |
| product-zones.php | ✅ OK | Utilise image_front_url |
| schema.sql | ✅ OK | Colonnes ajoutées |

### 2026-01-16 - Session 9 (RECADRAGE COMPLET)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Fix upload image dos | ✅ OK | Création dossier + vérification retour |
| Composant Lightbox | ✅ OK | lightbox.js réutilisable |
| Lightbox product.php | ✅ OK | Bouton zoom + preview |
| Lightbox cart.php | ✅ OK | Images cliquables |
| Lightbox admin/order.php | ✅ OK | Images cliquables |
| Couleurs texte client | ✅ OK | 6 couleurs avec preview |
| Techniques client | ✅ OK | Flex/Flock/Broderie |
| Table product_colors | ✅ OK | migrate_product_colors.sql |
| Modèle ProductColor | ✅ OK | CRUD complet |
| Interface admin couleurs | ✅ OK | Ajout/suppression dynamique |
| product.php couleurs produit | ✅ OK | Priorité couleurs spécifiques |

### 2026-01-16 - Session 10 (STYLES VISUELS PAR TECHNIQUE)

| Tâche | Statut | Notes |
|-------|--------|-------|
| CSS techniques.css | ✅ OK | 4 styles distincts (broderie, flex, flock, sublimation) |
| Broderie | ✅ OK | Relief + ombres multiples + effet fil cousu |
| Flex | ✅ OK | Plat, net, léger reflet brillant |
| Flock | ✅ OK | Velours mat, ombre douce diffuse |
| Sublimation | ✅ OK | Intégré au tissu, mix-blend-mode |
| product.php technique | ✅ OK | Classe CSS dynamique + indicateur |
| lightbox.js technique | ✅ OK | Support paramètre technique + styles |
| cart.php technique | ✅ OK | data-technique + affichage tag |
| admin/order.php technique | ✅ OK | data-technique + affichage tag |

### 2026-01-16 - Session 11 (RECADRAGE UX FINAL)

| Tâche | Statut | Notes |
|-------|--------|-------|
| **BUG FIX: Lightbox** | ✅ OK | lightbox.js chargé AVANT le JS inline (était chargé APRÈS) |
| **BUG FIX: Face/Dos** | ✅ OK | Condition corrigée (vérifier image, pas zone) |
| **Images par coloris** | ✅ OK | Table product_color_images + admin + client |
| Migration SQL | ✅ OK | migrate_product_color_images.sql |
| Modèle ProductColorImage | ✅ OK | CRUD + upload images |
| Admin variantes couleur | ✅ OK | Interface dans product-form.php |
| Client changement images | ✅ OK | Au clic couleur → vraies photos |
| **Sélecteur polices scalable** | ✅ OK | Dropdown avec recherche, preview, catégories |
| **Refonte layout configurateur** | ✅ OK | 3 colonnes desktop + accordion mobile + sticky CTA |

### 2026-01-16 - Session 12 (REFONTE BRODERIE RÉALISTE)

**Objectif** : Améliorer drastiquement le rendu visuel de la technique "broderie" pour un aspect premium et crédible.

**Références utilisées** : 3 images de broderie réelle macro uploadées dans `/public/assets/references/techniques/broderie/`

**Analyse des références** :
- Direction fils : Satin stitch horizontal, points parallèles serrés
- Relief : Ombre portée bas-droite (~2px), texte "monte" du tissu
- Texture fil : Grain fin visible, aspect satiné/brillant
- Brillance : Points de lumière subtils sur le haut des fils
- Contraste : Fonctionne sur fond clair ET fond foncé

| Tâche | Statut | Notes |
|-------|--------|-------|
| Dossier références | ✅ OK | `/public/assets/references/techniques/broderie/` |
| Analyse images macro | ✅ OK | Relief, direction fils, texture, brillance |
| **SVG Filters** | ✅ OK | `/public/assets/includes/svg-filters.php` |
| **Broderie Variante A** | ✅ OK | Sobre/premium - relief subtil, texture légère |
| **Broderie Variante B** | ✅ OK | Texture marquée - relief prononcé, grain visible |
| Switch A/B dev | ✅ OK | `?broderie=b` dans URL ou classe `.broderie-variant-b` |
| Intégration product.php | ✅ OK | Include SVG filters + switch URL |
| Intégration cart.php | ✅ OK | Include SVG filters |
| Intégration admin/order.php | ✅ OK | Include SVG filters |
| Intégration lightbox.js | ✅ OK | Styles mis à jour avec SVG filters |

**Fichiers modifiés** :
- `public/assets/includes/svg-filters.php` (NOUVEAU) - Filtres SVG pour toutes techniques
- `public/assets/css/techniques.css` - Refonte complète broderie + 2 variantes
- `public/product.php` - Include SVG + switch A/B
- `public/cart.php` - Include SVG
- `admin/order.php` - Include SVG
- `public/assets/js/lightbox.js` - Styles techniques mis à jour

**Détail technique SVG Filters** :
- `#broderie-a` : feTurbulence (grain léger) + feDisplacementMap + feDropShadow + feSpecularLighting
- `#broderie-b` : feTurbulence (grain marqué) + feDisplacementMap + double ombre + feConvolveMatrix + feSpecularLighting
- `#flex-shine` : feSpecularLighting (brillance vinyle)
- `#flock-velvet` : feTurbulence + feGaussianBlur (velours mat)

**Pourquoi c'est plus réaliste** :
1. Relief crédible avec ombre directionnelle (lumière haut-gauche)
2. Grain du fil simulé via feTurbulence + displacement
3. Brillance naturelle via feSpecularLighting
4. Contour légèrement irrégulier (pas plastique)
5. Fonctionne sur fonds clairs ET sombres

> ⚠️ **SESSION 12 ABANDONNÉE** : Approche CSS "fake" abandonnée au profit du paradigme "configurateur indicatif + photos réelles" (voir Session 13)

### 2026-01-17 - Session 13 (PIVOT UX - PARADIGME INDICATIF)

**Changement de direction validé** : Le configurateur ne simule plus les techniques visuellement.

**Nouveau paradigme** :
1. **Configurateur = INDICATIF** : Positionner, choisir police/couleur/technique. Rendu propre et lisible.
2. **Rendu matière = MODAL SÉPARÉ** : Photos macro réelles par technique (pas de promesse CSS trompeuse)

| Tâche | Statut | Notes |
|-------|--------|-------|
| **Fix Lightbox v2** | ✅ OK | Position conservée + drag actif + zone d'impression + callback sync |
| **Modal "Voir le rendu réel"** | ✅ OK | Photos macro par technique + disclaimer client |
| **Nettoyage CSS techniques** | ✅ OK | Suppression effets fake, style propre indicatif |
| **Retrait SVG Filters** | ✅ OK | Include retiré de toutes les pages |

**Fichiers modifiés** :
- `public/assets/js/lightbox.js` - Refonte complète v2 (zone impression, drag, sync)
- `public/assets/js/real-render-modal.js` (NOUVEAU) - Modal photos macro réelles
- `public/assets/css/techniques.css` - Nettoyé, style indicatif uniquement
- `public/product.php` - Bouton "Voir le rendu réel" + intégration modal
- `public/cart.php` - Retrait SVG filters
- `admin/order.php` - Retrait SVG filters

**Nouveau composant - Modal Rendu Réel** :
- Bouton "Voir le rendu réel" dans section technique
- Galerie photos macro (jusqu'à 3 images par technique)
- Zoom plein écran au clic
- Disclaimer rassurant : "Le rendu final peut varier..."
- Images stockées dans `/public/assets/references/techniques/{technique}/`

**Lightbox v2 - Améliorations** :
- Zone d'impression visible (bordure dashed au drag)
- Drag & drop actif dans la lightbox
- Position synchronisée avec le configurateur (callback)
- Hint visuel "Glissez le texte pour ajuster"

### 2026-01-17 - Session 14 (P4 - PACKS / IDÉES)

**Objectif** : Système de packs / suggestions de personnalisation.

**Vision** : Un pack = une suggestion inspirante, jamais une prison. Le client peut tout modifier après pré-remplissage.

| Tâche | Statut | Notes |
|-------|--------|-------|
| **P4.1 - Architecture DB** | ✅ OK | Tables `packs` + `pack_products` |
| **P4.1 - Modèle Pack.php** | ✅ OK | CRUD + gestion produits liés |
| **P4.1 - Admin packs.php** | ✅ OK | Liste + toggle status + delete |
| **P4.1 - Admin pack-form.php** | ✅ OK | Création/édition + preset JSON |
| **P4.2 - Multi-produits** | ✅ OK | Checkboxes grille + compteur |
| **P4.3 - Preset JSON** | ✅ OK | Formulaire visuel (texte, police, couleur, technique, position, vue) |
| **P4.4 - Injection Preset** | ✅ OK | Route `/product.php?id=X&pack_id=Y` |

**P4.4 - Injection Preset → Configurateur** :

Route : `/product.php?id=PRODUCT_ID&pack_id=PACK_ID`

Vérifications silencieuses :
1. Pack existe et est actif
2. Produit courant ∈ pack_products (ou pack universel si vide)
3. Si invalide → ignoré silencieusement

Champs injectés (ONE-SHOT) :
- `text` → champ texte personnalisé
- `font` → sélecteur police
- `text_color` → pastille couleur texte
- `technique` → dropdown technique
- `position.x`, `position.y` → coordonnées drag & drop
- `view` → face/dos

Flag JS : `window.__PACK_PRESET_APPLIED = true` (évite ré-application)

Ce qui reste libre :
- Taille
- Couleur produit
- Quantité
- Toutes les options restent modifiables après injection

**Fichiers créés/modifiés** :
- `sql/migrate_packs.sql` - Tables packs + pack_products
- `app/models/Pack.php` - Modèle CRUD + produits + getFirstProduct()
- `admin/packs.php` - Liste admin
- `admin/pack-form.php` - Formulaire création/édition
- `admin/includes/sidebar.php` - Lien "Packs / Idées"
- `public/product.php` - Injection preset configurateur
- `public/index.php` - Section "Nos idées tendance"

**P4.5 - Section Inspirations** :
- Section "Nos idées tendance" après les produits sur index.php
- Design sombre moderne avec cartes glassmorphism
- Badges colorés par type (technique, contextuel, thématique, inspiration)
- CTA "Essayer cette idée" → `/product.php?id=X&pack_id=Y`
- Liens navbar + footer (conditionnels si packs existent)
- Skip automatique si pack sans produit actif

**P4.6 - Tests & Validation** :

| Test | Résultat | Notes |
|------|----------|-------|
| Parcours inspirations → panier | ✅ OK | Preset injecté, modifiable, panier OK |
| Pack inactif | ✅ OK | Ignoré silencieusement |
| Pack sans produit actif | ✅ OK | Carte non affichée |
| Produit non lié au pack | ✅ OK | Preset ignoré |
| Configurateur sans pack_id | ✅ OK | Comportement normal |
| Lightbox + drag & drop | ✅ OK | Aucune régression |
| Console JS | ✅ OK | Propre (debug log retiré) |
| SEO basique | ✅ OK | H1/H2 + alt images |

**Bug corrigé** :
- Retrait du `console.log('[PACK] Preset appliqué:', preset)` pour production

---

## 🟢 GEL FONCTIONNEL P4 — PACKS / IDÉES TERMINÉ

---

### 2026-01-17 - Session 15 (P5 - PAGE D'ACCUEIL DYNAMIQUE)

**Objectif** : Refondre complètement la page d'accueil pour la rendre ultra moderne, 100% administrable, scalable multi-marques.

**Principes** :
- ❌ Pas de page builder type WordPress
- ❌ Pas de drag & drop complexe
- ❌ Pas de HTML libre non contrôlé
- ✅ Sections prédéfinies, simples, efficaces
- ✅ Admin choisit type → configure contenu → front rend automatiquement

**Types de sections autorisés** :

| Type | Description | Contenu |
|------|-------------|---------|
| `hero` | Section héro plein écran | Titre, sous-titre, CTA, image/vidéo |
| `featured_products` | Grille produits | Titre + sélection produits |
| `featured_packs` | Grille packs/idées | Titre + sélection packs |
| `content_block` | Bloc texte + média | Titre, texte, image/vidéo |
| `blog_slider` | Slider articles | Titre + articles auto |

**Architecture technique** :

```
Tables:
├── homepage_sections (type, title, subtitle, cta, media, config, sort_order, status)
├── homepage_section_items (section_id, item_type, item_id, sort_order)
└── blog_posts (title, slug, excerpt, content, cover_image, status)

Admin:
├── /admin/homepage.php (liste sections + ordre)
├── /admin/homepage-section.php (création/édition)
├── /admin/blog.php (liste articles)
└── /admin/blog-form.php (création/édition)

Front:
└── /public/index.php (lecture sections DB → rendu automatique)
```

| Tâche | Statut | Notes |
|-------|--------|-------|
| **P5.1 - Migration SQL** | ✅ OK | Tables sections + items + blog |
| **P5.2 - Modèles PHP** | ✅ OK | HomepageSection + BlogPost |
| **P5.3 - Admin Sections** | ✅ OK | Liste + formulaire + drag&drop réordonnancement |
| **P5.4 - Admin Blog** | ✅ OK | CRUD articles + sidebar links |
| **P5.5 - Front dynamique** | ✅ OK | Refonte complète index.php |
| **P5.6 - Tests** | 🟡 À TESTER | En production

### 2026-01-17 - Session 16 (P5 - IMPLÉMENTATION COMPLÈTE)

**Fichiers créés** :
- `sql/migrate_homepage.sql` - Tables homepage_sections, homepage_section_items, blog_posts
- `app/models/HomepageSection.php` - CRUD sections + gestion items (produits/packs)
- `app/models/BlogPost.php` - CRUD articles + génération slug + toggle status
- `admin/homepage.php` - Liste sections avec drag&drop AJAX
- `admin/homepage-section.php` - Formulaire création/édition type-spécifique
- `admin/blog.php` - Liste articles avec actions toggle/delete
- `admin/blog-form.php` - Formulaire création/édition articles

**Fichiers modifiés** :
- `admin/includes/sidebar.php` - Ajout liens "Page d'accueil" et "Blog"
- `public/index.php` - Refonte complète, rendu dynamique depuis DB

**Fonctionnalités implémentées** :

| Section Type | Admin | Front |
|--------------|-------|-------|
| `hero` | ✅ Titre, sous-titre, CTA, highlight | ✅ Rendu plein écran avec animations |
| `featured_products` | ✅ Sélection produits via checkboxes | ✅ Grille responsive |
| `featured_packs` | ✅ Sélection packs via checkboxes | ✅ Cards glassmorphism |
| `content_block` | ✅ Texte + média (image/vidéo) | ✅ Layout 2 colonnes alternées |
| `blog_slider` | ✅ Auto (articles publiés) | ✅ Slider horizontal scrollable |

**Architecture front dynamique** :
```php
$sections = $sectionModel->findActive();
foreach ($sections as $section) {
    switch ($section['type']) {
        case 'hero': // Render hero
        case 'featured_products': // Render products grid
        case 'featured_packs': // Render packs grid
        case 'content_block': // Render text + media
        case 'blog_slider': // Render blog carousel
    }
}
```

**Points clés** :
- Réordonnancement drag&drop des sections (AJAX)
- Formulaire admin dynamique selon type de section
- Chargement conditionnel des données (produits/packs/articles)
- Navbar/footer conservés statiques
- Fallback si aucune section configurée

---

## 🔴 AUDIT RECADRAGE - 2026-01-16

### A. État d'avancement vs prompt initial

| Fonctionnalité | Statut | Détail |
|----------------|--------|--------|
| Aperçu temps réel | ✅ OK | Drag & drop + preview texte live |
| Polices administrables | ✅ OK | Google + Custom, admin fonts.php |
| Zones d'impression | ✅ OK | Admin product-zones.php, front/back |
| Drag & drop contraint | ✅ OK | Position en %, contrainte zone |
| Gestion commandes | ✅ OK | Liste, détail, statuts |
| Gestion clients | ✅ OK | Liste, fiche, historique |
| Images face/dos | ✅ OK | Upload admin + bascule client |
| Couleurs par produit | ✅ OK | Table product_colors + admin |
| Couleurs texte | ✅ OK | 6 couleurs + preview temps réel |
| Techniques client | ✅ OK | Flex/Flock/Broderie/Sublimation + prix |
| Styles visuels techniques | ✅ OK | CSS distinct par technique |
| **Zoom/lightbox** | ✅ OK | **BUG CORRIGÉ cette session** |
| **Bascule face/dos client** | ✅ OK | **BUG CORRIGÉ cette session** |
| **Images par coloris** | ✅ OK | **IMPLÉMENTÉ cette session** |
| **Sélecteur polices scalable** | ✅ OK | **IMPLÉMENTÉ cette session** |
| Layout configurateur moderne | ❌ NON | Layout vertical formulaire classique |

### B. Ce qu'il reste à faire (priorisé)

| # | Tâche | Priorité | Complexité | Notes |
|---|-------|----------|------------|-------|
| ~~1~~ | ~~Images par coloris~~ | ~~🔴 HAUTE~~ | ~~Moyenne~~ | ✅ TERMINÉ |
| ~~2~~ | ~~Sélecteur polices scalable~~ | ~~🟡 MOYENNE~~ | ~~Faible~~ | ✅ TERMINÉ |
| 1 | Refonte layout configurateur | 🟡 MOYENNE | Haute | Produit au centre, options sur côtés |
| 2 | Pinch-zoom mobile | 🟢 BASSE | Faible | Amélioration lightbox |
| 3 | Packs thématiques (P4) | 🟢 BASSE | Haute | Non prioritaire |

---

## Prochaines Étapes (ordre validé)

### Phase immédiate (HAUTE priorité) ✅ TERMINÉ

1. **Images face/dos** ✅ TERMINÉ
   - [x] ALTER TABLE products
   - [x] Modifier product-form.php (2 uploads)
   - [x] Modifier product.php (bascule face/dos)
   - [x] Adapter zones d'impression

2. **Lightbox / Zoom** ✅ TERMINÉ
   - [x] Composant modal réutilisable (lightbox.js)
   - [x] Intégration product.php (bouton zoom)
   - [x] Intégration cart.php (images cliquables)
   - [x] Intégration admin/order.php (images cliquables)

3. **Couleurs texte + Techniques** ✅ TERMINÉ
   - [x] Couleurs texte dans product.php (6 options)
   - [x] Techniques dans product.php (Flex, Flock, Broderie)
   - [x] Preview temps réel de la couleur du texte
   - [x] Ombres adaptatives pour lisibilité

4. **Couleurs par produit** ✅ TERMINÉ
   - [x] Table product_colors (migration SQL)
   - [x] Modèle ProductColor.php
   - [x] Interface admin dans product-form.php
   - [x] product.php utilise couleurs spécifiques si définies

### Phase suivante (MOYENNE priorité)

4. **Améliorations preview**
   - [ ] Preview panier fidèle avec position
   - [ ] Export image pour email/PDF

### Phase future (BASSE priorité)

5. **P4 - Packs thématiques**
6. **Connexion clients**
7. **Paiement en ligne**

---

## Design System

### Couleurs
- **Rose** : #FF69B4 (principal), #FF1493 (foncé), #FFB6C1 (clair)
- **Vert Menthe** : #3DFFC0 (principal), #00D9A0 (foncé), #98FFD6 (clair)
- **Noir** : #0D0D0D, #1A1A2E, #16213E

### Polices
- **UI** : Inter
- **Personnalisation** : Dynamiques depuis table `fonts`

---

## Structure Projet

```
/personnaly.fr/
├── PROGRESSION.md          ← CE FICHIER
├── public/
│   ├── index.php           ← Accueil + catalogue
│   ├── product.php         ← Personnalisation (à enrichir)
│   ├── cart.php            ← Panier (à enrichir lightbox)
│   └── checkout.php
├── app/
│   ├── models/
│   │   ├── Product.php
│   │   ├── Font.php
│   │   ├── ProductPrintZone.php
│   │   └── CustomizationOption.php
│   └── helpers/
│       ├── FontLoader.php
│       └── Cart.php
├── admin/
│   ├── products.php
│   ├── product-form.php    ← À modifier (2 images)
│   ├── fonts.php
│   ├── product-zones.php
│   ├── options.php
│   ├── orders.php
│   └── order.php           ← À enrichir (lightbox)
└── sql/
    ├── schema.sql
    ├── fonts.sql
    └── options.sql
```

---

## Credentials

- **Admin** : admin@personnaly.fr / password
- **DB** : zajr1824_persosaas

---

## Règles de Développement

1. **Pas de code sans validation** de l'architecture
2. **Lire PROGRESSION.md** au début de chaque session
3. **Mettre à jour PROGRESSION.md** après chaque tâche
4. **UX 2026** : moderne, visuel, rassurant
5. **Pas de solution "année 2000"** même si elle fonctionne

---

**FIN DU FICHIER DE PROGRESSION**
