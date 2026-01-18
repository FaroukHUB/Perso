# TASKS — PERSONNALY

> **Fichier de suivi des tâches structuré**
> - `[ ]` = TODO
> - `[~]` = EN COURS
> - `[x]` = TERMINÉ
> - 1 tâche = 1 implémentation
> - Validation humaine AVANT chaque implémentation
> - Mise à jour TASKS.md + PROGRESSION.md après chaque tâche

---

## 📊 ÉTAT GLOBAL — 2026-01-17

| Phase | Statut | Détail |
|-------|--------|--------|
| P1-P3 | ✅ TERMINÉ | Polices, Drag&Drop, Zones |
| P4 | ✅ TERMINÉ | Packs / Idées |
| P5 | ✅ TERMINÉ | Page d'accueil dynamique |
| P6 STEP 1-5 | 🟡 EN COURS | Correctifs, WebP, Catégories, Techniques, Upsells+Codes Promo (CSS à refaire) |
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

**Dernière mise à jour** : 2026-01-18 — Clôture rapatriement upsells + structuration chantiers futurs

---

# 🟠 CHANTIERS IDENTIFIÉS — À PLANIFIER

> Ces tâches ont été identifiées lors de la session du 2026-01-18.
> Aucune n'est encore validée pour implémentation.
> Chaque STEP nécessitera validation humaine avant exécution.

---

## ADMIN — Stabilisation & UX

### A.1 — BUG : Catégories produits non enregistrées
> Les catégories sélectionnées dans la création produit ne sont pas sauvegardées.

- [ ] **A.1.1** — Analyser le formulaire product-form.php (checkboxes catégories)
- [ ] **A.1.2** — Vérifier la sauvegarde backend (table pivot product_categories)
- [ ] **A.1.3** — Corriger le bug de relation
- [ ] **A.1.4** — Tester la persistance catégories

**Priorité** : 🔴 HAUTE (bug critique)

### A.2 — Sidebar admin : problème de hauteur
> La sidebar ne descend pas assez, impossible d'accéder aux items du bas.
> Bug spécifique : dans l'onglet Options, la sidebar masque le contenu.

- [ ] **A.2.1** — Diagnostiquer le CSS de la sidebar (height, overflow, position)
- [ ] **A.2.2** — Corriger la hauteur pour scroll complet
- [ ] **A.2.3** — Fixer le conflit avec l'onglet Options
- [ ] **A.2.4** — Tester sur différentes résolutions

**Priorité** : 🟡 MOYENNE (UX admin)

---

## PRODUIT — Structure & Variantes

### B.1 — Refonte gestion couleurs/variantes admin
> Simplifier l'interface de création produit pour éviter confusion et duplication.

- [ ] **B.1.1** — Retirer le bouton "Ajouter une couleur" (garder uniquement "Ajouter une variante")
- [ ] **B.1.2** — Retirer tailles et couleurs de l'onglet Options global
- [ ] **B.1.3** — Centraliser la gestion tailles/couleurs dans la création produit uniquement
- [ ] **B.1.4** — Documenter le nouveau workflow

**Priorité** : 🟡 MOYENNE (clarification UX)

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

#### STEP DESIGN-3 — Admin Designs (À VENIR)
- [ ] **E.1.3.1** — Table design_templates + CRUD
- [ ] **E.1.3.2** — Admin création designs (upload SVG/PNG)
- [ ] **E.1.3.3** — Grille templates côté client
- [ ] **E.1.3.4** — JS ajout design au canvas (click → addDesignElement())

**Priorité** : 🟡 MOYENNE (peut être Phase 2)

**Statut** : 🟢 CONFIGURATEUR V2 EN PRODUCTION — DESIGN-3 optionnel
