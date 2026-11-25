# Cashier day and drawer workflow

Approved scope: personal daily totals, payment breakdown, sold products, recent sales, refunds/exchanges; persistent held sales; drawer opening/counting/closing, cash movements, immutable closing summaries, manager review, handover, branch stock alerts, printable reports. Philippine calendar dates and shifts spanning midnight remain separate.

Design: retain existing workspace fonts and theme variables. Reference palette: navy #14213d, slate #64748b, paper #ffffff, canvas #eef1f6, primary #4355ff, positive #15803d (actual theme variables remain authoritative). Signature: a reconciliation ledger connecting opening cash, cash movements and counted closing cash. Mobile stacks operational forms and tables; money uses tabular numerals. No animation is needed for financial actions.

Implementation sequence:
- Add migration 000014 with drawers, shifts, immutable financial events, persistent held sales and manager reviews.
- Add transactional services and personal day reporting; attach checkout/refund/exchange/corrections atomically.
- Add cashier My Day, shift detail/print, manager review/drawer management and navigation/login links.
- Verify money, event dates, ownership, retries, close races, handover and historical snapshot preservation; inspect responsive UI.
- Apply additive local schema; prepare standalone SQL, updated combined upgrade and hosting ZIPs.

Defaults: one drawer per branch; admins can add counters. Cashier totals are personal to their assigned branch. Admin financial operations also require their own active shift in that selling branch. Closed shifts retain their captured totals; corrections append events to the operator's current shift. No historical shift or float is invented.
