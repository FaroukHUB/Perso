# TASKS — PERSONNALY

> **Fichier de suivi des tâches** — Complémentaire à PROGRESSION.md
> - `[ ]` = à faire
> - `[x]` = terminé
> - 1 tâche = 1 ligne
> - Mise à jour obligatoire à chaque fin de session

---

## 🔴 PRIORITÉ HAUTE

- [x] **Task 01** — Déplacer bouton "Voir le rendu réel" sous l'image produit ✅
- [x] **Task 02** — Refonte sélecteur techniques (dropdown scalable comme polices) ✅
- [x] **Task 03** — Audit UX complet configurateur (voir rapport ci-dessous)
- [x] **Task 04** — Vérification parcours client complet (voir rapport ci-dessous) ✅
- [ ] **Task 05** — Vérification drag & drop / lightbox en conditions réelles

---

## 🟡 PRIORITÉ MOYENNE

- [ ] Packs thématiques (P4)
- [ ] Optimisation performance JS
- [ ] Optimisation UX mobile avancée
- [ ] Ajouter images de référence pour flex, flock, sublimation

---

## 🟢 PRIORITÉ BASSE

- [ ] Connexion client
- [ ] Paiement en ligne
- [ ] Historique commandes client

---

## 📦 BACKLOG (non planifié)

- [ ] Export image personnalisée pour email/PDF
- [ ] Pinch-zoom mobile amélioré
- [ ] Mode sombre admin
- [ ] Statistiques avancées dashboard

---

## 📋 AUDIT UX CONFIGURATEUR — 2026-01-17

### Structure actuelle

```
DESKTOP (>1024px) - Layout 3 colonnes:
┌────────────────────────────────────────────────────────────────────┐
│ GAUCHE (300px)    │   CENTRE (flex)       │   DROITE (320px)      │
│ sticky            │                       │   sticky              │
├───────────────────┼───────────────────────┼───────────────────────┤
│ • Texte perso     │   • Toggle Face/Dos   │   • Nom produit       │
│ • Police (dropdown)│   • Preview 450px    │   • Prix              │
│ • Couleur texte   │   • Zone impression   │   • Taille            │
│ • Technique       │   • Zoom btn          │   • Couleur produit   │
│   └─ Rendu réel   │   • Drag hint         │   • Quantité          │
│      (bouton)     │                       │   • Ajouter panier    │
└────────────────────────────────────────────────────────────────────┘

MOBILE (<768px) - Stack + Accordions:
┌─────────────────────────────┐
│ PREVIEW (350px)             │
│ Toggle Face/Dos + Zoom      │
├─────────────────────────────┤
│ [Accordion] Votre texte     │
│ [Accordion] Police          │
│ [Accordion] Couleur texte   │
│ [Accordion] Technique       │
│ [Accordion] Taille          │
│ [Accordion] Couleur         │
│ [Accordion] Quantité        │
├─────────────────────────────┤
│ STICKY CTA: Prix + Ajouter  │
└─────────────────────────────┘
```

### ✅ Points positifs

| Élément | Status | Détail |
|---------|--------|--------|
| Layout 3 colonnes | ✅ OK | Grid responsive, colonnes sticky |
| Preview produit | ✅ OK | 450px desktop, 350px mobile |
| Zone d'impression | ✅ OK | Visible en overlay, positionnement % |
| Sélecteur polices | ✅ OK | Dropdown scalable, recherche, preview |
| Couleurs texte | ✅ OK | Pastilles avec preview temps réel |
| Toggle Face/Dos | ✅ OK | Visible si image dos existe |
| Sticky CTA mobile | ✅ OK | Prix + bouton fixe en bas |
| Accordéons mobile | ✅ OK | Collapsible, animation fluide |
| Drag & drop | ✅ OK | Touch + Mouse, contraint zone |

### ❌ Problèmes identifiés

#### 1. Bouton "Voir le rendu réel" — MAUVAISE POSITION
- **Actuel**: Dans section technique (ligne 1288), caché dans accordion mobile
- **Attendu**: Sous l'image produit, visible en permanence
- **Impact**: Client ne voit pas l'option, pas rassuré
- **Fichier**: `public/product.php` lignes 1287-1295

