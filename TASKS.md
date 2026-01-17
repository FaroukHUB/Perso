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
| P6 | 🔴 À FAIRE | Voir ci-dessous |

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

## STEP 5 — UPSELLS (TRÈS IMPORTANT)

> Proposer des produits/options supplémentaires pour augmenter le panier moyen.

### 5.1 — Architecture DB

- [ ] **5.1.1** — Créer table `upsells` (id, name, type, condition_type, condition_value, offer_type, offer_value, discount, priority, status)
- [ ] **5.1.2** — Types de conditions : panier_min, produit_specifique, technique_specifique, categorie
- [ ] **5.1.3** — Types d'offres : produit, option, reduction

### 5.2 — Admin CRUD Upsells

- [ ] **5.2.1** — admin/upsells.php (liste)
- [ ] **5.2.2** — admin/upsell-form.php (création avec règles conditionnelles)
- [ ] **5.2.3** — Interface : SI [condition] ALORS proposer [offre]
- [ ] **5.2.4** — Lien sidebar admin

### 5.3 — Affichage client

- [ ] **5.3.1** — cart.php : section "Vous aimerez aussi" basée sur règles
- [ ] **5.3.2** — checkout.php : upsells avant validation
- [ ] **5.3.3** — Ajout rapide au panier depuis upsell

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

**Dernière mise à jour** : 2026-01-17 — Audit complet + restructuration
