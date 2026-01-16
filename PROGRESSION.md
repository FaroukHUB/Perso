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

**Phase actuelle** : RECADRAGE VISION PRODUIT

**Statut global** : 🟡 Pause - Validation architecture requise

---

## ⚠️ RECADRAGE 2026-01-16

### Problème identifié

L'implémentation actuelle (6 polices fixes, preview basique) ne correspond pas à la vision produit PERSONNALY :

| Aspect | Implémentation actuelle | Vision attendue |
|--------|-------------------------|-----------------|
| Polices | 6 polices codées en dur dans head | Admin configure via Google Fonts + upload custom |
| Options | Système basique (taille/couleur) | Moteur extensible + packs thématiques |
| Preview | Texte sur fond coloré | Rendu métier (position réelle, simulation textile) |

### Vision PERSONNALY confirmée

PERSONNALY doit être un **moteur de personnalisation**, pas un simple formulaire :

1. **Polices administrables** : Google Fonts (recherche/sélection) + upload custom
2. **Packs thématiques** : Collections culturelles/événementielles (Aïd, Mariage, Sport...)
3. **Preview métier** : Rendu réaliste, zones d'impression, lisibilité
4. **Aucune limite arbitraire** : Pas de "6 polices max" codé en dur

### Architecture proposée (en attente de validation)

#### Tables à créer

```
fonts
├── id, name, family, source (google/custom)
├── file_url, google_import, category
└── active, sort_order

theme_packs
├── id, name, slug, description, icon
└── active, sort_order

theme_pack_items
├── pack_id, type (font/color/text_suggestion)
└── value, label

product_print_zones (futur)
├── product_id, zone_name, x, y, width, height
└── max_chars, allowed_fonts
```

#### Workflow admin polices

1. Admin recherche dans Google Fonts (liste préchargée ou API)
2. OU Admin uploade une police custom (.woff2, .ttf)
3. Admin catégorise (Élégant, Moderne, Fun, Script...)
4. Admin active/désactive selon besoins

#### Workflow packs thématiques

1. Admin crée un pack (ex: "Aïd Mubarak")
2. Admin associe : polices recommandées + couleurs + textes suggérés
3. Client voit le pack comme raccourci de personnalisation

#### Preview évolutive

- Phase 1 : Texte sur fond (actuel)
- Phase 2 : Overlay sur image produit avec position
- Phase 3 : Canvas avec simulation textile
- Phase 4 : Mockup 3D (optionnel)

### Prochaine action

**ATTENTE VALIDATION** avant tout code :
- [ ] Validation architecture polices
- [ ] Validation architecture packs
- [ ] Définition scope Phase 1 (MVP)
- [ ] Priorités : Polices ? Packs ? Preview ?

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

---

## Design System

### Couleurs
- **Rose** : #FF69B4 (principal), #FF1493 (foncé), #FFB6C1 (clair)
- **Vert Menthe** : #3DFFC0 (principal), #00D9A0 (foncé), #98FFD6 (clair)
- **Noir** : #0D0D0D, #1A1A2E, #16213E

### Polices
- **Display** : Poppins (titres)
- **Body** : Inter (texte)

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

### STEP 5 (à venir)
- [ ] Envoi d'emails (confirmation commande)
- [ ] Upload images produits
- [ ] Page "Mes commandes" pour clients
- [ ] Connexion/Inscription clients

### Améliorations futures
- [ ] Export PDF commandes
- [ ] Statistiques avancées
- [ ] Notifications email vendeur
- [ ] Paiement en ligne (Stripe)
- [ ] Page détail commande admin

---

## Structure Actuelle du Projet

```
/personnaly.fr/
├── PROGRESSION.md
├── .gitignore
├── public/
│   ├── index.php              ← Page d'accueil + catalogue
│   ├── product.php            ← Personnalisation produit
│   ├── cart.php               ← Panier
│   ├── checkout.php           ← Finalisation commande
│   ├── .htaccess
│   ├── uploads/
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
│   │   └── CustomizationOption.php  ← Options admin
│   └── helpers/
│       ├── functions.php
│       ├── Cart.php           ← Gestion panier session
│       └── Email.php          ← Envoi emails (mail PHP)
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── products.php           ← CRUD liste
│   ├── product-form.php       ← Ajout/modif + upload image
│   ├── orders.php             ← Gestion commandes
│   ├── order.php              ← Détail commande
│   ├── customers.php          ← Liste clients
│   ├── customer.php           ← Fiche client
│   ├── options.php            ← Gestion tailles/couleurs/positions
│   └── settings.php           ← Paramètres + mdp
└── sql/
    ├── schema.sql
    └── options.sql            ← Table customization_options
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
