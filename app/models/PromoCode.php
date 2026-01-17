<?php
/**
 * PERSONNALY - Model PromoCode
 * Gestion des codes promo avec saisie client
 */

require_once __DIR__ . '/../core/Database.php';

class PromoCode
{
    private PDO $db;

    // Types de réduction
    public const DISCOUNT_TYPES = [
        'percentage' => 'Pourcentage (%)',
        'fixed_amount' => 'Montant fixe (€)',
        'free_shipping' => 'Livraison gratuite'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère tous les codes promo
     */
    public function findAll(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM promo_codes';
            if ($activeOnly) {
                $sql .= ' WHERE active = 1';
            }
            $sql .= ' ORDER BY created_at DESC';

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère un code promo par ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promo_codes WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Récupère un code promo par son code
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promo_codes WHERE UPPER(code) = UPPER(?)');
        $stmt->execute([trim($code)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée un nouveau code promo
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO promo_codes (
                code, name, description, discount_type, discount_value,
                min_order_amount, max_discount, max_uses, max_uses_per_customer,
                start_date, end_date, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            strtoupper(trim($data['code'])),
            $data['name'],
            $data['description'] ?? null,
            $data['discount_type'],
            $data['discount_value'] ?? 0,
            $data['min_order_amount'] ?: null,
            $data['max_discount'] ?: null,
            $data['max_uses'] ?: null,
            $data['max_uses_per_customer'] ?? 1,
            $data['start_date'] ?: null,
            $data['end_date'] ?: null,
            $data['active'] ?? 1
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un code promo
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_codes SET
                code = ?, name = ?, description = ?, discount_type = ?, discount_value = ?,
                min_order_amount = ?, max_discount = ?, max_uses = ?, max_uses_per_customer = ?,
                start_date = ?, end_date = ?, active = ?
            WHERE id = ?'
        );

        return $stmt->execute([
            strtoupper(trim($data['code'])),
            $data['name'],
            $data['description'] ?? null,
            $data['discount_type'],
            $data['discount_value'] ?? 0,
            $data['min_order_amount'] ?: null,
            $data['max_discount'] ?: null,
            $data['max_uses'] ?: null,
            $data['max_uses_per_customer'] ?? 1,
            $data['start_date'] ?: null,
            $data['end_date'] ?: null,
            $data['active'] ?? 1,
            $id
        ]);
    }

    /**
     * Supprime un code promo
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM promo_codes WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Active/désactive un code promo
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_codes SET active = NOT active WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Valide un code promo et retourne les détails
     *
     * @param string $code Le code saisi par le client
     * @param float $cartTotal Montant du panier
     * @return array ['valid' => bool, 'error' => string|null, 'promo' => array|null, 'discount' => float]
     */
    public function validateCode(string $code, float $cartTotal): array
    {
        $code = strtoupper(trim($code));

        if (empty($code)) {
            return ['valid' => false, 'error' => 'Veuillez entrer un code promo', 'promo' => null, 'discount' => 0];
        }

        $promo = $this->findByCode($code);

        if (!$promo) {
            return ['valid' => false, 'error' => 'Code promo invalide', 'promo' => null, 'discount' => 0];
        }

        // Vérifier si actif
        if (!$promo['active']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est plus actif', 'promo' => null, 'discount' => 0];
        }

        // Vérifier les dates
        $now = date('Y-m-d');
        if (!empty($promo['start_date']) && $promo['start_date'] > $now) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est pas encore valide', 'promo' => null, 'discount' => 0];
        }
        if (!empty($promo['end_date']) && $promo['end_date'] < $now) {
            return ['valid' => false, 'error' => 'Ce code promo a expiré', 'promo' => null, 'discount' => 0];
        }

        // Vérifier limite d'utilisation
        if (!empty($promo['max_uses']) && $promo['current_uses'] >= $promo['max_uses']) {
            return ['valid' => false, 'error' => 'Ce code promo a atteint sa limite d\'utilisation', 'promo' => null, 'discount' => 0];
        }

        // Vérifier montant minimum
        if (!empty($promo['min_order_amount']) && $cartTotal < $promo['min_order_amount']) {
            return [
                'valid' => false,
                'error' => 'Minimum de commande : ' . number_format($promo['min_order_amount'], 2, ',', ' ') . '€',
                'promo' => null,
                'discount' => 0
            ];
        }

        // Calculer la réduction
        $discount = $this->calculateDiscount($promo, $cartTotal);

        return [
            'valid' => true,
            'error' => null,
            'promo' => $promo,
            'discount' => $discount
        ];
    }

    /**
     * Calcule le montant de la réduction
     */
    public function calculateDiscount(array $promo, float $cartTotal): float
    {
        $discount = 0;

        switch ($promo['discount_type']) {
            case 'percentage':
                $discount = round($cartTotal * ($promo['discount_value'] / 100), 2);
                // Appliquer plafond si défini
                if (!empty($promo['max_discount']) && $discount > $promo['max_discount']) {
                    $discount = $promo['max_discount'];
                }
                break;

            case 'fixed_amount':
                $discount = min($promo['discount_value'], $cartTotal);
                break;

            case 'free_shipping':
                // La livraison gratuite est gérée différemment
                // On retourne 0 mais le flag free_shipping sera utilisé
                $discount = 0;
                break;
        }

        return $discount;
    }

    /**
     * Incrémente le compteur d'utilisations
     */
    public function incrementUses(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE promo_codes SET current_uses = current_uses + 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Enregistre l'utilisation d'un code pour une commande
     */
    public function recordUsage(int $orderId, int $promoCodeId, string $codeUsed, float $discountApplied): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO order_promo_codes (order_id, promo_code_id, code_used, discount_applied)
                 VALUES (?, ?, ?, ?)'
            );
            $result = $stmt->execute([$orderId, $promoCodeId, $codeUsed, $discountApplied]);

            if ($result) {
                $this->incrementUses($promoCodeId);
            }

            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Duplique un code promo
     */
    public function duplicate(int $id): ?int
    {
        $original = $this->findById($id);
        if (!$original) {
            return null;
        }

        unset($original['id'], $original['created_at'], $original['updated_at']);
        $original['code'] = $original['code'] . '_COPY';
        $original['name'] = $original['name'] . ' (copie)';
        $original['current_uses'] = 0;
        $original['active'] = 0;

        return $this->create($original);
    }

    /**
     * Retourne le libellé de la réduction
     */
    public function getDiscountLabel(array $promo): string
    {
        switch ($promo['discount_type']) {
            case 'percentage':
                return '-' . (int) $promo['discount_value'] . '%';
            case 'fixed_amount':
                return '-' . number_format($promo['discount_value'], 2, ',', ' ') . '€';
            case 'free_shipping':
                return 'Livraison gratuite';
            default:
                return '';
        }
    }
}
