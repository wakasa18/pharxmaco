<?php

namespace App\Libraries;

final class BenefitProductPromotion
{
    /** Retain the more favorable treatment per item; promos never stack on statutory lines. */
    public static function combine(string $kind, array $benefit, array $items, array $promotions, string $today): array
    {
        $selected = array_keys($benefit['items']);
        $statutoryPlans = $benefit['items'];
        $offers = ProductPromotion::all($promotions, $items, $today);
        $byProduct = [];
        foreach ($offers as $offer) $byProduct[ProductPromotion::productId($offer)] = $offer;
        // 20% = one fifth. Five remainder states find the best complete basket
        // without losing a cent when individually tied offers win together.
        $states = [0=>['score'=>0,'count'=>0,'node'=>null]];
        $ids = $selected; sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            $vat = DiscountPolicy::cents($statutoryPlans[$id]['vat_exemption']);
            $base = DiscountPolicy::cents((float)$items[$id]['subtotal']) - $vat;
            $next = [];
            $keep = static function(int $remainder, array $candidate) use (&$next): void {
                $current = $next[$remainder] ?? null;
                if (!$current || $candidate['score'] > $current['score']
                    || ($candidate['score'] === $current['score'] && $candidate['count'] < $current['count'])) $next[$remainder] = $candidate;
            };
            ksort($states, SORT_NUMERIC);
            foreach ($states as $remainder=>$state) {
                $sum = $remainder + $base % 5;
                $keep($sum % 5, ['score'=>$state['score'] + $vat + intdiv($base,5) + intdiv($sum,5), 'count'=>$state['count'],
                    'node'=>['id'=>$id,'promo'=>false,'previous'=>$state['node']]]);
                if (isset($byProduct[$id])) $keep($remainder, ['score'=>$state['score'] + DiscountPolicy::cents($byProduct[$id]['amount']),
                    'count'=>$state['count']+1,'node'=>['id'=>$id,'promo'=>true,'previous'=>$state['node']]]);
            }
            $states = $next;
        }
        $best = null; ksort($states, SORT_NUMERIC);
        foreach ($states as $remainder=>$state) {
            $state['score'] += $remainder >= 3 ? 1 : 0;
            if (!$best || $state['score'] > $best['score'] || ($state['score'] === $best['score'] && $state['count'] < $best['count'])) $best = $state;
        }
        $chosen = [];
        for ($node=$best['node']; $node !== null; $node=$node['previous']) if ($node['promo']) $chosen[$node['id']] = true;
        $applied = []; $promoPlans = [];
        foreach ($offers as $offer) {
            $id = ProductPromotion::productId($offer);
            if (isset($statutoryPlans[$id]) && !isset($chosen[$id])) continue;
            unset($statutoryPlans[$id]);
            $applied[] = $offer;
            $promoPlans[$id] = ['discount'=>$offer['amount'],'vat_exemption'=>0.0,'kind'=>null];
        }
        $bases = [];
        foreach ($statutoryPlans as $id=>$plan) $bases[$id] = (DiscountPolicy::cents((float)$items[$id]['subtotal']) - DiscountPolicy::cents($plan['vat_exemption'])) / 100;
        $amount = DiscountPolicy::amount(array_sum($bases), ['type'=>'percentage','value'=>20,'minimum'=>0,'maximum'=>null]);
        foreach (DiscountAllocator::allocate($bases,$amount) as $id=>$share) $statutoryPlans[$id]['discount'] = $share;
        $benefit['items'] = $statutoryPlans + $promoPlans;
        $statutoryAmount = array_sum(array_map(static fn($p)=>DiscountPolicy::cents($p['discount']), $statutoryPlans)) / 100;
        $promoAmount = ProductPromotion::total($applied);
        $benefit['discount'] = (DiscountPolicy::cents($statutoryAmount) + DiscountPolicy::cents($promoAmount)) / 100;
        $benefit['vat_exemption'] = array_sum(array_map(static fn($p)=>DiscountPolicy::cents($p['vat_exemption']), $statutoryPlans)) / 100;
        $benefit['promotions'] = $applied;
        $benefit['promotion'] = $applied[0]['discount'] ?? null;
        $benefit['promotion_amount'] = $promoAmount;
        $benefit['selected_product_ids'] = $selected;
        $promoName = ProductPromotion::names($applied);
        $benefit['label'] = $statutoryAmount > 0 || $benefit['vat_exemption'] > 0 ? StatutoryDiscount::OPTIONS[$kind] . ' (20%)' : '';
        if ($applied) $benefit['label'] .= ($benefit['label'] !== '' ? ' + ' : '') . $promoName;
        $benefit['beneficiary']['pricing'] = ['policy'=>'more_favorable_per_item', 'statutory_discount'=>$statutoryAmount,
            'promotion_discount'=>$promoAmount, 'promotion_name'=>$promoName, 'promotions'=>ProductPromotion::snapshot($applied),
            'promotion_id'=>count($applied) === 1 ? (int)$applied[0]['discount']['id'] : null,
            'promotion_product_id'=>count($applied) === 1 ? ProductPromotion::productId($applied[0]) : null,
            'selected_product_ids'=>$selected];
        return $benefit;
    }
}
