<?php

namespace App\Libraries;

final class MedicineType
{
    public const OPTIONS = ['' => 'Not specified', 'branded' => 'Branded', 'generic' => 'Generic', 'non_medicine' => 'Non-medicine'];
    public const FILTERS = ['' => 'All types', 'branded' => 'Branded', 'generic' => 'Generic', 'non_medicine' => 'Non-medicine', 'unspecified' => 'Not specified'];

    public static function label(?string $value): string
    {
        return self::OPTIONS[$value ?? ''] ?? self::OPTIONS[''];
    }

    public static function filter(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::FILTERS) ? $value : '';
    }

    public static function applyFilter(object $builder, string $filter): object
    {
        if (in_array($filter, ['branded', 'generic', 'non_medicine'], true)) {
            $builder->where('products.medicine_type', $filter);
        } elseif ($filter === 'unspecified') {
            $builder->groupStart()->where('products.medicine_type', null)
                ->orWhere('products.medicine_type', '')->groupEnd();
        }
        return $builder;
    }
}
