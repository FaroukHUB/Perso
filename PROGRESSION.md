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

**Phase actuelle** : STEP 1 - Fondations (EN COURS)

**Statut global** : 🟢 Connexion DB OK - Design girly appliqué

---

## Historique des Tâches

### 2026-01-16 - Session 1 : Mise en place initiale

| Tâche | Statut | Notes |
|-------|--------|-------|
| Nettoyage repo (suppression Python/FastAPI) | ✅ OK | Tout le code Python supprimé |
| Création structure PHP MVC | ✅ OK | Dossiers app/, public/, admin/, sql/ |
| Configuration database.php | ✅ OK | Credentials o2switch configurés |
| Classe Database (PDO singleton) | ✅ OK | app/core/Database.php |
| Classe Auth | ✅ OK | app/core/Auth.php |
| Helpers (fonctions utilitaires) | ✅ OK | app/helpers/functions.php |
| Modèle User | ✅ OK | app/models/User.php |
| Modèle Product | ✅ OK | app/models/Product.php |
| Modèle Order | ✅ OK | app/models/Order.php |
| Page publique index.php | ✅ OK | Design girly appliqué |
| Admin login | ✅ OK | Design girly appliqué |
| Admin dashboard | ✅ OK | Design girly appliqué |
| Schéma SQL complet | ✅ OK | sql/schema.sql |
| Fichier PROGRESSION.md | ✅ OK | Ce fichier |
| Connexion MySQL | ✅ OK | Test réussi via debug-db.php |
| Design CSS girly moderne | ✅ OK | Rose + Vert menthe + Noir |
| Exécuter le SQL sur phpMyAdmin | ⏳ À FAIRE | Utilisateur doit exécuter |
| Test login admin | ⏳ À FAIRE | admin@personnaly.fr / admin123 |

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

## Prochaines Étapes

### STEP 1 (en cours)
- [ ] Exécuter `sql/schema.sql` dans phpMyAdmin
- [ ] Tester le login admin (admin@personnaly.fr / admin123)
- [ ] Changer le mot de passe admin
- [ ] Supprimer debug-db.php

### STEP 2 (à venir)
- [ ] CRUD Produits complet (admin/products.php)
- [ ] Upload images produits
- [ ] Gestion des catégories

### STEP 3 (à venir)
- [ ] Gestion des commandes (admin/orders.php)
- [ ] Changement de statut
- [ ] Affichage des personnalisations JSON

### STEP 4 (futur)
- [ ] Formulaire de personnalisation côté client
- [ ] Panier
- [ ] Processus de commande

---

## Structure Actuelle du Projet

```
/personnaly.fr/
├── PROGRESSION.md          ← CE FICHIER
├── .gitignore
├── public/
│   ├── index.php           ← Page d'accueil (design girly)
│   ├── test-db.php         ← Test connexion (à supprimer)
│   ├── debug-db.php        ← Debug connexion (à supprimer)
│   ├── .htaccess
│   ├── uploads/
│   └── assets/
│       └── css/
│           ├── style.css   ← Design system principal
│           └── admin.css   ← Styles admin
├── app/
│   ├── config/
│   │   ├── database.example.php
│   │   └── database.php    ← NE PAS COMMITER
│   ├── core/
│   │   ├── Database.php    ← Singleton PDO
│   │   └── Auth.php        ← Authentification
│   ├── models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   └── Order.php
│   ├── services/
│   └── helpers/
│       └── functions.php
├── admin/
│   ├── login.php           ← Design girly
│   ├── dashboard.php       ← Design girly
│   └── logout.php
└── sql/
    └── schema.sql          ← À exécuter dans phpMyAdmin
```

---

## Notes Importantes

1. **Credentials DB** : Ne jamais commiter `app/config/database.php`
2. **Admin par défaut** : admin@personnaly.fr / admin123 (CHANGER EN PROD)
3. **JSON pour personnalisations** : La table `order_customizations` utilise un champ JSON pour la flexibilité
4. **Design** : Ultra-moderne, girly, rose + vert menthe + noir, épuré

---

## Blocages / Problèmes Résolus

| Problème | Solution |
|----------|----------|
| Connexion MySQL refusée | Associer l'utilisateur à la base dans cPanel |

---

**FIN DU FICHIER DE PROGRESSION**
