# TASKS — PERSONNALY

> **Fichier de suivi des tâches structuré**
> - `[ ]` = TODO
> - `[~]` = EN COURS
> - `[x]` = TERMINÉ
> - 1 tâche = 1 implémentation
> - Validation humaine AVANT chaque implémentation
> - Mise à jour TASKS.md + PROGRESSION.md après chaque tâche

---

## 📊 ÉTAT GLOBAL — 2026-01-19

| Phase | Statut | Détail |
|-------|--------|--------|
| P1-P3 | ✅ TERMINÉ | Polices, Drag&Drop, Zones |
| P4 | ✅ TERMINÉ | Packs / Idées |
| P5 | ✅ TERMINÉ | Page d'accueil dynamique |
| P6 STEP 1-5 | 🟡 EN COURS | Correctifs, WebP, Catégories, Techniques, Upsells+Codes Promo+Tailles/Designs/Éléments |
| P6 STEP 6-10 | 🔴 À FAIRE | Stats, Archives, Organisation, Marketing, Paiement |

---

# 🔴 PHASE P6 — FONCTIONNALITÉS RESTANTES

---

## STEP 1 — CORRECTIFS & FONDATIONS (PRIORITÉ HAUTE)

> Corriger les bugs existants avant d'ajouter des features.

### 1.1 — Debug chemins images uploads

- [x] **1.1.1** — Investiguer pourquoi les images uploadées (hero, content_block) ne s'affichent pas côté site ✅
- [x] **1.1.2** — Ajouter feedback erreurs PHP détaillé (UPLOAD_ERR_*) ✅
- [x] **1.1.3** — Corriger le rendu content_block pour médias additionnels ✅
- [x] **1.1.4** — Documenter la solution ✅

**Solution** : Le bug était dans `public/index.php` ligne 1029. Le bloc média ne s'affichait que si une image principale existait (`$section['media_url']`), empêchant l'affichage des médias additionnels seuls.
**Fix** : Condition modifiée pour afficher le bloc si média principal OU médias additionnels existent.

### 1.2 — UI Upload médias améliorée

> Interface modernisée pour les médias additionnels (content_block).

- [x] **1.2.1** — Remplacer input `multiple` par input simple + bouton "+ Ajouter une image" ✅
- [x] **1.2.2** — Afficher les images uploadées avec bouton ❌ supprimer ✅
- [x] **1.2.3** — Permettre réordonnancement drag&drop des images ✅
- [x] **1.2.4** — Appliquer à content_block (fait) ✅

**Implémentation** :
- Galerie visuelle avec vignettes 100x100px
- Bouton "+" pour ajouter une image à la fois
- Bouton ❌ au hover pour supprimer
- Handle drag pour réordonner (6 points)
- Preview des nouvelles images "En attente"
- Backend PHP mis à jour pour `existing_media_urls[]` + `additional_media[]`
- Maximum 10 images

---

## STEP 2 — MÉDIAS & PERFORMANCE

> Optimisation des images pour performance web.

### 2.1 — Conversion WebP automatique

- [x] **2.1.1** — Créer classe `ImageHelper` avec `convertToWebP()` ✅
- [x] **2.1.2** — Appliquer à l'upload (products, homepage, blog, packs) ✅
- [x] **2.1.3** — Conserver original + générer version WebP automatiquement ✅
- [x] **2.1.4** — Logique dynamique : WebP généré à l'upload, chemins DB inchangés ✅

**Fichiers créés** :
- `app/helpers/ImageHelper.php` - Classe avec convertToWebP(), pictureTag(), getImageUrls()

**Fichiers modifiés** :
- `admin/homepage-section.php` - WebP pour images sections
- `admin/product-form.php` - WebP pour images produits
- `admin/blog-form.php` - WebP pour images blog
- `admin/pack-form.php` - WebP pour images packs

### 2.2 — Fallback navigateur

- [x] **2.2.1** — Créer fonction `picture()` helper avec `<picture>` + fallback ✅
- [x] **2.2.2** — Appliquer sur index.php (produits, packs, galerie, blog) ✅

**Note** : La fonction `picture()` vérifie l'existence du fichier WebP et génère automatiquement :
- `<picture><source type="image/webp" srcset="..."><img src="..." alt="..."></picture>` si WebP existe
- `<img src="..." alt="...">` sinon

---

## ✅ STEP 2 TERMINÉ — Médias & Performance

---

## STEP 3 — CATÉGORIES PRODUITS (IMPORTANT)

> Organiser les produits en catégories pour filtrage et sections homepage.

### 3.1 — Architecture DB

- [x] **3.1.1** — Créer table `categories` (id, name, slug, description, image_url, sort_order, status) ✅
- [x] **3.1.2** — Créer table `product_categories` (product_id, category_id) — relation N:N ✅
- [x] **3.1.3** — Migration SQL ✅

