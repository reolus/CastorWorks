<?php foreach (($_SESSION['_flash'] ?? []) as $message): ?>
<div class="alert alert-<?=e($message['type'])?> alert-dismissible fade show" role="alert"><?=e($message['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; unset($_SESSION['_flash']); ?>
<?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?=e($_SESSION['flash_success'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php unset($_SESSION['flash_success']); endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?=e($_SESSION['flash_error'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php unset($_SESSION['flash_error']); endif; ?>
