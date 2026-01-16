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

**Phase actuelle** : P2 - PREVIEW DRAG & DROP

**Statut global** : 🟡 P2 EN REFONTE - Passage au drag & drop contraint

---

## ✅ DÉCISION ARCHITECTURE - 2026-01-16

### Architecture validée

| Composant | Décision |
|-----------|----------|
| **Polices** | Table `fonts` avec Google Fonts (family+weights) + upload custom (.woff2) |
| **Import dynamique** | `google_import_url` générée automatiquement par PHP, jamais saisie |
| **Zones d'impression** | Table `product_print_zones` avec positions en %, contraintes, polices autorisées |
| **Packs thématiques** | P4 (après polices, preview, zones) |

### Décisions SQL finales (2026-01-16)

| Élément | Décision | Raison |
|---------|----------|--------|
| `DROP TABLE` ordre | product_print_zones AVANT fonts | Respect des FK |
| `google_import_url` | NULL dans INSERT, généré par PHP | Logique métier côté code |
| `allowed_fonts` | **TEXT** (pas JSON natif) | Compatibilité MySQL 5.6+ o2switch |

### Contraintes MVP obligatoires

1. **Pas d'API Google Fonts** - Admin saisit family + weights manuellement
2. **Upload .woff2 uniquement** (.woff en fallback optionnel)
3. **css_key unique** - Généré automatiquement, non éditable
4. **google_import_url** - Générée automatiquement si source=google
5. **allowed_fonts TEXT** - NULL = toutes polices, sinon JSON array en TEXT

### Décision UX P2 (2026-01-16)

| Approche | Décision | Raison |
|----------|----------|--------|
| Positions prédéfinies | ❌ REJETÉ | "gauche/droite" = inutilisable pour l'impression |
| Drag & drop contraint | ✅ ADOPTÉ | Visuel, intuitif, données précises en % |
| Stockage position | `{"x": 48.2, "y": 55.7, "zone_id": 1}` | Coordonnées relatives à la zone |
| Contrainte zone | Obligatoire | Empêche les erreurs de placement |

### Priorités MVP (ordre obligatoire)

| Priorité | Composant | Description |
|----------|-----------|-------------|
| **P1** | Polices | Table fonts + admin + loader CSS dynamique |
| **P2** | Preview | Image produit + overlay texte positionné |
| **P3** | Zones | Table product_print_zones + admin par produit |
| **P4** | Packs v1 | CRUD packs + affectation produit |

### Tables SQL à créer

```
fonts
├── id, name, family, css_key (UNIQUE, auto-généré)
├── source (google/custom)
├── google_weights, google_import_url (NULL, générée par PHP)
├── custom_woff2_url, custom_woff_url
├── category (sans-serif, serif, script, display, handwriting)
└── active, sort_order, created_at, updated_at

product_print_zones
├── id, product_id (FK products)
├── zone_name, zone_label
├── pos_x, pos_y, width, height (en %)
├── max_chars, max_lines
├── default_font_id (FK fonts), allowed_fonts (TEXT, JSON array)
└── active, sort_order, created_at
```

### Vision produit (rappel permanent)

**PERSONNALY EST** :
- Un moteur de personnalisation modulaire
- Des polices administrables (pas codées en dur)
- Des zones d'impression métier
- Une preview évolutive réaliste
- Une base scalable PHP/MySQL sur o2switch

**PERSONNALY N'EST PAS** :
- Un formulaire figé
- Un choix de X polices codées en dur
- Une preview cosmétique basique

---

## Historique des Tâches

### 2026-01-16 - Session 1