### 3.2 — Admin CRUD Catégories

- [x] **3.2.1** — admin/categories.php (liste + réordonnancement) ✅
- [x] **3.2.2** — admin/category-form.php (création/édition) ✅
- [x] **3.2.3** — Lien sidebar admin ✅
- [x] **3.2.4** — Dans product-form.php : checkboxes catégories ✅

### 3.3 — Sections homepage avec catégories

- [x] **3.3.1** — Type section `featured_category` : affiche produits d'une catégorie ✅

## ✅ STEP 3 TERMINÉ — Catégories Produits

---

## STEP 4 — TECHNIQUES : RENDU RÉEL ADMIN ✅ TERMINÉ

> Permettre à l'admin d'uploader les photos macro de rendu réel par technique.

### 4.1 — Architecture

- [x] **4.1.1** — Colonne `images_json` dans customization_options ✅
- [x] **4.1.2** — Méthodes addImage/removeImage/getImages dans le modèle ✅

### 4.2 — Admin upload images techniques

- [x] **4.2.1** — Dans admin/options.php (onglet Techniques) : section upload images ✅
- [x] **4.2.2** — UI : 1 image à la fois, max 3 par technique ✅
- [x] **4.2.3** — Affichage miniatures avec suppression ✅
- [x] **4.2.4** — Stockage dans `/public/uploads/techniques/` ✅

### 4.3 — Intégration modal rendu réel

- [x] **4.3.1** — API /api/technique-images.php pour charger images depuis DB ✅
- [x] **4.3.2** — real-render-modal.js : fetch API avec fallback ✅

---

## ✅ STEP 5 — UPSELLS & CODES PROMO TERMINÉ (REFACTORÉ v2)

> STEP 5 a été refactoré pour séparer clairement :
> - **Upsells** : Suggestions de produits complémentaires ("Vous aimerez aussi")
> - **Codes Promo** : Système classique avec saisie code par le client

### 5.A — VRAIS Upsells (Suggestions de produits)

- [x] **5.A.1** — Table `product_upsells` + `upsell_settings` ✅
- [x] **5.A.2** — admin/upsells.php (liste + paramètres) ✅
- [x] **5.A.3** — admin/upsell-form.php (création/édition) ✅
- [x] **5.A.4** — cart.php : section suggestions produits ✅
- [~] **5.A.5** — 🎨 **CSS admin/upsells.php** : refaire design ultra-moderne
  - [x] **5.A.5.1** — Layout structurel `.upsells-layout` défini dans admin.css (grid 320px | 1fr)
  - [ ] **5.A.5.2** — Styles visuels (panels, cards, forms, etc.) — À FAIRE

### 5.B — Codes Promo (Saisie Client)

- [x] **5.B.1** — Table `promo_codes` + `order_promo_codes` ✅
- [x] **5.B.2** — app/models/PromoCode.php avec validateCode() ✅
- [x] **5.B.3** — admin/promo-codes.php (liste ultra-moderne) ✅
- [x] **5.B.4** — admin/promo-code-form.php (création/édition) ✅
- [x] **5.B.5** — cart.php : champ saisie code promo + validation AJAX ✅

**Fichiers créés** :
- `sql/migrate_real_upsells.sql`
- `sql/migrate_promo_codes.sql`
- `app/models/ProductUpsell.php`
- `app/models/PromoCode.php`
- `admin/promo-codes.php`
- `admin/promo-code-form.php`

**⚠️ EN COURS** :
- SOUS-STEP 0 terminé : nettoyage CSS inline (commit bae8245)
- STEP 1 terminé : layout `.upsells-layout` défini dans admin.css (grid 320px | 1fr)
- STEP 2 à venir : styles visuels (panels, cards, forms, etc.)

### ✅ 5.C — Groupes de Tailles, Designs & Éléments (TERMINÉ 2026-01-19)

> Refonte complète de la gestion des tailles avec groupes dynamiques, ajout des Designs (Idées cadeaux) et Éléments (Cliparts/Formes).

#### 5.C.1 — Groupes de Tailles

- [x] **5.C.1.1** — Table `size_groups` (id, name, sort_order, active) ✅
- [x] **5.C.1.2** — Table `sizes` (id, label, size_group_id, sort_order, active) ✅
- [x] **5.C.1.3** — Modèle `SizeGroup.php` avec `findOrCreate()` ✅
- [x] **5.C.1.4** — Modèle `Size.php` avec `findAllGrouped()` ✅
- [x] **5.C.1.5** — Formulaire taille : sélecteur groupe OU création nouveau groupe ✅

#### 5.C.2 — Designs (Idées cadeaux)

