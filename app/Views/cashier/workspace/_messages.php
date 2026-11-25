<?php foreach (['success','error'] as $messageType): ?>
    <?php if ($message = session()->getFlashdata($messageType)): ?><div class="alert <?= $messageType === 'error' ? 'danger' : 'success' ?>" role="status"><?= esc($message) ?></div><?php endif; ?>
<?php endforeach; ?>
