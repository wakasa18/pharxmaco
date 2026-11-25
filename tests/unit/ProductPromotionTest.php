<?php

use App\Libraries\ProductPromotion;
use PHPUnit\Framework\TestCase;

final class ProductPromotionTest extends TestCase
{
    public function testSharedProductPromotionSelectionCases(): void
    {
        $cases = json_decode(file_get_contents(ROOTPATH . 'tests/fixtures/product-promotion-cases.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            $promos = array_map(static fn($promo) => $promo + ['status' => 'active', 'applies_to' => 'product'], $case['promos']);
            $result = ProductPromotion::best($promos, $case['items'], '2026-10-15');
            $this->assertSame($case['id'], $result['discount']['id'] ?? null, $case['label']);
            $this->assertEquals($case['amount'], $result['amount'] ?? 0, $case['label']);
        }
    }

    public function testUnavailableAndNonProductPromotionsCannotBeAutomatic(): void
    {
        $base = ['id' => 1, 'product_id' => 44, 'discount_type' => 'percentage', 'discount_value' => 20,
            'status' => 'active', 'applies_to' => 'product'];
        foreach ([['status' => 'inactive'], ['deleted_at' => '2026-10-01'], ['is_deleted' => 1], ['is_permanently_deleted' => 1],
            ['start_date' => '2026-10-16'], ['end_date' => '2026-10-14'], ['applies_to' => 'all'], ['applies_to' => 'category'], ['discount_value' => 101]] as $change) {
            $this->assertNull(ProductPromotion::best([array_replace($base, $change)], [['product_id' => 44, 'subtotal' => 55]], '2026-10-15'));
        }
    }

    public function testAllProductsGetTheirBestOfferAndEntirePreviewMustMatch(): void
    {
        $base = ['discount_type'=>'percentage','discount_value'=>20,'status'=>'active','applies_to'=>'product','discount_name'=>'Promo'];
        $promos = [
            ['id'=>5,'product_id'=>2] + $base,
            ['id'=>3,'product_id'=>1] + $base,
            ['id'=>2,'product_id'=>1] + $base,
            ['id'=>6,'product_id'=>3,'minimum_purchase'=>200] + $base,
            ['id'=>7,'product_id'=>4,'max_discount_amount'=>1] + $base,
        ];
        $items = [['product_id'=>1,'subtotal'=>100],['product_id'=>2,'subtotal'=>55],['product_id'=>3,'subtotal'=>100],['product_id'=>4,'subtotal'=>100]];
        $offers = ProductPromotion::all($promos,$items,'2026-10-15');
        $this->assertSame([2,5,7],array_column(array_column($offers,'discount'),'id'));
        $this->assertSame(32.0,ProductPromotion::total($offers));
        $snapshot = [['id'=>2,'product_id'=>1,'amount_cents'=>2000],['id'=>5,'product_id'=>2,'amount_cents'=>1100],['id'=>7,'product_id'=>4,'amount_cents'=>100]];
        $input = ['auto_promo_amount'=>'32.00','auto_promo_snapshot'=>json_encode($snapshot)];
        $this->assertTrue(ProductPromotion::matchesPreview($input,$offers));
        foreach (['[]','invalid',json_encode(array_reverse($snapshot)),json_encode([$snapshot[0],$snapshot[0],$snapshot[2]])] as $bad) {
            $this->assertFalse(ProductPromotion::matchesPreview(array_replace($input,['auto_promo_snapshot'=>$bad]),$offers));
        }
        $this->assertFalse(ProductPromotion::matchesPreview(['auto_promo_id'=>'2','auto_promo_amount'=>'32.00'],$offers));
    }

    public function testSharedAutomaticCategoryCasesAndPerItemPreview(): void
    {
        $cases = json_decode(file_get_contents(ROOTPATH.'tests/fixtures/category-promotion-cases.json'),true,512,JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            $discounts = array_map(static fn($d)=>$d+['status'=>'active','discount_name'=>'Category offer'],$case['promos']);
            $offers = ProductPromotion::all($discounts,$case['items'],'2026-10-16');
            $snapshot = ProductPromotion::snapshot($offers);
            $actual = array_map(static fn($offer)=>array_intersect_key($offer,array_flip(['id','product_id','amount'])),$snapshot);
            $this->assertEquals($case['offers'],$actual,$case['label']);
            $this->assertEquals($case['total'],ProductPromotion::total($offers),$case['label']);
            $preview = array_map(static fn($p)=>['id'=>$p['id'],'product_id'=>$p['product_id'],'amount_cents'=>\App\Libraries\DiscountPolicy::cents($p['amount'])],$snapshot);
            $this->assertTrue(ProductPromotion::matchesPreview(['auto_promo_amount'=>number_format($case['total'],2,'.',''),'auto_promo_snapshot'=>json_encode($preview)],$offers));
        }
    }
}