- [x] **5.C.2.1** — Table `design_categories` (id, name, sort_order, active) ✅
- [x] **5.C.2.2** — Table `designs` (id, name, image_path, category_id, active) ✅
- [x] **5.C.2.3** — Modèle `DesignCategory.php` avec `findOrCreate()` ✅
- [x] **5.C.2.4** — Modèle `Design.php` avec `findAllGrouped()` ✅
- [x] **5.C.2.5** — Onglet Designs dans admin/options.php ✅
- [x] **5.C.2.6** — Upload image + conversion WebP ✅

#### 5.C.3 — Éléments (Cliparts/Formes)

- [x] **5.C.3.1** — Table `element_categories` (id, name, sort_order, active) ✅
- [x] **5.C.3.2** — Table `elements` (id, name, image_path, category_id, is_premium, price, active) ✅
- [x] **5.C.3.3** — Modèle `ElementCategory.php` avec `findOrCreate()` ✅
- [x] **5.C.3.4** — Modèle `Element.php` avec support premium/prix ✅
- [x] **5.C.3.5** — Onglet Éléments dans admin/options.php ✅
- [x] **5.C.3.6** — Toggle Premium avec champ prix conditionnel ✅

**Fichiers créés** :
- `sql/migrate_sizes_designs_elements.sql` — Migration complète 6 tables
- `app/models/SizeGroup.php` — CRUD + findOrCreate()
- `app/models/Size.php` — CRUD + findAllGrouped()
- `app/models/DesignCategory.php` — CRUD + findOrCreate()
- `app/models/Design.php` — CRUD + findAllGrouped() + upload image
- `app/models/ElementCategory.php` — CRUD + findOrCreate()
- `app/models/Element.php` — CRUD + is_premium + price

**Fichiers modifiés** :
- `admin/options.php` — Refonte complète avec 4 onglets (Techniques, Tailles, Designs, Éléments)

**Structure admin/options.php** :
- Onglet Techniques : inchangé (customization_options type=technique)
- Onglet Tailles : formulaire avec sélecteur groupe OU nouveau groupe
- Onglet Designs : upload image + nom + catégorie
- Onglet Éléments : upload image + nom + catégorie + toggle premium + prix

**⚠️ À FAIRE** :
- Exécuter `sql/migrate_sizes_designs_elements.sql` sur la BDD production

---

## STEP 6 — STATS ADMIN (RESTREINT)

> Dashboard analytics pour Owner/Manager uniquement.

### 6.1 — Architecture

- [ ] **6.1.1** — Ajouter colonne `role` dans table users (admin, owner, manager, staff)
- [ ] **6.1.2** — Ou créer table `user_roles`

### 6.2 — Onglet Stats

- [ ] **6.2.1** — admin/stats.php (accès restreint Owner/Manager)
- [ ] **6.2.2** — Middleware vérification rôle
- [ ] **6.2.3** — Lien sidebar conditionnel

### 6.3 — Métriques

- [ ] **6.3.1** — CA (jour, semaine, mois, année)
- [ ] **6.3.2** — Nombre commandes + panier moyen
- [ ] **6.3.3** — Nouveaux clients
- [ ] **6.3.4** — Top produits
- [ ] **6.3.5** — Stats personnalisations (techniques populaires, polices)

### 6.4 — Messages business

- [ ] **6.4.1** — Règles simples : "CA en hausse de X%" / "Technique broderie très demandée"
- [ ] **6.4.2** — Alertes : stock bas, commande en retard

---

## STEP 7 — ARCHIVE COMMANDES

> Gérer les commandes terminées sans encombrer la liste principale.

### 7.1 — Architecture

- [ ] **7.1.1** — Ajouter colonne `archived_at` dans orders
- [ ] **7.1.2** — Ou créer table `orders_archive`

### 7.2 — Fonctionnalités

- [ ] **7.2.1** — Bouton "Archiver" sur commandes livrées/annulées
- [ ] **7.2.2** — admin/orders-archive.php (liste archives)
- [ ] **7.2.3** — Export CSV (filtré par date, statut)
- [ ] **7.2.4** — Restauration (désarchiver)
- [ ] **7.2.5** — Suppression définitive (confirmation double)

---

## STEP 8 — ORGANISATION INTERNE

> Outils pour gérer l'équipe et le workflow.

### 8.1 — Notes internes commandes

- [ ] **8.1.1** — Table `order_notes` (order_id, user_id, note, created_at)
- [ ] **8.1.2** — Interface dans admin/order.php (timeline notes)
- [ ] **8.1.3** — Mention @user (notification)

### 8.2 — Tags commandes

- [ ] **8.2.1** — Table `order_tags` (id, name, color)
- [ ] **8.2.2** — Table `order_tag_assignments` (order_id, tag_id)
- [ ] **8.2.3** — Admin : gestion tags + assignation
- [ ] **8.2.4** — Filtrage par tag dans liste commandes

