<?php
declare(strict_types=1);

function render_pagination(array $pager, string $baseUrl): void
{
    if ($pager['pages'] <= 1) {
        return;
    }
    $page = $pager['page'];
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    echo '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $nextDisabled = $page >= $pager['pages'] ? ' disabled' : '';
    echo '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . e($baseUrl . $sep . 'page=' . ($page - 1)) . '">Prev</a></li>';
    $start = max(1, $page - 2);
    $end = min($pager['pages'], $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a></li>';
    }
    echo '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . e($baseUrl . $sep . 'page=' . ($page + 1)) . '">Next</a></li>';
    echo '</ul></nav>';
}