| Tâche | Statut | Notes |
|-------|--------|-------|
| Step 1 : Fondations PHP/MySQL | ✅ OK | Structure MVC, PDO, Auth |
| Connexion MySQL | ✅ OK | Credentials o2switch |
| Design girly moderne | ✅ OK | Rose + Vert menthe + Noir |
| SQL exécuté | ✅ OK | Tables créées |
| Login admin testé | ✅ OK | password (pas admin123) |
| Step 2 : Liste produits | ✅ OK | admin/products.php |
| Step 2 : Formulaire produit | ✅ OK | admin/product-form.php |
| Step 2 : CRUD complet | ✅ OK | Ajout/Modif/Suppression/Toggle |
| Step 3 : Liste commandes | ✅ OK | admin/orders.php |
| Step 3 : Changement statut | ✅ OK | Dropdown avec statuts |
| Page clients | ✅ OK | admin/customers.php |
| Page paramètres | ✅ OK | admin/settings.php + changer mdp |

### 2026-01-16 - Session 2

| Tâche | Statut | Notes |
|-------|--------|-------|
| Step 4 : Classe Cart | ✅ OK | app/helpers/Cart.php (session) |
| Step 4 : Page produit | ✅ OK | public/product.php + personnalisation |
| Step 4 : Page panier | ✅ OK | public/cart.php + quantités |
| Step 4 : Checkout | ✅ OK | public/checkout.php + commande |
| Lien produits accueil | ✅ OK | Bouton "Personnaliser" fonctionnel |
| Panier navbar | ✅ OK | Badge avec compteur |

### 2026-01-16 - Session 3 (Priorités Hautes)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Helper Email | ✅ OK | app/helpers/Email.php (mail PHP) |
| Email confirmation client | ✅ OK | Template HTML moderne, envoyé au checkout |
| Email notification admin | ✅ OK | Alerte nouvelle commande avec détails |
| Page détail commande | ✅ OK | admin/order.php (items, client, statut) |
| Fiche client détaillée | ✅ OK | admin/customer.php (stats, historique) |
| Liens navigation | ✅ OK | Commandes et clients cliquables |

### 2026-01-16 - Session 4 (Options + Images)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Table customization_options | ✅ OK | sql/options.sql (tailles, couleurs, positions) |
| Model CustomizationOption | ✅ OK | app/models/CustomizationOption.php |
| Page admin Options | ✅ OK | admin/options.php (CRUD tailles/couleurs/positions) |
| Options dynamiques frontend | ✅ OK | product.php charge depuis DB |
| Upload images produits | ✅ OK | admin/product-form.php (JPG, PNG, WebP, GIF) |
| Preview avec vraie image | ✅ OK | Affichage image produit sur accueil et fiche |

### 2026-01-16 - Session 5 (P1 - Polices Administrables)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Table fonts | ✅ OK | sql/fonts.sql (Google + Custom upload) |
| Table product_print_zones | ✅ OK | sql/fonts.sql (zones P3) |
| Model Font | ✅ OK | app/models/Font.php (CRUD + auto-génération css_key/url) |
| Helper FontLoader | ✅ OK | app/helpers/FontLoader.php (CSS dynamique) |
| Page admin Polices | ✅ OK | admin/fonts.php (ajout Google/Custom, preview, toggle) |
| Menu admin + Polices | ✅ OK | admin/includes/sidebar.php |
| Intégration frontend | ✅ OK | product.php charge polices depuis DB + FontLoader |

### 2026-01-16 - Session 5 (P2 - Preview v1 → ABANDONNÉ)

| Tâche | Statut | Notes |
|-------|--------|-------|
| CSS positions preview | ❌ ABANDONNÉ | Classes pos-centre/gauche/droite inadaptées |
| JS position dynamique | ❌ ABANDONNÉ | Boutons radio ≠ UX moderne |

**Raison abandon** : Le système de positions prédéfinies (gauche/droite/centre) n'est pas un outil visuel moderne. Le texte sort du produit, l'utilisateur ne comprend pas où sera imprimé son texte, et les données stockées ("gauche") sont inutilisables pour l'impression.

