<?php
function catalogManifest(): array {
    $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (hash_file('sha256',ROOTPATH.$manifest['upgrade'])!==$manifest['upgrade_sha256']) throw new RuntimeException('Product SQL changed after preparation.');
    return $manifest;
}
function catalogExpected(array $rows,array $manifest): array {
    $plans=array_column($manifest['plan'],null,'id');
    foreach ($rows as &$row) {
        $plan=$plans[(int)$row['id']] ?? null;
        if (!$plan || $row['sku']!==$plan['sku']) continue;
        $values=[];
        foreach ($manifest['fields'] as $field) $values[$field]=$row[$field]===null?null:($field==='senior_pwd_eligible'?(int)$row[$field]:$row[$field]);
        if (!in_array($values,$plan['before'],true)) continue;
        foreach ($manifest['fields'] as $field) $row[$field]=$plan['after'][$field]===null?null:(string)$plan['after'][$field];
    }
    unset($row); return $rows;
}
function catalogBenefitChecks(object $db): array {
    $settings=new \Config\ReceiptInvoice(); $settings->taxStatus='vat'; $settings->vatRate=12.0;
    $input=['beneficiary_name'=>'Catalog verification','beneficiary_id'=>'VERIFY-ONLY','benefit_verified'=>'1','benefit_products'=>['6'=>'1','13'=>'1']];
    $items=[];
    foreach ([6,13,46] as $id) $items[$id]=$db->table('products')->where('id',$id)->get()->getRowArray()+['subtotal'=>112.0];
    foreach (['senior_citizen','pwd'] as $kind) {
        $quote=\App\Libraries\StatutoryDiscount::quote($kind,$items,$input,$settings);
        if ($quote['discount']!==40.0 || $quote['vat_exemption']!==24.0 || array_keys($quote['items'])!==[6,13]) throw new RuntimeException('Branded/generic discount calculation failed.');
        $blocked=false;
        try { \App\Libraries\StatutoryDiscount::quote($kind,$items,array_replace($input,['benefit_products'=>['46'=>'1']]),$settings); }
        catch (\InvalidArgumentException $error) { $blocked=str_contains($error->getMessage(),'not been confirmed'); }
        if (!$blocked) throw new RuntimeException('Non-eligible personal care item received a medicine benefit.');
    }
    return ['Both Senior Citizen and PWD discounts accept the newly classified branded and generic medicines.',
        'Two selected PHP112 VAT-inclusive lines produce PHP40 discount and PHP24 VAT relief; an unselected line is untouched.',
        'An ineligible personal care item is rejected for either statutory discount.'];
}
