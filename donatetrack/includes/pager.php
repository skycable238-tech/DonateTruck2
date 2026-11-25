<?php /* expects $page, $pages, $total */ ?>
<div class="d-flex justify-content-between align-items-center mt-3">
  <span class="text-muted small"><?= (int)$total ?> record(s) &middot; page <?= (int)$page ?> of <?= (int)$pages ?></span>
  <?php if ($pages > 1): ?>
  <nav><ul class="pagination pagination-sm mb-0">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e(qs(['page' => $page - 1])) ?>">Previous</a></li>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e(qs(['page' => $i])) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e(qs(['page' => $page + 1])) ?>">Next</a></li>
  </ul></nav>
  <?php endif; ?>
</div>
