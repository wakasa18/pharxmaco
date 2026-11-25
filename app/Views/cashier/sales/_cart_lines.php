<?php if (empty($cart)): ?>
        <div class="cart-empty">
            <div class="cart-empty-icon" aria-hidden="true">
                <svg width="27" height="27" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L20 8H6"/>
                    <circle cx="10" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/>
                </svg>
            </div>
            <div class="cart-empty-text">Cart is empty</div>
            <div class="cart-empty-sub">Tap a product to add it</div>
            <button type="button" class="btn btn-primary pos-mobile-action" data-pos-go="products">Browse products</button>
        </div>
        <?php else: ?>
        <div class="cart-item-list" id="cartItemList">
            <?php foreach ($cart as $item): ?>
            <div class="cart-item cart-row"
                 data-product-id="<?= esc($item['product_id']) ?>"
                 data-category-id="<?= esc($item['category_id'] ?? '') ?>"
                 data-price="<?= (float)$item['price'] ?>">
                <!-- Row 1: name + remove -->
                <div class="cart-item-top">
                    <div class="cart-item-info">
                        <div class="cart-item-name"><?= esc($item['product_name']) ?></div>
                        <div class="cart-item-unit">₱<?= number_format((float)$item['price'], 2) ?> each</div>
                    </div>
                    <form method="post" action="<?= site_url('cashier/sales/remove-cart-item') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                        <button type="submit" class="cart-remove-btn" title="Remove" aria-label="Remove <?= esc($item['product_name'], 'attr') ?> from cart">✕</button>
                    </form>
                </div>
                <!-- Row 2: qty controls + subtotal -->
                <div class="cart-item-bottom">
                    <div class="cart-qty-wrap">
                        <button class="qty-btn qty-dec" type="button" aria-label="Decrease quantity for <?= esc($item['product_name'], 'attr') ?>">−</button>
                        <input type="number" class="cart-qty-input" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= (int)($item['stock'] ?? 999999) ?>" aria-label="Quantity for <?= esc($item['product_name']) ?>">
                        <button class="qty-btn qty-inc" type="button" aria-label="Increase quantity for <?= esc($item['product_name'], 'attr') ?>">+</button>
                    </div>
                    <div class="cart-item-sub cart-subtotal-cell">₱<?= number_format((float)$item['subtotal'], 2) ?></div>
                </div>
                <div class="cart-qty-warning"></div>
                <div class="cart-promo-note" data-line-promo hidden></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
