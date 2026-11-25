/* A confirmation view of the current form; existing server validation remains authoritative. */
(() => {
    if (window.PharxmacoTransactionOverview) return;
    const currency = new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'});
    const money = cents => currency.format(cents / 100);
    const cents = value => Math.round((Number(value) + Number.EPSILON) * 100);
    const refundCents = (quantity, remainingQuantity, remainingCents) => {
        if (![quantity,remainingQuantity,remainingCents].every(Number.isSafeInteger) || quantity < 1 || remainingQuantity < 1 || remainingCents < 0) return 0;
        if (quantity === remainingQuantity) return remainingCents;
        // Prorate the recorded remaining net amount, with positive half-up cent rounding.
        const numerator=BigInt(remainingCents)*BigInt(quantity), denominator=BigInt(remainingQuantity);
        return Math.min(remainingCents, Number((2n*numerator+denominator)/(2n*denominator)));
    };
    const text = value => String(value ?? '').trim();
    const method = value => ({cash:'Cash', gcash:'GCash', card:'Card'}[value] || 'Not selected');
    const condition = value => ({resellable:'Resellable', quarantined:'Quarantined', damaged:'Damaged', expired:'Expired'}[value] || 'Not selected');
    const node = (tag, value, className) => { const n=document.createElement(tag); if(value !== undefined)n.textContent=String(value); if(className)n.className=className; return n; };

    function refundModel(form, original) {
        const rows=[]; let total=0;
        form.querySelectorAll('.refund-qty-input').forEach(input => {
            const quantity=Number(input.value);
            if (input.disabled || quantity <= 0) return;
            const amount=refundCents(quantity, Number(input.max), Number(input.dataset.remainingCents));
            const kind=form.elements.namedItem('return_condition['+input.dataset.rowId+']')?.value;
            total+=amount;
            rows.push([input.dataset.name, quantity, condition(kind), money(amount), kind === 'resellable' ? 'Return '+quantity+' to available stock' : 'Keep outside available stock']);
        });
        const reason=text(form.elements.namedItem('reason')?.value);
        return {summary:[['Invoice',original.invoice],['Branch',original.branch],['Refund method',method(form.elements.namedItem('refund_method')?.value)],['Pay back to customer',money(total)]],
            sections:[{title:'Items to refund',columns:['Product','Quantity','Condition','Refund amount','Stock effect'],rows},{title:'Refund reason',text:reason}],
            blocked: !rows.length ? 'Select at least one item to refund.' : reason.length < 3 ? 'Enter a refund reason of at least 3 characters.' : '',
            note:'Resellable returns go back to available stock. Quarantined, damaged and expired returns stay outside available stock.'};
    }

    function correctionModel(form, original) {
        const quantities=[], changes=[];
        form.querySelectorAll('#itemsBody tr').forEach(row => {
            const input=row.querySelector('.qty-input'), old=Number(input.dataset.original), value=Number(input.value), delta=value-old;
            if (delta) quantities.push([text(row.querySelector('.product-cell').textContent),old,value,delta > 0 ? 'Remove '+delta+' from available stock' : 'Return '+(-delta)+' to available stock']);
        });
        const final=Number(form.dataset.overviewFinalCents), difference=Number(form.dataset.overviewDifference);
        const payment=form.elements.namedItem('payment_method').value;
        const reference=payment === 'cash' ? '' : text(form.elements.namedItem('reference_no').value);
        for (const [label,before,after] of [['Payment method',method(original.payment_method),method(payment)],['Payment reference',text(original.reference_no),reference],['Notes',text(original.notes),text(form.elements.namedItem('notes').value)]]) {
            if (before !== after) changes.push([label,before || 'Not provided',after || 'Not provided']);
        }
        for (const [label,before,after] of [['Subtotal',cents(original.total_amount),Number(form.dataset.overviewSubtotalCents)],['Discount',cents(original.discount_amount),Number(form.dataset.overviewDiscountCents)],['Final total',cents(original.final_total),final]]) {
            if (before !== after) changes.push([label,money(before),money(after)]);
        }
        if (difference) changes.push(['Amount received',money(cents(original.amount_paid)),money(cents(original.amount_paid)+difference)]);
        const reason=text(form.elements.namedItem('correction_reason').value);
        return {summary:[['Invoice',original.invoice],['Branch',original.branch],['Recorded total',money(cents(original.final_total))],['Corrected total',money(final)],
            ['Payment / payout method',method(payment)],[difference > 0 ? 'Collect from customer' : difference < 0 ? 'Pay back to customer' : 'Payment difference',difference ? money(Math.abs(difference)) : 'No additional payment or payout']],
            sections:[{title:'Quantity changes',columns:['Product','Current quantity','New quantity','Stock effect'],rows:quantities},{title:'Other changes',columns:['Field','Current value','New value'],rows:changes},{title:'Correction reason',text:reason}],
            blocked:!quantities.length && !changes.length ? 'No changes to save. Return to the form to make a correction.' : reason.length < 5 ? 'Enter a correction reason of at least 5 characters.' : '',
            note:'Original change given stays '+money(cents(original.change_amount))+'. Settle only the payment difference shown above.'};
    }

    function start() {
        document.querySelectorAll('form[data-transaction-confirmation]').forEach(form => {
            let submitted=false;
            form.addEventListener('submit',event=>{if(submitted){event.preventDefault();return;}if(form.checkValidity())submitted=true;});
            window.addEventListener('pageshow',()=>{submitted=false;});
        });
        document.querySelectorAll('form[data-transaction-overview]').forEach(form => {
            const overlay=document.getElementById(form.id+'-overview'), data=document.getElementById(form.id+'-overview-data'), modal=window.PharxmacoModal;
            if (!overlay || !data || !modal || form.dataset.overviewInitialized) return;
            form.dataset.overviewInitialized='1';
            const original=JSON.parse(data.textContent), content=overlay.querySelector('[data-overview-content]'), status=overlay.querySelector('[data-overview-status]');
            const ack=overlay.querySelector('[data-overview-ack]'), confirm=overlay.querySelector('[data-overview-confirm]'), back=overlay.querySelector('[data-overview-back]');
            const trigger=form.querySelector('button[type=submit]');
            let reviewed=null, approved=null, saving=false, blocked=false;
            const signature=()=>JSON.stringify([...new FormData(form).entries()]);
            const prepare=()=>form.dispatchEvent(new Event('transaction:prepare-overview'));
            const close=()=>modal.close(overlay);
            function render(message='') {
                prepare();
                const model=form.dataset.transactionOverview === 'refund' ? refundModel(form,original) : correctionModel(form,original);
                content.replaceChildren();
                const summary=node('dl',undefined,'transaction-overview-summary');
                model.summary.forEach(([label,value])=>{const pair=node('div');pair.append(node('dt',label),node('dd',value));summary.append(pair);});
                content.append(summary);
                model.sections.forEach(section=>{
                    if (section.rows && !section.rows.length) return;
                    const area=node('section',undefined,'transaction-overview-section');area.append(node('h3',section.title));
                    if (section.rows) {
                        const table=node('table',undefined,'transaction-overview-table'),head=node('thead'),headRow=node('tr'),body=node('tbody');
                        section.columns.forEach(label=>headRow.append(node('th',label)));head.append(headRow);
                        section.rows.forEach(row=>{const tr=node('tr');row.forEach((value,i)=>{const td=node('td',value);td.dataset.label=section.columns[i];tr.append(td);});body.append(tr);});
                        table.append(head,body);area.append(table);
                    } else area.append(node('p',section.text));
                    content.append(area);
                });
                content.append(node('p',model.note,'transaction-overview-note'));
                blocked=!!model.blocked; status.textContent=model.blocked || message; status.hidden=!status.textContent;
                ack.checked=false; ack.disabled=blocked; confirm.disabled=true; reviewed=signature();
                overlay.querySelector('.transaction-overview-body').scrollTop=0;
            }
            document.body.append(overlay);
            trigger.setAttribute('aria-haspopup','dialog');trigger.setAttribute('aria-controls',overlay.id);
            overlay.querySelectorAll('[data-overview-close]').forEach(button=>button.addEventListener('click',close));
            overlay.addEventListener('click',event=>{if(event.target===overlay)close();});
            ack.addEventListener('change',()=>{confirm.disabled=!ack.checked || blocked || saving;});
            form.addEventListener('submit',event=>{
                if (saving) { event.preventDefault(); return; }
                if (approved !== null && approved === signature() && form.checkValidity()) { saving=true; confirm.disabled=true; return; }
                event.preventDefault(); approved=null; prepare(); if(trigger.disabled || !form.reportValidity())return;
                render(); modal.open(overlay,{className:'open',trigger,initialFocus:back});
            });
            confirm.addEventListener('click',()=>{
                if(saving || blocked || !ack.checked)return;
                prepare();
                if(reviewed !== signature()) {render('The form changed. Check the updated overview and confirm again.');return;}
                if(!form.checkValidity()) {close();form.reportValidity();return;}
                approved=signature();close();form.requestSubmit(trigger);
            });
            window.addEventListener('pageshow',()=>{saving=false;approved=null;reviewed=null;ack.checked=false;confirm.disabled=true;trigger.disabled=false;prepare();});
        });
    }
    window.PharxmacoTransactionOverview=Object.freeze({refundCents});
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
