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

---

## État Actuel

**Date dernière mise à jour** : 2026-01-16

**Phase actuelle** : STEP 1 - Fondations

**Statut global** : 🟡 En cours

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
| Page publique index.php | ✅ OK | public/index.php |
| Admin login | ✅ OK | admin/login.php |
| Admin dashboard | ✅ OK | admin/dashboard.php |
| Schéma SQL complet | ✅ OK | sql/schema.sql |
| Fichier PROGRESSION.md | ✅ OK | Ce fichier |
| Exécuter le SQL sur phpMyAdmin | ⏳ À FAIRE | Utilisateur doit exécuter |
| Test connexion DB | ⏳ À FAIRE | Après exécution SQL |

---

## Prochaines Étapes

### STEP 1 (en cours)
- [ ] Exécuter `sql/schema.sql` dans phpMyAdmin
- [ ] Tester la connexion à la base
- [ ] Tester le login admin (admin@personnaly.fr / admin123)
- [ ] Changer le mot de passe admin

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
/home/user/Perso/
├── PROGRESSION.md          ← CE FICHIER
├── .gitignore
├── public/
│   ├── index.php           ← Page d'accueil
│   └── .htaccess
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
│   ├── services/           ← (vide, pour plus tard)
│   └── helpers/
│       └── functions.php
├── admin/
│   ├── login.php
│   ├── dashboard.php
│   └── logout.php
└── sql/
    └── schema.sql          ← À exécuter dans phpMyAdmin
```

---

## Notes Importantes

1. **Credentials DB** : Ne jamais commiter `app/config/database.php`
2. **Admin par défaut** : admin@personnaly.fr / admin123 (CHANGER EN PROD)
3. **JSON pour personnalisations** : La table `order_customizations` utilise un champ JSON pour la flexibilité

---

## Blocages / Problèmes Connus

*Aucun pour le moment.*

---

## Commandes Git Utiles

```bash
# Pull sur o2switch
git pull origin claude/fastapi-customization-subdomain-GIlQI

# Après modifications
git add -A
git commit -m "Description"
git push origin claude/fastapi-customization-subdomain-GIlQI
```

---

**FIN DU FICHIER DE PROGRESSION**