#### 2. Sélecteur techniques — UX OBSOLÈTE
- **Actuel**: Liste de radio buttons en colonne (`technique-options`)
- **Attendu**: Dropdown scalable comme sélecteur polices
- **Impact**: Non scalable (20+ techniques = scroll énorme)
- **Fichier**: `public/product.php` lignes 1269-1286, styles 830-872

#### 3. Comportement bouton "Rendu réel" si aucune technique
- **Actuel**: Ouvre modal vide ou technique par défaut
- **Attendu**: Message "Sélectionnez une technique pour voir le rendu réel"
- **Fichier**: `public/assets/js/real-render-modal.js`

### 🧪 À tester en conditions réelles

| Test | Méthode | Résultat attendu |
|------|---------|------------------|
| Drag & drop mobile | Touch sur preview | Texte suit le doigt |
| Lightbox position | Clic zoom | Position texte = configurateur |
| Lightbox drag | Drag dans lightbox | Position synchro avec config |
| Parcours panier | Ajouter → Panier | Personnalisation visible |
| Responsive 600px | Viewport mobile | Preview lisible, CTA visible |

### 📐 Dimensions clés

| Breakpoint | Layout | Preview | Notes |
|------------|--------|---------|-------|
| >1200px | 3 cols (300-flex-320) | 450px | Full desktop |
| 1024-1200px | 3 cols (280-flex-280) | 450px | Tablet landscape |
| 768-1024px | Stack (1 col) | 450px | Tablet portrait |
| <768px | Stack + accordions | 350px | Mobile + sticky CTA |
| <600px | Stack + accordions | 300px | Petit mobile |

---

## ✅ TERMINÉ (Session 13)

- [x] Fix Lightbox v2 (position + drag + zone impression + sync)
- [x] Modal "Voir le rendu réel" par technique
- [x] Nettoyage CSS techniques (suppression effets fake)
- [x] Retrait SVG Filters de toutes les pages
- [x] Mise à jour PROGRESSION.md

---

## 📋 VÉRIFICATION PARCOURS CLIENT — Task 04 — 2026-01-17

### Flux vérifié

```
PRODUIT (product.php) → PANIER (cart.php) → CHECKOUT (checkout.php) → SUCCÈS
```

### ✅ Étapes vérifiées

| Étape | Page | Status | Détail |
|-------|------|--------|--------|
| 1. Configuration | product.php | ✅ OK | Texte, police, couleur, technique, position |
| 2. Ajout panier | product.php | ✅ OK | CSRF, quantité, customization complète |
| 3. Vue panier | cart.php | ✅ OK | Liste articles, quantité +/-, supprimer |
| 4. Modification | cart.php | ✅ OK | Update qty, clear cart fonctionnels |
| 5. Formulaire | checkout.php | ✅ OK | Validation email, champs obligatoires |
| 6. Création commande | checkout.php | ✅ OK | Transaction DB, rollback si erreur |
| 7. Email | checkout.php | ✅ OK | Confirmation client + notification admin |
| 8. Succès | checkout.php | ✅ OK | Numéro commande affiché |

### 🔒 Sécurité vérifiée

| Aspect | Status | Implémentation |
|--------|--------|----------------|
| CSRF | ✅ OK | Token sur tous les formulaires |
| Validation email | ✅ OK | `filter_var()` côté serveur |
| Escape HTML | ✅ OK | Fonction `h()` partout |
| Transaction DB | ✅ OK | `beginTransaction()` + `commit/rollback` |
| Panier vide | ✅ OK | Redirect vers accueil |

### 📦 Données transmises au panier

```php
$customization = [
    'size' => 'M',
    'color' => 'blanc',
    'text' => 'Mon texte',
    'text_color' => 'noir',
    'font' => 'Poppins',
    'technique' => 'flex',
    'view' => 'front',
    'position' => ['x' => 50.0, 'y' => 50.0, 'zone_id' => 1]
];
```

### ⚠️ Points d'attention (non bloquants)

| Point | Fichier | Détail |
|-------|---------|--------|
| Position affichée "centre" | cart.php:448 | Hardcodé, devrait afficher X/Y ou "Personnalisée" |
| Image checkout | checkout.php:554 | Emoji 👕 au lieu de vraie image produit |

### 🎯 Conclusion

**Parcours client 100% fonctionnel.** Aucun bug bloquant détecté.
Les points d'attention sont cosmétiques et n'impactent pas la conversion.

---

**Dernière mise à jour** : 2026-01-17 — Task 04 terminée (parcours client vérifié)
