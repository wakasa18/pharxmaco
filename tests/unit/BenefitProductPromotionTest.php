<?php

use App\Libraries\BenefitProductPromotion;
use App\Libraries\DiscountPolicy;
use App\Libraries\StatutoryDiscount;
use PHPUnit\Framework\TestCase;

final class BenefitProductPromotionTest extends TestCase
{
    private function quote(array $case, string $kind = 'senior_citizen'): array
    {
        $config = new \Config\ReceiptInvoice(); $config->taxStatus = $case['status']; $config->vatRate = 12.0;
        $items = [];
        foreach ($case['items'] as $line) $items[$line['productId']] = ['product_id'=>$line['productId'], 'subtotal'=>$line['subtotal'],
            'category_id'=>$line['categoryId'] ?? 0,
            'tax_treatment'=>$line['taxTreatment'], 'senior_pwd_eligible'=>1];
        $input = ['beneficiary_name'=>'Test Beneficiary', 'beneficiary_id'=>'TEST-ONLY', 'benefit_verified'=>'1',
            'benefit_products'=>array_fill_keys($case['selected'], '1')];
        $promos = array_map(static fn($p)=>$p + ['status'=>'active','applies_to'=>'product','discount_name'=>'Product promo'], $case['promos']);
        return BenefitProductPromotion::combine($kind, StatutoryDiscount::quote($kind,$items,$input,$config),$items,$promos,'2026-10-15');
    }

    public function testSharedMoreFavorableAndMixedBasketCasesForSeniorAndPwd(): void
    {
        $cases = json_decode(file_get_contents(ROOTPATH.'tests/fixtures/benefit-product-promo-cases.json'),true,512,JSON_THROW_ON_ERROR);
        foreach ($cases as $case) foreach (['senior_citizen','pwd'] as $kind) {
            $quote = $this->quote($case,$kind);
            $this->assertEquals($case['discount'],$quote['discount'],$case['label']);
            $this->assertEquals($case['vat'],$quote['vat_exemption'],$case['label']);
            $this->assertEquals($case['promo'],$quote['promotion_amount']??0,$case['label']);
            $this->assertSame($case['promo_id'],$quote['promotion']['id']??null,$case['label']);
            if (isset($case['promo_ids'])) $this->assertSame($case['promo_ids'],array_column(array_column($quote['promotions'],'discount'),'id'));
            $this->assertSame(DiscountPolicy::cents((float)$case['final']),DiscountPolicy::cents(array_sum(array_column($case['items'],'subtotal')))
                - DiscountPolicy::cents($quote['discount']) - DiscountPolicy::cents($quote['vat_exemption']),$case['label']);
            foreach ($quote['items'] as $plan) if ($plan['kind']===null) $this->assertEquals(0,$plan['vat_exemption']);
        }
    }

    public function testGeneratedCombinedCentavosAndPersistedBreakdown(): void
    {
        $results=[];
        for ($i=1;$i<=128;$i++) {
            $case=['status'=>$i%3?'vat':'non_vat', 'items'=>[
                ['productId'=>7,'categoryId'=>1,'subtotal'=>($i*13%999+1)/100,'taxTreatment'=>'taxable'],
                ['productId'=>2,'categoryId'=>1,'subtotal'=>($i*31%997+1)/100,'taxTreatment'=>$i%2?'taxable':'vat_exempt'],
                ['productId'=>9,'categoryId'=>2,'subtotal'=>($i*17%991+1)/100,'taxTreatment'=>'taxable']], 'selected'=>[7,2], 'promos'=>[
                    ['id'=>3,'product_id'=>7,'discount_type'=>'percentage','discount_value'=>$i%60+1],
                    ['id'=>4,'product_id'=>9,'discount_type'=>'fixed','discount_value'=>($i%50+1)/100],
                    ['id'=>5,'applies_to'=>'category','category_id'=>1,'discount_type'=>$i%2?'fixed':'percentage',
                        'discount_value'=>$i%2?($i%200+1)/100:$i%60+1,'max_discount_amount'=>($i%30+1)/10,'minimum_purchase'=>($i%10)/10]]];
            $quote=$this->quote($case);
            $this->assertSame(DiscountPolicy::cents($quote['discount']),array_sum(array_map(static fn($p)=>DiscountPolicy::cents($p['discount']),$quote['items'])));
            $config=new \Config\ReceiptInvoice();$config->taxStatus=$case['status'];
            $sale=['discount_amount'=>$quote['discount'],'receipt_metadata'=>\App\Libraries\ReceiptInvoice::snapshot(['discount_name'=>$quote['label']],$config,null,$quote['beneficiary'])];
            $breakdown=\App\Libraries\ReceiptInvoice::discountBreakdown($sale);
            $this->assertNotNull($breakdown);
            $this->assertSame(DiscountPolicy::cents($quote['discount']),DiscountPolicy::cents($breakdown['statutory'])+DiscountPolicy::cents($breakdown['promotion']));
            $results[]=$case+['discount'=>$quote['discount'],'vat'=>$quote['vat_exemption'],'promo'=>$quote['promotion_amount']??0,
                'promo_id'=>$quote['promotion']['id']??null,'promo_ids'=>array_column(array_column($quote['promotions'],'discount'),'id'),'plans'=>$quote['items']];
        }
        $dir=WRITEPATH.'benefit-promo-review/'; if(!is_dir($dir))mkdir($dir,0775,true);
        file_put_contents($dir.'math-comparison.json',json_encode($results,JSON_THROW_ON_ERROR));
    }

    public function testRoundingChoicesMatchExhaustiveBasketSearch(): void
    {
        // Independent oracle: enumerate every promo/statutory subset for small baskets.
        for ($case=1;$case<=96;$case++) {
            $status=$case%2?'vat':'non_vat';$lines=[];$promos=[];$gross=0;
            for ($id=1;$id<=1+$case%7;$id++) {
                $price=10000+($case*17+$id*3)%101;
                $lines[]=['productId'=>$id,'subtotal'=>$price/100,'taxTreatment'=>$id%3?'taxable':'vat_exempt'];
                $base=$status==='vat' && $id%3?(int)round($price*100/112):$price;
                $promos[]=['id'=>$id+10,'product_id'=>$id,'discount_type'=>'fixed','discount_value'=>($price-$base+$base/5+($case+$id)%5-2)/100];
                $gross+=$price;
            }
            $input=['status'=>$status,'items'=>$lines,'selected'=>array_column($lines,'productId'),'promos'=>$promos];
            $quote=$this->quote($input,$case%3?'senior_citizen':'pwd');
            $best=-1;$fewest=PHP_INT_MAX;
            for ($mask=0;$mask<(1<<count($lines));$mask++) {
                $base=0;$vat=0;$promo=0;$count=0;
                foreach ($lines as $i=>$line) {
                    $price=DiscountPolicy::cents($line['subtotal']);
                    if ($mask&(1<<$i)) {$promo+=DiscountPolicy::cents($promos[$i]['discount_value']);$count++;continue;}
                    $net=$status==='vat' && $line['taxTreatment']==='taxable'?(int)round($price*100/112):$price;
                    $base+=$net;$vat+=$price-$net;
                }
                $saving=$vat+(int)round($base/5)+$promo;
                if ($saving>$best || ($saving===$best && $count<$fewest)) {$best=$saving;$fewest=$count;}
            }
            $this->assertSame($best,DiscountPolicy::cents($quote['discount'])+DiscountPolicy::cents($quote['vat_exemption']),'Basket '.$case);
            $this->assertCount($fewest,$quote['promotions'],'Equal savings preserve as many statutory lines as possible.');
        }
    }
}