### 2026-01-16 - Session 5 (P2 - Preview Drag & Drop)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Supprimer pos-centre/gauche/droite | ⏳ À FAIRE | Nettoyer l'ancienne approche |
| Overlay zone d'impression | ⏳ À FAIRE | Bordure pointillée sur la zone |
| Drag & drop souris | ⏳ À FAIRE | mousedown/mousemove/mouseup |
| Drag & drop tactile | ⏳ À FAIRE | touchstart/touchmove/touchend |
| Contrainte dans la zone | ⏳ À FAIRE | Math.max/min sur les limites |
| Stockage {x, y, zone_id} | ⏳ À FAIRE | Hidden inputs + data_json |
| Indication UX | ⏳ À FAIRE | "Déplacez le texte" |

---

## Design System

### Couleurs
- **Rose** : #FF69B4 (principal), #FF1493 (foncé), #FFB6C1 (clair)
- **Vert Menthe** : #3DFFC0 (principal), #00D9A0 (foncé), #98FFD6 (clair)
- **Noir** : #0D0D0D, #1A1A2E, #16213E

### Polices
- **UI** : Inter (interface utilisateur)
- **Personnalisation** : Dynamiques depuis table `fonts` (admin)
  - Google Fonts : family + weights → URL auto-générée
  - Custom : Upload .woff2 (.woff fallback)

---

## Pages Disponibles

### Admin (protégées)

| Page | URL | Fonctionnalités |
|------|-----|-----------------|
| Dashboard | /admin/dashboard.php | Stats, dernières commandes |
| Produits | /admin/products.php | Liste, toggle actif, supprimer |
| Formulaire produit | /admin/product-form.php | Ajout et modification |
| Commandes | /admin/orders.php | Liste, filtres, changer statut |
| Détail commande | /admin/order.php?id=X | Items, personnalisations, client, statut |
| **Polices** | /admin/fonts.php | CRUD Google/Custom, preview, toggle actif |
| Options | /admin/options.php | Tailles, couleurs, positions |
| Clients | /admin/customers.php | Liste des clients inscrits |
| Fiche client | /admin/customer.php?id=X | Stats, infos, historique commandes |
| Paramètres | /admin/settings.php | Changer mot de passe |

### Public (clients)

| Page | URL | Fonctionnalités |
|------|-----|-----------------|
| Accueil | / | Hero, catalogue produits |
| Produit | /public/product.php?id=X | Personnalisation, aperçu |
| Panier | /public/cart.php | Liste articles, modifier quantité |
| Checkout | /public/checkout.php | Formulaire commande |

---

## Prochaines Étapes

### P2 - Preview Drag & Drop 🔄 EN REFONTE
- [x] ~~Positions prédéfinies (centre/gauche/droite)~~ **ABANDONNÉ** - UX inadaptée
- [ ] Drag & drop souris (desktop)
- [ ] Drag & drop tactile (mobile)
- [ ] Zone d'impression visuelle (overlay pointillé)
- [ ] Contrainte du texte dans la zone
- [ ] Stockage position en % : `{ "x": 48.2, "y": 55.7, "zone_id": 1 }`
- [ ] Indication utilisateur "Déplacez le texte"

### P3 - Zones d'impression
- [ ] Admin zones par produit (product_print_zones)
- [ ] Positions %, contraintes (max_chars, max_lines)
- [ ] Polices autorisées par zone

### P4 - Packs thématiques v1
- [ ] Table packs + CRUD admin
- [ ] Affectation pack → produit

### Améliorations futures
- [ ] Connexion/Inscription clients
- [ ] Page "Mes commandes" client
- [ ] Codes promo
- [ ] Export PDF commandes
- [ ] Paiement en ligne (Stripe)

---

## Structure Actuelle du Projet