### 8.3 — Assignation staff

- [ ] **8.3.1** — Colonne `assigned_to` dans orders
- [ ] **8.3.2** — Dropdown assignation dans order.php
- [ ] **8.3.3** — Filtrage "Mes commandes" dans liste

### 8.4 — Alertes retard

- [ ] **8.4.1** — Définir délais par statut (ex: pending > 24h = alerte)
- [ ] **8.4.2** — Badge visuel dans liste commandes
- [ ] **8.4.3** — Notification email (optionnel)

---

## STEP 9 — MARKETING & FIDÉLITÉ

> Outils pour fidéliser et relancer les clients.

### 9.1 — Points fidélité

- [ ] **9.1.1** — Table `loyalty_points` (customer_id, points, source, order_id, created_at)
- [ ] **9.1.2** — Règles : 1€ = X points
- [ ] **9.1.3** — Affichage points dans compte client
- [ ] **9.1.4** — Utilisation points = réduction

### 9.2 — Relances automatiques

- [ ] **9.2.1** — Table `email_automations` (type, delay, template, status)
- [ ] **9.2.2** — Panier abandonné (24h, 48h, 72h)
- [ ] **9.2.3** — Client inactif (30j, 60j, 90j)
- [ ] **9.2.4** — Cron job pour envoi

### 9.3 — Cross-sell post-commande

- [ ] **9.3.1** — Email "Complétez votre look" après commande
- [ ] **9.3.2** — Suggestions basées sur achat

---

## STEP 10 — PAIEMENT & LIVRAISON (DERNIER)

> Intégration paiement en ligne et transporteurs.

### 10.1 — Stripe

- [ ] **10.1.1** — Créer compte Stripe + API keys
- [ ] **10.1.2** — Intégration Stripe Checkout ou Elements
- [ ] **10.1.3** — Webhook pour confirmation paiement
- [ ] **10.1.4** — Mise à jour statut commande automatique
- [ ] **10.1.5** — Gestion remboursements admin

### 10.2 — Livraison intelligente

- [ ] **10.2.1** — Intégration API Mondial Relay (points relais)
- [ ] **10.2.2** — Intégration API Colissimo (domicile)
- [ ] **10.2.3** — Calcul frais selon poids/destination
- [ ] **10.2.4** — Choix transporteur checkout
- [ ] **10.2.5** — Tracking commande

---

# ✅ PHASES TERMINÉES

## ✅ P5 — PAGE D'ACCUEIL DYNAMIQUE

| Tâche | Statut |
|-------|--------|
| P5.1 — Architecture DB | ✅ OK |
| P5.2 — Modèles PHP | ✅ OK |
| P5.3 — Admin Sections | ✅ OK |
| P5.4 — Admin Blog | ✅ OK |
| P5.5 — Front Dynamique | ✅ OK |
| P5.6 — Newsletter | ✅ OK |
| P5.7 — Éditeur Quill | ✅ OK |
| P5.8 — Sélection articles blog | ✅ OK |
| P5.9 — Multi-médias content_block | ✅ OK |
| P5.10 — Fix getItems() blog | ✅ OK |

## ✅ P4 — PACKS / IDÉES

| Tâche | Statut |
|-------|--------|
| P4.1 — Architecture DB | ✅ OK |
| P4.2 — Admin CRUD | ✅ OK |
| P4.3 — Preset JSON | ✅ OK |
| P4.4 — Injection configurateur | ✅ OK |
| P4.5 — Section inspirations | ✅ OK |
| P4.6 — Tests validation | ✅ OK |

## ✅ P1-P3 — FONDATIONS

| Phase | Contenu | Statut |
|-------|---------|--------|
| P1 | Polices administrables | ✅ OK |
| P2 | Drag & drop preview | ✅ OK |
| P3 | Zones d'impression | ✅ OK |

---

# 📋 ORDRE D'IMPLÉMENTATION RECOMMANDÉ

```
STEP 1 → STEP 2 → STEP 3 → STEP 4 → STEP 5 → STEP 6 → STEP 7 → STEP 8 → STEP 9 → STEP 10
   │         │         │         │         │
   │         │         │         │         └── Upsells (boost CA)
   │         │         │         └── Rendu réel admin (UX)
   │         │         └── Catégories (organisation)
   │         └── WebP (performance)
   └── Correctifs (stabilité)
```

**Justification** :
1. **Step 1** : Corriger bugs existants = stabilité
2. **Step 2** : Performance = meilleure UX
3. **Step 3** : Catégories = organisation nécessaire avant upsells
4. **Step 4** : Rendu réel = finalise l'UX configurateur
5. **Step 5** : Upsells = augmente CA avant lancement
6. **Step 6-9** : Outils internes = scalabilité équipe
7. **Step 10** : Paiement = dernier car nécessite tout le reste stable

