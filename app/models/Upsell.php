<?php
/**
 * PERSONNALY - Model Upsell
 * Gestion des upsells pour augmenter le panier moyen
 */

require_once __DIR__ . '/../core/Database.php';

class Upsell
{
    private PDO $db;

    // Types de conditions disponibles
    public const CONDITION_TYPES = [
        'panier_min' => 'Panier minimum (€)',
        'produit_specifique' => 'Produit spécifique',
        'technique_specifique' => 'Technique de personnalisation',
        'categorie' => 'Catégorie de produit',
        'quantite_min' => 'Quantité minimum'
    ];

    // Types d'offres disponibles
    public const OFFER_TYPES = [
        'produit' => 'Proposer un produit',
        'option' => 'Proposer une option',
        'reduction' => 'Offrir une réduction',
        'livraison_gratuite' => 'Livraison gratuite'
    ];

    // Types de réduction
    public const DISCOUNT_TYPES = [
        'aucun' => 'Aucune réduction',
        'pourcentage' => 'Pourcentage (%)',
        'montant_fixe' => 'Montant fixe (€)'
    ];

    // Positions d'affichage
    public const DISPLAY_POSITIONS = [
        'cart' => 'Panier uniquement',
        'checkout' => 'Checkout uniquement',
        'both' => 'Panier et checkout'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les upsells
     */
    public function findAll(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM upsells';
            if ($activeOnly) {
                $sql .= ' WHERE active = 1';
            }
            $sql .= ' ORDER BY priority DESC, created_at DESC';

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère un upsell par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM upsells WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée un nouvel upsell
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO upsells (
                name, description, condition_type, condition_value,
                offer_type, offer_value, offer_label,
                discount_type, discount_value,
                display_title, display_image, display_position,
                priority, max_uses, start_date, end_date, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['condition_type'],
            $data['condition_value'],
            $data['offer_type'],
            $data['offer_value'] ?? '',
            $data['offer_label'] ?? null,
            $data['discount_type'] ?? 'aucun',
            $data['discount_value'] ?? 0,
            $data['display_title'] ?? null,
            $data['display_image'] ?? null,
            $data['display_position'] ?? 'both',
            $data['priority'] ?? 0,
            $data['max_uses'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['active'] ?? 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un upsell
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE upsells SET
                name = ?, description = ?, condition_type = ?, condition_value = ?,
                offer_type = ?, offer_value = ?, offer_label = ?,
                discount_type = ?, discount_value = ?,
                display_title = ?, display_image = ?, display_position = ?,
                priority = ?, max_uses = ?, start_date = ?, end_date = ?, active = ?
            WHERE id = ?'
        );

        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['condition_type'],
            $data['condition_value'],
            $data['offer_type'],
            $data['offer_value'] ?? '',
            $data['offer_label'] ?? null,
            $data['discount_type'] ?? 'aucun',
            $data['discount_value'] ?? 0,
            $data['display_title'] ?? null,
            $data['display_image'] ?? null,
            $data['display_position'] ?? 'both',
            $data['priority'] ?? 0,
            $data['max_uses'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['active'] ?? 1,
            $id
        ]);
    }

    /**
     * Supprime un upsell
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM upsells WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive un upsell
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE upsells SET active = NOT active WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Incrémente le compteur d'utilisations
     */
    public function incrementUses(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE upsells SET current_uses = current_uses + 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les upsells applicables selon le contexte du panier
     *
     * @param array $context [
     *   'cart_total' => float,
     *   'products' => array of product_ids,
     *   'techniques' => array of technique values,
     *   'categories' => array of category_ids,
     *   'quantity' => int total items
     * ]
     * @param string $position 'cart' ou 'checkout'
     */
    public function findApplicable(array $context, string $position = 'both'): array
    {
        try {
            // Récupérer tous les upsells actifs et valides
            $sql = "SELECT * FROM upsells
                    WHERE active = 1
                    AND (display_position = ? OR display_position = 'both')
                    AND (start_date IS NULL OR start_date <= CURDATE())
                    AND (end_date IS NULL OR end_date >= CURDATE())
                    AND (max_uses IS NULL OR current_uses < max_uses)
                    ORDER BY priority DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$position]);
            $upsells = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $applicable = [];

            foreach ($upsells as $upsell) {
                if ($this->checkCondition($upsell, $context)) {
                    $applicable[] = $upsell;
                }
            }

            return $applicable;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Vérifie si un upsell s'applique au contexte
     */
    private function checkCondition(array $upsell, array $context): bool
    {
        $type = $upsell['condition_type'];
        $value = $upsell['condition_value'];

        switch ($type) {
            case 'panier_min':
                return ($context['cart_total'] ?? 0) >= (float) $value;

            case 'produit_specifique':
                $productIds = $context['products'] ?? [];
                return in_array((int) $value, array_map('intval', $productIds));

            case 'technique_specifique':
                $techniques = $context['techniques'] ?? [];
                // Recherche flexible (case insensitive, partielle)
                $valueLower = strtolower($value);
                foreach ($techniques as $tech) {
                    $techLower = strtolower($tech);
                    if ($techLower === $valueLower || strpos($techLower, $valueLower) !== false) {
                        return true;
                    }
                }
                return false;

            case 'categorie':
                $categoryIds = $context['categories'] ?? [];
                return in_array((int) $value, array_map('intval', $categoryIds));

            case 'quantite_min':
                return ($context['quantity'] ?? 0) >= (int) $value;

            default:
                return false;
        }
    }

    /**
     * Enregistre l'utilisation d'un upsell sur une commande
     */
    public function recordUsage(int $orderId, int $upsellId, float $discountApplied = 0): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO order_upsells (order_id, upsell_id, discount_applied)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE discount_applied = ?'
            );
            $result = $stmt->execute([$orderId, $upsellId, $discountApplied, $discountApplied]);

            if ($result) {
                $this->incrementUses($upsellId);
            }

            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Récupère les upsells utilisés pour une commande
     */
    public function getOrderUpsells(int $orderId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT ou.*, u.name, u.offer_type, u.offer_label
                 FROM order_upsells ou
                 JOIN upsells u ON ou.upsell_id = u.id
                 WHERE ou.order_id = ?'
            );
            $stmt->execute([$orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Calcule la réduction d'un upsell
     */
    public function calculateDiscount(array $upsell, float $baseAmount): float
    {
        if ($upsell['discount_type'] === 'aucun') {
            return 0;
        }

        $discountValue = (float) $upsell['discount_value'];

        if ($upsell['discount_type'] === 'pourcentage') {
            return round($baseAmount * ($discountValue / 100), 2);
        }

        if ($upsell['discount_type'] === 'montant_fixe') {
            return min($discountValue, $baseAmount); // Ne pas dépasser le montant de base
        }

        return 0;
    }

    /**
     * Récupère les statistiques d'un upsell
     */
    public function getStats(int $id): array
    {
        $upsell = $this->findById($id);
        if (!$upsell) {
            return [];
        }

        // Total réductions appliquées
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as uses, SUM(discount_applied) as total_discount
             FROM order_upsells WHERE upsell_id = ?'
        );
        $stmt->execute([$id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'upsell' => $upsell,
            'total_uses' => (int) ($stats['uses'] ?? 0),
            'total_discount' => (float) ($stats['total_discount'] ?? 0)
        ];
    }

    /**
     * Duplique un upsell
     */
    public function duplicate(int $id): ?int
    {
        $original = $this->findById($id);
        if (!$original) {
            return null;
        }

        unset($original['id'], $original['created_at'], $original['updated_at']);
        $original['name'] = $original['name'] . ' (copie)';
        $original['current_uses'] = 0;
        $original['active'] = 0; // Désactivé par défaut

        return $this->create($original);
    }
}
