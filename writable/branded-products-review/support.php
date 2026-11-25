<?php
function brandedManifest(): array {
    $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (hash_file('sha256',ROOTPATH.$manifest['upgrade'])!==$manifest['upgrade_sha256']) throw new RuntimeException('Branded seed changed after preparation.');
    return $manifest;
}
function brandedNewRows(array $before,array $after,string $table): array {
    $ids=array_column($before[$table],null,'id');
    return array_values(array_filter($after[$table],static fn($row)=>!isset($ids[$row['id']])));
}
function brandedChecks(object $db,array $before,array $after,array $manifest,bool $requireAll=true): array {
    $assert=static function(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); };
    $added=brandedNewRows($before,$after,'products');$inventories=brandedNewRows($before,$after,'branch_products');$logs=brandedNewRows($before,$after,'stock_logs');
    if ($requireAll) $assert(count($added)===count($manifest['items']),'Expected twelve new branded products.');
    $assert(count($inventories)===count($added) && count($logs)===count($added),'Every new product needs exactly one inventory and demo stock log: '.json_encode(['products'=>count($added),'inventory'=>count($inventories),'stock_logs'=>count($logs)]));
    $definitions=array_column($manifest['items'],null,'sku');
    $branch=$db->query("SELECT id FROM branches WHERE status='active' ORDER BY CASE WHEN branch_name='Main Branch' THEN 0 ELSE 1 END,id LIMIT 1")->getRowArray();
    $expiry=$db->query('SELECT DATE_ADD(CURDATE(), INTERVAL 1 YEAR) AS value')->getRowArray()['value'];
    $config=new \Config\ReceiptInvoice();$config->taxStatus='vat';$config->vatRate=12.0;
    foreach ($added as $product) {
        $item=$definitions[$product['sku']] ?? null;$assert($item!==null,'Unreviewed branded SKU inserted.');
        foreach (['product_name'=>'name','generic_name'=>'generic','strength'=>'strength','unit'=>'unit'] as $field=>$key) $assert($product[$field]===$item[$key],'Incorrect branded field: '.$field);
        $assert($product['medicine_type']==='branded' && $product['tax_treatment']==='taxable' && (int)$product['senior_pwd_eligible']===1,'Incorrect branded tax/benefit classification.');
        $assert($product['status']==='active' && $product['deleted_at']===null && (int)$product['is_deleted']===0 && (int)$product['is_permanently_deleted']===0,'New product is not active.');
        $assert((int)$product['stock']===$manifest['quantity'] && (float)$product['price']===(float)$item['price'] && (float)$product['cost_price']===(float)$item['cost_price'],'Incorrect demo master stock or price.');
        $category=$db->table('categories')->where('id',$product['category_id'])->get()->getRowArray();
        $assert($category['category_name']===$item['category'],'Incorrect category.');
        $inventory=array_values(array_filter($inventories,static fn($row)=>$row['product_id']===$product['id']));
        $movement=array_values(array_filter($logs,static fn($row)=>$row['product_id']===$product['id']));
        $assert(count($inventory)===1 && count($movement)===1,'Duplicate inventory or stock log.');$inventory=$inventory[0];$movement=$movement[0];
        $assert($inventory['branch_id']===$branch['id'] && (int)$inventory['stock']===$manifest['quantity'] && $inventory['expiration_date']===$expiry,'Invalid branch, demo quantity or expiry.');
        $assert((float)$inventory['price']===(float)$item['price'] && (float)$inventory['cost_price']===(float)$item['cost_price'],'Branch price differs from master.');
        $assert((bool)preg_match('/^[a-f0-9]{32}$/D',$inventory['stock_delivery_id']),'New stock has no valid delivery identity.');
        $assert($inventory['status']==='active' && $inventory['deleted_at']===null && (int)$inventory['is_deleted']===0 && (int)$inventory['is_permanently_deleted']===0,'New inventory is not available to POS.');
        $assert($movement['branch_id']===$branch['id'] && $movement['user_id']===null && $movement['action_type']==='stock_in'
            && (int)$movement['quantity']===$manifest['quantity'] && (int)$movement['previous_stock']===0 && (int)$movement['new_stock']===$manifest['quantity']
            && str_starts_with($movement['remarks'],'Demo branded product seed:'),'Invalid demo stock audit record.');
        $id=(int)$product['id'];
        foreach (['senior_citizen','pwd'] as $kind) {
            $quote=\App\Libraries\StatutoryDiscount::quote($kind,[$id=>$product+['subtotal'=>112.0]],
                ['beneficiary_name'=>'Seed verification','beneficiary_id'=>'VERIFY-ONLY','benefit_verified'=>'1','benefit_products'=>[$id=>'1']],$config);
            $assert($quote['discount']===20.0 && $quote['vat_exemption']===12.0,'New branded product fails statutory quote.');
        }
    }
    return ['products_added'=>count($added),'branch_rows_added'=>count($inventories),'demo_stock_logs_added'=>count($logs),
        'demo_units_added'=>count($added)*$manifest['quantity'],'branch_id'=>$branch['id'] ?? null,'sample_expiry'=>$expiry,
        'new_product_formulations_prices_and_stock_verified'=>true,'senior_and_pwd_quotes_verified'=>true];
}
