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

**Phase actuelle** : STEP 2 & 3 TERMINÉS

**Statut global** : 🟢 Admin complet fonctionnel

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

## Pages Admin Disponibles

| Page | URL | Fonctionnalités |
|------|-----|-----------------|
| Dashboard | /admin/dashboard.php | Stats, dernières commandes |
| Produits | /admin/products.php | Liste, toggle actif, supprimer |
| Formulaire produit | /admin/product-form.php | Ajout et modification |
| Commandes | /admin/orders.php | Liste, filtres, changer statut |
| Clients | /admin/customers.php | Liste des clients inscrits |
| Paramètres | /admin/settings.php | Changer mot de passe |

---

## Prochaines Étapes

### STEP 4 (à venir)
- [ ] Formulaire de personnalisation côté client (public)
- [ ] Panier
- [ ] Processus de commande complet
- [ ] Envoi d'emails (confirmation commande)

### Améliorations futures
- [ ] Upload images produits
- [ ] Export PDF commandes
- [ ] Statistiques avancées
- [ ] Notifications email vendeur

---

## Structure Actuelle du Projet

```
/personnaly.fr/
├── PROGRESSION.md
├── .gitignore
├── public/
│   ├── index.php              ← Page d'accueil
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
│   │   └── Order.php
│   └── helpers/
│       └── functions.php
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── products.php           ← CRUD liste
│   ├── product-form.php       ← Ajout/modif
│   ├── orders.php             ← Gestion commandes
│   ├── customers.php          ← Liste clients
│   └── settings.php           ← Paramètres + mdp
└── sql/
    └── schema.sql
```

---

## Credentials

- **Admin** : admin@personnaly.fr / password (à changer !)
- **DB** : zajr1824_persosaas / zajr1824_perso

---

**FIN DU FICHIER DE PROGRESSION**
