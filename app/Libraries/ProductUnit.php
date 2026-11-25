<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

final class ProductUnit
{
    public const OPTIONS = [
        'tablet' => 'Tablet', 'capsule' => 'Capsule', 'bottle' => 'Bottle', 'box' => 'Box',
        'sachet' => 'Sachet', 'tube' => 'Tube', 'ampoule' => 'Ampoule', 'vial' => 'Vial',
        'nebule' => 'Nebule', 'inhaler' => 'Inhaler', 'suppository' => 'Suppository', 'patch' => 'Patch',
        'syringe' => 'Syringe', 'strip' => 'Strip', 'blister' => 'Blister', 'pack' => 'Pack',
        'piece' => 'Piece', 'pair' => 'Pair', 'roll' => 'Roll', 'kit' => 'Kit', 'jar' => 'Jar', 'bag' => 'Bag',
    ];

    /** Keep existing catalog units selectable without rewriting their stored spelling. */
    public static function options(BaseConnection $db): array
    {
        $options = self::OPTIONS;
        $rules = new \App\Validation\ManagementRules();
        // Deduplicate in PHP so case-insensitive SQL collations do not hide an existing spelling.
        $rows = $db->table('products')->select('unit')->where('unit IS NOT NULL', null, false)->orderBy('unit')->get()->getResultArray();
        foreach ($rows as $row) {
            $unit = trim((string) $row['unit']);
            if ($unit !== '' && mb_strlen($unit) <= 30 && $rules->management_text($unit, 'catalog', [])) {
                $options[$unit] ??= $unit === 'pcs' ? 'Piece (pcs)' : ucfirst($unit);
            }
        }
        return $options;
    }

    public static function accepts(string $unit, BaseConnection $db): bool
    {
        return isset(self::OPTIONS[$unit]) || array_key_exists($unit, self::options($db));
    }
}