---

**Dernière mise à jour** : 2026-01-19 — Groupes de Tailles + Designs (Idées cadeaux) + Éléments (Cliparts/Formes)

---

# 🟠 CHANTIERS IDENTIFIÉS — À PLANIFIER

> Ces tâches ont été identifiées lors de la session du 2026-01-18.
> Aucune n'est encore validée pour implémentation.
> Chaque STEP nécessitera validation humaine avant exécution.

---

## ADMIN — Stabilisation & UX

### ✅ A.1 — BUG : Catégories produits non affichées (TERMINÉ 2026-01-18)
> Les catégories étaient bien sauvegardées mais pas affichées (ancien champ utilisé).

**Cause racine** : L'affichage utilisait `product.category` (ancien champ texte) au lieu de la relation `product_categories`.

- [x] **A.1.1** — Investiguer le bug → Affichage utilisait mauvais champ ✅
- [x] **A.1.2** — Ajouter `getCategoryNamesByProduct()` au modèle Category ✅
- [x] **A.1.3** — Corriger admin/products.php (badges mint, multi-catégories) ✅
- [x] **A.1.4** — Corriger public/index.php (featured_products) ✅

**Commit** : `387a43e`

**Priorité** : ✅ TERMINÉ

### ✅ A.2 — Sidebar admin : problème de hauteur (TERMINÉ 2026-01-18)
> La sidebar ne descend pas assez, impossible d'accéder aux items du bas.

- [x] **A.2.1** — Diagnostiquer le CSS de la sidebar (height, overflow, position) ✅
- [x] **A.2.2** — Corriger la hauteur pour scroll complet ✅
- [x] **A.2.3** — Scrollbar custom stylée (semi-transparente, rose au hover) ✅

**Fix** : Ajout `overflow-y: auto` sur `.sidebar-nav` + scrollbar webkit custom

**Priorité** : ✅ TERMINÉ

---

## PRODUIT — Structure & Variantes

### ✅ B.1 — Refonte gestion couleurs/variantes admin (TERMINÉ 2026-01-18)
> Simplifier l'interface de création produit pour éviter confusion et duplication.

- [x] **B.1.1** — Retirer le bouton "Ajouter une couleur" (garder uniquement "Ajouter une variante") ✅
- [x] **B.1.2** — Retirer tailles et couleurs de l'onglet Options global (garder uniquement Techniques) ✅
- [x] **B.1.3** — Ajouter la taille dans les variantes produit (couleur + taille + images) ✅
- [x] **B.1.4** — Migration SQL pour colonne `size` dans `product_color_images` ✅

**Fichiers modifiés** :
- `admin/options.php` — Simplifié pour n'afficher que Techniques
- `admin/product-form.php` — Section couleurs supprimée, taille ajoutée aux variantes
- `app/models/ProductColorImage.php` — Support du champ `size`
- `sql/migrate_variant_size.sql` — Migration ajout colonne size

**Note** : Exécuter `sql/migrate_variant_size.sql` pour ajouter la colonne size à la base de données.

**Priorité** : ✅ TERMINÉ

### ✅ B.2 — Option B+ : Multi-tailles par variante couleur (TERMINÉ 2026-01-18)
> Permettre à chaque variante couleur d'avoir ses propres tailles disponibles (inspiré WooCommerce/Shopify).

**Problème résolu** : Une couleur peut n'être disponible qu'en certaines tailles (ex: Rouge uniquement en S,M,L mais Bleu en S,M,L,XL,XXL).

- [x] **B.2.1** — Migration SQL : champ `size` → `available_sizes` (JSON) ✅
- [x] **B.2.2** — Ajout `size_group` dans `customization_options` (groupes: Lettres, Chiffres, Enfants) ✅
- [x] **B.2.3** — Méthode `getSizesGrouped()` dans CustomizationOption.php ✅
- [x] **B.2.4** — ProductColorImage.php : support JSON available_sizes ✅
- [x] **B.2.5** — UI product-form.php : toggles tailles par variante (multi-sélection) ✅
- [x] **B.2.6** — Suppression section tailles standalone du formulaire ✅
- [x] **B.2.7** — CSS mini-toggles pour sélection tailles compacte ✅
- [x] **B.2.8** — JavaScript dynamique pour nouvelles variantes ✅

**Fichiers créés** :
- `sql/migrate_variant_sizes.sql` — Migration complète (available_sizes JSON + size_group + tailles par défaut)

**Fichiers modifiés** :
- `app/models/ProductColorImage.php` — Gestion JSON available_sizes
- `app/models/CustomizationOption.php` — getSizesGrouped() + getSizesSimple()
- `admin/product-form.php` — UI variantes avec toggles tailles multi-sélection

