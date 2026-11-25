<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class StockExpiryProvenance
{
    public static function capture(BaseConnection $db, array $inventory): array
    {
        $delivery = $inventory['stock_delivery_id'] ?? null;
        if (!is_string($delivery) || !preg_match('/^[a-f0-9]{32}$/D', $delivery)) {
            $delivery = bin2hex(random_bytes(16));
            if (!$db->table('branch_products')->where('id', $inventory['id'])->update(['stock_delivery_id' => $delivery])) {
                throw new \RuntimeException('Could not record the stock delivery.');
            }
        }
        return ['stock_delivery_id' => $delivery, 'expiration_date_at_sale' => $inventory['expiration_date'] ?: null];
    }

    public static function assertSameDelivery(array $item, array $inventory): void
    {
        $original = $item['stock_delivery_id'] ?? null;
        $current = $inventory['stock_delivery_id'] ?? null;
        if (!is_string($original) || !preg_match('/^[a-f0-9]{32}$/D', $original)
            || !is_string($current) || !hash_equals($original, $current)
            || ($item['expiration_date_at_sale'] ?? null) !== (($inventory['expiration_date'] ?? null) ?: null)) {
            throw new InvalidArgumentException('The original stock delivery is unknown or differs from current stock. Use Quarantine, Damaged or Expired for a return; use a refund and new sale instead of changing this invoice quantity.');
        }
    }

    public static function restockQuantity(string $condition, int $quantity, array $item, array $inventory, string $today): int
    {
        $expiry = $item['expiration_date_at_sale'] ?? null;
        $restock = ReturnCondition::restockQuantity($condition, $quantity, $expiry, $today);
        if ($restock === 0) return 0;
        $parsed = is_string($expiry) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $expiry) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $expiry) {
            throw new InvalidArgumentException('The expiry at sale was not recorded. Choose Quarantine, Damaged or Expired; this return cannot enter available stock.');
        }
        self::assertSameDelivery($item, $inventory);
        return $restock;
    }

    public static function restockProblem(array $item, array $inventory, string $today): ?string
    {
        try {
            self::restockQuantity('resellable', 1, $item, $inventory, $today);
            return null;
        } catch (InvalidArgumentException $error) {
            return $error->getMessage();
        }
    }
}
