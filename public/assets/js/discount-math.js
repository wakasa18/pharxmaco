/* Keep checkout and correction previews in centavos, matching DiscountPolicy. */
(() => {
    'use strict';
    const cents = value => Math.round((Number(value) + Number.EPSILON) * 100);
    const calculate = (subtotal, rule) => {
        const eligible = Math.max(0, cents(subtotal));
        if (eligible < cents(rule.minimum || 0)) return 0;
        const value = cents(rule.value);
        let discount = rule.type === 'percentage' ? Math.floor((eligible * value + 5000) / 10000) : value;
        if (rule.maximum != null) discount = Math.min(discount, cents(rule.maximum));
        return Math.min(eligible, Math.max(0, discount)) / 100;
    };
    const statutory = (lines, taxStatus, vatRate) => {
        if (!['vat', 'non_vat'].includes(taxStatus) || (taxStatus === 'vat' && Number(vatRate) !== 12)) {
            throw new Error('Configure the seller VAT/Non-VAT status and 12% VAT rate before using this benefit.');
        }
        let baseCents = 0, vatCents = 0;
        for (const line of lines) {
            if (taxStatus === 'vat' && !['taxable','vat_exempt','zero_rated'].includes(line.taxTreatment)) {
                throw new Error('Set the tax classification of each eligible product before checkout.');
            }
            const gross = cents(line.subtotal);
            if (gross <= 0) throw new Error('Eligible products must have a positive selling price.');
            const base = taxStatus === 'vat' && line.taxTreatment === 'taxable' ? Math.floor((gross * 100 + 56) / 112) : gross;
            baseCents += base;
            vatCents += gross - base;
        }
        return { discount: Math.floor((baseCents + 2.5) / 5) / 100, vatExemption: vatCents / 100 };
    };
    const bestProductPromotion = (lines, promotions) => {
        let best = null;
        for (const promo of promotions) {
            if (!(promo.id > 0) || !(promo.productId > 0)) continue;
            const eligible = lines.reduce((sum, line) => line.productId === promo.productId ? sum + cents(line.subtotal) : sum, 0) / 100;
            const amount = calculate(eligible, promo);
            if (amount <= 0) continue;
            if (!best || amount > best.amount || (amount === best.amount && promo.id < best.id)) {
                best = { ...promo, amount };
            }
        }
        return best;
    };
    const productPromotions = (lines, promotions) => {
        const offers = new Map();
        for (const promo of promotions) {
            const scope = promo.scope || 'product', target = scope === 'category' ? promo.categoryId : promo.productId;
            if (!(promo.id > 0) || !['product','category'].includes(scope) || !(target > 0)) continue;
            if (!['percentage','fixed'].includes(promo.type) || !Number.isFinite(promo.value) || promo.value <= 0
                || (promo.type === 'percentage' && promo.value > 100) || !Number.isFinite(promo.minimum ?? 0) || (promo.minimum ?? 0) < 0
                || (promo.maximum != null && (!Number.isFinite(promo.maximum) || promo.maximum <= 0))) continue;
            const bases = new Map();
            for (const line of lines) {
                if ((scope === 'category' ? line.categoryId : line.productId) !== target) continue;
                bases.set(line.productId, (bases.get(line.productId) || 0) + cents(line.subtotal));
            }
            const sorted = [...bases].sort((a,b)=>a[0]-b[0]);
            const total = sorted.reduce((sum,line)=>sum+line[1],0), amount = cents(calculate(total/100,promo));
            const shares = sorted.map(([productId,base],index)=>{
                const raw = total ? base/total*amount : 0;
                return {productId,base,index,amount:Math.floor(raw),remainder:raw-Math.floor(raw)};
            });
            let remaining = amount-shares.reduce((sum,line)=>sum+line.amount,0);
            for (const line of [...shares].sort((a,b)=>b.remainder-a.remainder || a.index-b.index)) {
                if (remaining>0 && line.amount<line.base) {line.amount++;remaining--;}
            }
            for (const share of shares) {
                if (share.amount<=0) continue;
                const offer = {...promo,productId:share.productId,amount:share.amount/100};
                const previous = offers.get(offer.productId);
                if (!previous || offer.amount > previous.amount || (offer.amount === previous.amount && offer.id < previous.id)) offers.set(offer.productId,offer);
            }
        }
        return [...offers.values()].sort((a,b) => a.productId-b.productId);
    };
    const statutoryPlans = (eligible, taxStatus, vatRate) => {
        const quote = statutory(eligible, taxStatus, vatRate);
        const bases = eligible.map((line, index) => {
            const gross = cents(line.subtotal);
            const base = taxStatus === 'vat' && line.taxTreatment === 'taxable' ? Math.floor((gross * 100 + 56) / 112) : gross;
            return { id: line.productId, base, vat: gross - base, index };
        });
        const total = bases.reduce((sum, line) => sum + line.base, 0), target = cents(quote.discount);
        const shares = bases.map(line => {
            const share = total ? line.base / total * target : 0;
            return { ...line, discount: Math.floor(share), remainder: share - Math.floor(share) };
        });
        let remaining = target - shares.reduce((sum, line) => sum + line.discount, 0);
        for (const line of [...shares].sort((a,b) => b.remainder-a.remainder || a.index-b.index)) {
            if (remaining > 0 && line.discount < line.base) { line.discount++; remaining--; }
        }
        return new Map(shares.map(line => [line.id, { discount: line.discount / 100, vatExemption: line.vat / 100, statutory: true }]));
    };
    const combinedStatutory = (lines, eligible, promotions, taxStatus, vatRate) => {
        const original = statutoryPlans(eligible, taxStatus, vatRate);
        const offers = productPromotions(lines, promotions), byProduct = new Map(offers.map(offer => [offer.productId, offer]));
        // Match the server's five remainder states for an aggregate 20% benefit.
        let states = new Map([[0, {score:0,count:0,node:null}]]);
        for (const line of [...eligible].sort((a,b)=>a.productId-b.productId)) {
            const vat = cents(original.get(line.productId).vatExemption), base = cents(line.subtotal)-vat;
            const next = new Map();
            const keep = (remainder, candidate) => {
                const current = next.get(remainder);
                if (!current || candidate.score>current.score || (candidate.score===current.score && candidate.count<current.count)) next.set(remainder,candidate);
            };
            for (const [remainder,state] of [...states].sort((a,b)=>a[0]-b[0])) {
                const sum = remainder+base%5;
                keep(sum%5,{score:state.score+vat+Math.floor(base/5)+Math.floor(sum/5),count:state.count,
                    node:{id:line.productId,promo:false,previous:state.node}});
                const offer = byProduct.get(line.productId);
                if (offer) keep(remainder,{score:state.score+cents(offer.amount),count:state.count+1,
                    node:{id:line.productId,promo:true,previous:state.node}});
            }
            states = next;
        }
        let best = null;
        for (const [remainder,state] of [...states].sort((a,b)=>a[0]-b[0])) {
            const candidate = {...state,score:state.score+(remainder>=3?1:0)};
            if (!best || candidate.score>best.score || (candidate.score===best.score && candidate.count<best.count)) best=candidate;
        }
        const chosen = new Set();
        for (let node=best.node;node;node=node.previous) if(node.promo) chosen.add(node.id);
        const selectedPromotions = offers.filter(offer => !original.has(offer.productId) || chosen.has(offer.productId));
        const plans = statutoryPlans(eligible.filter(line=>!chosen.has(line.productId)),taxStatus,vatRate);
        for (const offer of selectedPromotions) plans.set(offer.productId,{discount:offer.amount,vatExemption:0,statutory:false});
        const discount = [...plans.values()].reduce((sum,item) => sum + cents(item.discount),0) / 100;
        const vatExemption = [...plans.values()].reduce((sum,item) => sum + cents(item.vatExemption),0) / 100;
        const promotionAmount = selectedPromotions.reduce((sum, offer) => sum + cents(offer.amount), 0) / 100;
        return { discount, vatExemption, promotions: selectedPromotions, promotion: selectedPromotions[0] || null, promotionAmount,
            statutoryDiscount: (cents(discount) - cents(promotionAmount)) / 100, items: Object.fromEntries(plans) };
    };
    window.PharxmacoDiscount = Object.freeze({ cents, calculate, statutory, bestProductPromotion, productPromotions, combinedStatutory });
})();