**Note** : Exécuter `sql/migrate_variant_sizes.sql` sur la BDD production.

**Priorité** : ✅ TERMINÉ

### ✅ B.3 — Site public : filtrage tailles par couleur (TERMINÉ 2026-01-18)
> Adapter le site public pour afficher uniquement les tailles disponibles pour la couleur sélectionnée.

- [x] **B.3.1** — product.php : charger available_sizes de la variante sélectionnée (colorSizes JSON) ✅
- [x] **B.3.2** — Filtrer le sélecteur tailles dynamiquement (JS filterSizesByColor()) ✅
- [x] **B.3.3** — Masquer tailles non disponibles + auto-resélection si taille courante indisponible ✅

**Fichier modifié** :
- `public/product.php` — Ajout $colorSizes PHP, colorSizes JS, filterSizesByColor()

**Priorité** : ✅ TERMINÉ

### ✅ B.4 — Refonte Cart.php Ultra-Moderne (TERMINÉ 2026-01-18)
> Refonte complète de la page panier avec design 2026 glassmorphism et fonctionnalités e-commerce.

- [x] **B.4.1** — Design glassmorphism 2026 (backdrop-filter, gradients) ✅
- [x] **B.4.2** — Section code promo améliorée avec validation AJAX ✅
- [x] **B.4.3** — Options livraison (Standard gratuit / Express 5.90€) avec AJAX ✅
- [x] **B.4.4** — Section paiement CB avec icônes (Visa, MC, Amex, CB) ✅
- [x] **B.4.5** — Section upsells "Vous aimerez aussi" avec CSS moderne ✅
- [x] **B.4.6** — Fix lien upsells (configurateur.php → product.php) ✅
- [x] **B.4.7** — Footer complet avec navigation, infos, contact ✅
- [x] **B.4.8** — Design responsive mobile optimisé ✅

**Fichier modifié** :
- `public/cart.php` — Refonte complète 1400+ lignes

**Fonctionnalités ajoutées** :
- Livraison : sauvegarde en session, calcul dynamique total
- Code promo : validation AJAX avec feedback visuel
- Trust badges : paiement sécurisé, livraison rapide, satisfaction garantie
- Upsells : cards modernes avec hover effects

**Priorité** : ✅ TERMINÉ

---

## PERSONNALISATION — Partage & Viralité

### C.1 — Partage de création (lien public)
> Permettre d'envoyer un lien public de la création produit pour demander un avis.

- [ ] **C.1.1** — Définir l'architecture (token unique, expiration optionnelle)
- [ ] **C.1.2** — Créer la table `shared_creations` (token, product_id, customization_json, created_at, expires_at)
- [ ] **C.1.3** — Endpoint génération lien `/share/create`
- [ ] **C.1.4** — Page publique lecture seule `/share/{token}`
- [ ] **C.1.5** — Aperçu fidèle (design, texte, position, technique)
- [ ] **C.1.6** — CTA "Créer le mien" vers configurateur

**Priorité** : 🟢 BASSE (conversion/viralité future)

**À décider plus tard** :
- Expiration du lien (24h, 7j, jamais)
- Protection par token ou lien public direct

---

## BRANDING FRONT — Identité Visuelle Site Client

### D.1 — Panneau de branding front (piloté depuis admin)
> Permettre à l'admin de personnaliser l'identité visuelle du site public (couleurs, typographies, styles de boutons) afin d'aligner le rendu client avec son image de marque, **sans impacter l'interface d'administration**.

⚠️ **IMPORTANT** : L'admin reste neutre et stable. Ce branding concerne UNIQUEMENT le site front (pages publiques).

#### D.1.A — Couleurs principales FRONT
- [ ] **D.1.A.1** — Table `branding_settings` (key, value, type)
- [ ] **D.1.A.2** — Admin : sélecteur couleur principale (CTA, boutons)
- [ ] **D.1.A.3** — Admin : sélecteur couleur secondaire (accents)
- [ ] **D.1.A.4** — Admin : sélecteur couleur texte principal
- [ ] **D.1.A.5** — Admin : sélecteur couleur titres

#### D.1.B — Typographie FRONT
- [ ] **D.1.B.1** — Admin : sélecteur police principale
- [ ] **D.1.B.2** — Admin : sélecteur police titres (H1, H2, H3)
- [ ] **D.1.B.3** — Admin : sélecteur police paragraphes

#### D.1.C — Application FRONT
- [ ] **D.1.C.1** — Génération CSS dynamique depuis settings
- [ ] **D.1.C.2** — Application sur pages produit
- [ ] **D.1.C.3** — Application sur configurateur/personnalisation
- [ ] **D.1.C.4** — Application sur panier/checkout
- [ ] **D.1.C.5** — Application sur pages publiques (home, blog, etc.)

