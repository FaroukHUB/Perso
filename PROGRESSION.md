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

**Date dernière mise à jour** : 2026-01-16

**Phase actuelle** : RECADRAGE TERMINÉ - Toutes les priorités HAUTE complétées

**Statut global** : 🟢 COMPLET - Phase haute priorité terminée, prêt pour déploiement

---

## 🎯 VISION PERSONNALY (RAPPEL PERMANENT)

**PERSONNALY EST** :
- Un outil de personnalisation **moderne et visuel** (2026)
- Une expérience **rassurante** pour le client (zoom, preview fidèle)
- Un système **administrable** sans code
- Une preview **réaliste** (pas cosmétique)

**PERSONNALY N'EST PAS** :
- Un formulaire figé "qui marche"
- Un site e-commerce basique
- Une solution "année 2000"

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
| **Packs thématiques** | ❌ NON | P4 non commencé |

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
| 9 | Packs thématiques (P4) | BASSE | Haute |

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
