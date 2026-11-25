<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleItemModel extends Model
{
    protected $table = 'sale_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'sale_id',
        'product_id',
        'stock_delivery_id',
        'expiration_date_at_sale',
        'product_name_snapshot',
        'generic_name_snapshot',
        'strength_snapshot',
        'tax_treatment_snapshot',
        'cost_price_at_sale',
        'quantity',
        'price',
        'subtotal',
        'discount_applied',
        'vat_exemption_amount',
        'statutory_discount_kind',
        'profit'
    ];
}