**Priorité** : 🟢 BASSE (branding futur)

**Intention produit** :
> Donner le sentiment à l'admin que son site client est vraiment "le sien", sans page builder, sans custom CSS sauvage, sans duplication de thèmes. On reste SaaS, scalable, multi-tenant.

---

## DESIGN — Personnalisation Produit

### E.1 — Refonte UX configurateur (inspiration Canva / Yoursurprise)
> Objectif : moderniser l'expérience de personnalisation produit.

#### ✅ STEP DESIGN-0 — Architecture UX/UI (VALIDÉ)
- [x] **E.1.0.1** — Layout structurel Desktop (3 colonnes)
- [x] **E.1.0.2** — Layout structurel Mobile (canvas + bottom bars)
- [x] **E.1.0.3** — Liste composants UI
- [x] **E.1.0.4** — Flow utilisateur (arrivée → panier)
- [x] **E.1.0.5** — Points à valider (UX/Technique/Business)
- [x] **E.1.0.6** — Validation humaine des choix ✅

**Décisions validées (2026-01-18) :**

| Catégorie | Décision | Valeur |
|-----------|----------|--------|
| UX | Panneau outils Desktop | Gauche (240px) |
| UX | Panneau outils Mobile | Bottom toolbar |
| UX | Panneau propriétés | Drawer contextuel (sur sélection) |
| UX | Panneau calques | Dédié (séparé des propriétés) |
| UX | Snap magnétique | Soft (centre/bords zone) + toggle |
| UX | Limite éléments | MAX 10 |
| UX | Undo/Redo | Phase 2 |
| UX | Autosave brouillon | localStorage (JSON) |
| UX | Preview rendu réel | Modal séparée |
| Tech | Librairie rendu | Konva.js (fallback DOM overlay) |
| Tech | Format sérialisation | JSON obligatoire (source vérité) |
| Tech | Export panier | JSON + image preview |
| Tech | Compression upload | Client-side resize + serveur WebP |
| Business | Designs premium | NON (tous gratuits) |
| Business | Upload client | OUI (10 Mo max + resize) |
| Business | Export PNG | OUI (partage social) |

#### ✅ STEP DESIGN-1 — Wireframes & Spécifications (VALIDÉ)
- [x] **E.1.1.1** — Wireframe Desktop avec états (Texte/Photo/Design/Calques) ✅
- [x] **E.1.1.2** — Wireframe Mobile avec bottom toolbar 2 parties ✅
- [x] **E.1.1.3** — Mapping interactions drag/drop tactile ✅
- [x] **E.1.1.4** — Validation humaine wireframes ✅

#### ✅ STEP DESIGN-2 — Implémentation (TERMINÉ 2026-01-18)
- [x] **E.1.2.1** — Structure HTML/CSS canvas central ✅
- [x] **E.1.2.2** — Panneau outils (texte, image, design) ✅
- [x] **E.1.2.3** — Drag & drop éléments (Konva.js) ✅
- [x] **E.1.2.4** — Drawer propriétés contextuel ✅
- [x] **E.1.2.5** — Panneau calques dédié ✅
- [x] **E.1.2.6** — Responsive mobile bottom toolbar ✅
- [x] **E.1.2.7** — Sérialisation JSON + localStorage ✅
- [x] **E.1.2.8** — Export image preview ✅
- [x] **E.1.2.9** — Intégration product.php avec feature toggle ?v2=1 ✅
- [x] **E.1.2.10** — Connexion formulaire panier (customization_json) ✅

**Fichiers créés** :
- `public/assets/css/configurator.css` — 800+ lignes, layout 3 colonnes desktop, mobile bottom toolbar
- `public/assets/js/configurator.js` — 1400+ lignes, Konva.js, state management, serialization

**Fichiers modifiés** :
- `public/product.php` — Feature toggle `?v2=1`, HTML v2, injection données, chargement configurator.js

#### ✅ STEP DESIGN-2.5 — Finitions Configurateur (TERMINÉ 2026-01-18)
- [x] **E.1.2.11** — Fix drawer dupliqué (suppression HTML drawer) ✅
- [x] **E.1.2.12** — Chargement dynamique polices Google Fonts ✅
- [x] **E.1.2.13** — Sélecteur couleur produit (onglet Design) ✅
- [x] **E.1.2.14** — Images par variante couleur ✅
- [x] **E.1.2.15** — Bouton Sauvegarder avec feedback ✅
- [x] **E.1.2.16** — Bouton Partager (native share / clipboard) ✅
- [x] **E.1.2.17** — Simplification barre d'actions ✅

**Note** : Les boutons "Disposer" fonctionnent (nécessitent élément sélectionné). Les designs sont des placeholders (DESIGN-3 requis).