```
/personnaly.fr/
├── PROGRESSION.md
├── .gitignore
├── public/
│   ├── index.php              ← Page d'accueil + catalogue
│   ├── product.php            ← Personnalisation produit (polices dynamiques)
│   ├── cart.php               ← Panier
│   ├── checkout.php           ← Finalisation commande
│   ├── .htaccess
│   ├── uploads/
│   │   └── fonts/             ← Upload polices custom (.woff2/.woff)
│   └── assets/css/
│       ├── style.css          ← Design system
│       └── admin.css          ← Styles admin
├── app/
│   ├── config/
│   │   └── database.php       ← NE PAS COMMITER
│   ├── core/
│   │   ├── Database.php
│   │   └── Auth.php
│   ├── models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Order.php
│   │   ├── CustomizationOption.php  ← Options admin
│   │   └── Font.php           ← CRUD polices + auto-génération
│   └── helpers/
│       ├── functions.php
│       ├── Cart.php           ← Gestion panier session
│       ├── Email.php          ← Envoi emails (mail PHP)
│       └── FontLoader.php     ← Chargement CSS polices dynamique
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── products.php           ← CRUD liste
│   ├── product-form.php       ← Ajout/modif + upload image
│   ├── orders.php             ← Gestion commandes
│   ├── order.php              ← Détail commande
│   ├── fonts.php              ← Gestion polices (Google + Custom)
│   ├── customers.php          ← Liste clients
│   ├── customer.php           ← Fiche client
│   ├── options.php            ← Gestion tailles/couleurs/positions
│   ├── settings.php           ← Paramètres + mdp
│   └── includes/
│       └── sidebar.php        ← Menu admin commun
└── sql/
    ├── schema.sql
    ├── options.sql            ← Table customization_options
    └── fonts.sql              ← Tables fonts + product_print_zones
```

---

## Credentials

- **Admin** : admin@personnaly.fr / password (à changer !)
- **DB** : zajr1824_persosaas / zajr1824_perso

---

## Fonctionnalités Clés Step 4

### Personnalisation produit
- Choix taille (XS à XXL)
- Choix couleur (blanc, noir, rose, menthe, bleu, gris)
- Texte personnalisé (max 50 caractères)
- Position du texte (centre, gauche, droite, dos)
- Aperçu en direct

### Panier
- Stockage en session PHP
- Gestion quantités (+/-)
- Suppression articles
- Calcul automatique total
- Badge compteur navbar

### Checkout
- Formulaire coordonnées
- Adresse de livraison
- Création client automatique
- Création commande avec personnalisations
- Page confirmation avec numéro commande

---

## Audit de Conformité (2026-01-16)

### Conformité prompt initial

| Exigence | Statut |
|----------|--------|
| Aperçu temps réel | ✅ OUI |
| Fidélité preview → panier → commande | ✅ OUI |
| Structure pour novices (options admin) | ✅ OUI |
| Séparation logique/affichage | ⚠️ PARTIEL |
| Gestion clients admin complète | ✅ OUI |
| Réception commandes (email) | ✅ OUI |
| Upsell / options commerciales | ❌ NON |

### Fonctionnalités restantes (priorité haute)

- [x] Envoi email confirmation commande ✅
- [x] Envoi email notification admin ✅
- [x] Page détail commande admin ✅
- [x] Fiche client détaillée avec historique ✅

### Fonctionnalités restantes (priorité moyenne)

- [x] Options personnalisation administrables (tailles, couleurs) ✅
- [x] Upload images produits ✅
- [x] Preview avec vraie image produit ✅

### Fonctionnalités restantes (priorité basse)

- [ ] Connexion/Inscription clients
- [ ] Page "Mes commandes" client
- [ ] Codes promo
- [ ] Multi-boutiques (architecture préparée mais non active)

### Décisions techniques prises

1. **Stack** : PHP 7.4+ / MySQL / PDO (compatible o2switch)
2. **Auth** : Sessions PHP + bcrypt
3. **Panier** : Session PHP (pas de base)
4. **Personnalisations** : JSON dans `order_customizations.data_json`
5. **Design** : CSS natif, pas de framework (léger)
6. **JS** : Vanilla JS, pas de framework (simple)

### Scalabilité prévue

- Jusqu'à ~500 boutiques : o2switch mutualisé viable
- Emails : externaliser vers Brevo/Mailjet dès maintenant
- Multi-boutiques : ajout `shop_id` aux tables quand nécessaire

---

**FIN DU FICHIER DE PROGRESSION**
