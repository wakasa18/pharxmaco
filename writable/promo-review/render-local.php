<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
$settings = config(\Config\Database::class)->default;
if ($settings['database'] !== 'pharxmaco_drugstore' || !in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Known local database required.');
$db = \Config\Database::connect('default');
$db->query('START TRANSACTION READ ONLY');
try {
    $today = date('Y-m-d');
    $promos = $db->table('discounts')->where('status', 'active')->where('deleted_at', null)->where('is_deleted', 0)->where('is_permanently_deleted', 0)
        ->groupStart()->where('start_date', null)->orWhere('start_date <=', $today)->groupEnd()
        ->groupStart()->where('end_date', null)->orWhere('end_date >=', $today)->groupEnd()->orderBy('id')->get()->getResultArray();
    $target = null;
    foreach (array_reverse($promos) as $promo) {
        if ($promo['applies_to'] !== 'product') continue;
        $target = $db->query("SELECT p.*,p.id product_id,bp.id branch_product_id,bp.branch_id,bp.stock,bp.price,bp.cost_price,bp.expiration_date FROM products p JOIN branch_products bp ON bp.product_id=p.id WHERE p.id=? AND p.status='active' AND bp.status='active' AND p.deleted_at IS NULL AND bp.deleted_at IS NULL AND bp.stock>=10 AND (bp.expiration_date IS NULL OR bp.expiration_date>=?) ORDER BY bp.branch_id LIMIT 1", [$promo['product_id'], $today])->getRowArray();
        if ($target) break;
    }
    if (!$target) throw new RuntimeException('A stocked product promo is required for this read-only preview.');
    $other = $db->query("SELECT p.*,p.id product_id,bp.id branch_product_id,bp.branch_id,bp.stock,bp.price,bp.cost_price,bp.expiration_date FROM products p JOIN branch_products bp ON bp.product_id=p.id WHERE p.id<>? AND bp.branch_id=? AND p.status='active' AND bp.status='active' AND p.deleted_at IS NULL AND bp.deleted_at IS NULL AND bp.stock>0 AND (bp.expiration_date IS NULL OR bp.expiration_date>=?) ORDER BY p.id LIMIT 1", [$target['product_id'], $target['branch_id'], $today])->getRowArray();
    if (!$other) throw new RuntimeException('An unrelated product is required.');
    foreach ([$target, $other] as $product) {
        $product['quantity'] = 1; $product['subtotal'] = (float)$product['price'];
        $cart[(int)$product['product_id']] = $product;
    }
    $quotes = [];
    foreach ([1, 2, 10] as $qty) {
        $items = $cart; $items[(int)$target['product_id']]['subtotal'] = (float)$target['price'] * $qty;
        $quote = \App\Libraries\ProductPromotion::best($promos, $items, $today);
        $quotes[$qty] = ['id' => (int)($quote['discount']['id'] ?? 0), 'amount' => $quote['amount'] ?? 0,
            'final_total' => array_sum(array_column($items, 'subtotal')) - ($quote['amount'] ?? 0)];
    }
} finally { $db->query('ROLLBACK'); }
session()->set(['role'=>'admin','user_id'=>999999,'branch_id'=>(int)$target['branch_id'],'full_name'=>'Promo preview']);
$dir = WRITEPATH . 'medicine-type-review/';
foreach (['pos-product-promo', 'pos-product-promo-fresh-cart'] as $name) {
    session()->set('checkout_token', bin2hex(random_bytes(16)));
    $html = view('cashier/sales/index', ['products'=>array_values($cart),'cart'=>$cart,'discounts'=>$promos,'heldSales'=>[]], ['saveData'=>false]);
    file_put_contents($dir . $name . '.html', preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
}
$manifest = ['target_id'=>(int)$target['product_id'], 'product_name'=>$target['product_name'], 'price'=>(float)$target['price'],
    'unrelated_id'=>(int)$other['product_id'], 'unrelated_price'=>(float)$other['price'], 'quotes'=>$quotes, 'read_only'=>true];
file_put_contents(__DIR__.'/preview.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
