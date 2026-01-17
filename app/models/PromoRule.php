<?php
/**
 * PERSONNALY - Model PromoRule
 * Gestion des règles promotionnelles conditionnelles
 * (SI condition ALORS offre/réduction)
 */

require_once __DIR__ . '/../core/Database.php';

class PromoRule
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
     * Récupère toutes les règles promo
     */
    public function findAll(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM promo_rules';
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
     * Récupère une règle promo par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promo_rules WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée une nouvelle règle promo
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO promo_rules (
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
     * Met à jour une règle promo
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_rules SET
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
     * Supprime une règle promo
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM promo_rules WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive une règle promo
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_rules SET active = NOT active WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Incrémente le compteur d'utilisations
     */
    public function incrementUses(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_rules SET current_uses = current_uses + 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Récupère les règles promo applicables selon le contexte du panier
     */
    public function findApplicable(array $context, string $position = 'both'): array
    {
        try {
            $sql = "SELECT * FROM promo_rules
                    WHERE active = 1
                    AND (display_position = ? OR display_position = 'both')
                    AND (start_date IS NULL OR start_date <= CURDATE())
                    AND (end_date IS NULL OR end_date >= CURDATE())
                    AND (max_uses IS NULL OR current_uses < max_uses)
                    ORDER BY priority DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$position]);
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $applicable = [];
            foreach ($rules as $rule) {
                if ($this->checkCondition($rule, $context)) {
                    $applicable[] = $rule;
                }
            }

            return $applicable;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Vérifie si une règle s'applique au contexte
     */
    private function checkCondition(array $rule, array $context): bool
    {
        $type = $rule['condition_type'];
        $value = $rule['condition_value'];

        switch ($type) {
            case 'panier_min':
                return ($context['cart_total'] ?? 0) >= (float) $value;

            case 'produit_specifique':
                $productIds = $context['products'] ?? [];
                return in_array((int) $value, array_map('intval', $productIds));

            case 'technique_specifique':
                $techniques = $context['techniques'] ?? [];
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
     * Calcule la réduction d'une règle promo
     */
    public function calculateDiscount(array $rule, float $baseAmount): float
    {
        if ($rule['discount_type'] === 'aucun') {
            return 0;
        }

        $discountValue = (float) $rule['discount_value'];

        if ($rule['discount_type'] === 'pourcentage') {
            return round($baseAmount * ($discountValue / 100), 2);
        }

        if ($rule['discount_type'] === 'montant_fixe') {
            return min($discountValue, $baseAmount);
        }

        return 0;
    }

    /**
     * Duplique une règle promo
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
        $original['active'] = 0;

        return $this->create($original);
    }
}