#### ✅ STEP DESIGN-2.6 — Mise en production (TERMINÉ 2026-01-18)
- [x] **E.1.2.18** — V2 par défaut (retrait flag ?v2=1) ✅
- [x] **E.1.2.19** — Bouton "Ajouter le texte" visible ✅
- [x] **E.1.2.20** — Couleur "Original" auto-ajoutée ✅

#### ✅ STEP DESIGN-2.7 — Techniques dans configurateur (TERMINÉ 2026-01-18)
- [x] **E.1.2.21** — Section techniques dans panneau Texte ✅
- [x] **E.1.2.22** — Sélection technique avec mise en surbrillance ✅
- [x] **E.1.2.23** — Prix dynamique selon technique ✅
- [x] **E.1.2.24** — Bouton aperçu (oeil) → modal rendu réel ✅

#### ✅ STEP DESIGN-2.8 — Design Ultra-Moderne 2026 (TERMINÉ 2026-01-18)
- [x] **E.1.2.25** — Custom dropdowns glass morphism (polices + techniques) ✅
- [x] **E.1.2.26** — Animations cubic-bezier fluides ✅
- [x] **E.1.2.27** — Fix sélecteur techniques coupé sur mobile ✅
- [x] **E.1.2.28** — Ajout techniques dans mobile drawer ✅
- [x] **E.1.2.29** — Fix image produit disparaît (CORS) ✅
- [x] **E.1.2.30** — Bouton "Ajouter le texte" remonté en haut du drawer ✅
- [x] **E.1.2.31** — Mobile drawer ultra-moderne (pastilles 40px, gradients) ✅
- [x] **E.1.2.32** — Bouton "Ajouter le texte" inline avec input (icône +) ✅

**Fichiers modifiés** :
- `public/product.php` — Custom dropdowns HTML
- `public/assets/css/configurator.css` — +200 lignes glass morphism
- `public/assets/js/configurator.js` — Dropdown handlers + mobile content

#### ✅ STEP DESIGN-2.9 — Polish UI Ultra-Moderne 2026 (TERMINÉ 2026-01-19)
- [x] **E.1.2.33** — Suppression tous pointillés (zone impression, snap guides) ✅
- [x] **E.1.2.34** — Sélecteur police amélioré (aperçu, animations hover, selected effect) ✅
- [x] **E.1.2.35** — Sélecteur technique avec badges prix premium (gradient pink) ✅
- [x] **E.1.2.36** — Bouton Original avec icône reset dans onglet Design ✅
- [x] **E.1.2.37** — Tailles produit redesignées (glassmorphism, hover lift) ✅
- [x] **E.1.2.38** — Quantité +/- modernisé (style 2026) ✅
- [x] **E.1.2.39** — CTA Ajouter au panier premium (shimmer effect) ✅
- [x] **E.1.2.40** — Toolbar mobile améliorée (backdrop blur, indicateurs actifs) ✅
- [x] **E.1.2.41** — Architecture designs admin prête (chargement packs avec images) ✅

**Fichiers modifiés** :
- `public/product.php` — Bouton Original SVG, designs depuis packs admin
- `public/assets/css/configurator.css` — +280 lignes styles ultra-modernes 2026
- `public/assets/js/configurator.js` — Suppression pointillés zone/guides

#### ✅ STEP DESIGN-2.10 — Fix Responsive Mobile (TERMINÉ 2026-01-19)
- [x] **E.1.2.42** — Image produit centrée et agrandie sur mobile ✅
- [x] **E.1.2.43** — Suppression marges excessives du canvas (padding 16px → 0) ✅
- [x] **E.1.2.44** — Calcul dynamique hauteur image (100vh - 280px) ✅
- [x] **E.1.2.45** — Bouton "Voir le rendu réel" visible (remonté) ✅
- [x] **E.1.2.46** — Optimisation petits écrans (max-width: 400px) ✅

**Fichiers modifiés** :
- `public/assets/css/configurator.css` — Lignes 3683-3751 styles mobile optimisés

#### STEP DESIGN-3 — Admin Designs (À VENIR)
- [~] **E.1.3.1** — Table design_templates + CRUD (optionnel, packs déjà utilisables)
- [~] **E.1.3.2** — Admin création designs (upload SVG/PNG)
- [x] **E.1.3.3** — Grille templates côté client (utilise packs actifs avec cover_image) ✅
- [ ] **E.1.3.4** — JS ajout design au canvas (click → loadPreset())

**Priorité** : 🟡 MOYENNE (architecture prête, packs utilisables)

**Statut** : 🟢 CONFIGURATEUR V2.4 EN PRODUCTION — UI Ultra-Moderne 2026 + Responsive Mobile Optimisé
