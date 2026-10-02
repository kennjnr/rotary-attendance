<?php
// admin/includes/pagination.php
// Shared pagination helpers for admin list pages.

/**
 * Work out the current page, offset and total pages.
 */
function paginate(int $totalRows, int $perPage = 10): array
{
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page       = (int)($_GET['page'] ?? 1);
    $page       = min(max(1, $page), $totalPages);

    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'offset'      => ($page - 1) * $perPage,
        'total'       => $totalRows,
        'total_pages' => $totalPages,
    ];
}

/**
 * Build a URL to the given page, keeping the other query params (search, filters).
 */
function pageUrl(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

/**
 * Render the "Showing X–Y of Z" text and the page links.
 */
function renderPagination(array $p): string
{
    if ($p['total'] === 0) return '';

    $from = $p['offset'] + 1;
    $to   = min($p['offset'] + $p['per_page'], $p['total']);

    $html  = '<div class="pagination-bar">';
    $html .= '<span class="pagination-info">Showing ' . $from . '–' . $to . ' of ' . $p['total'] . '</span>';

    if ($p['total_pages'] > 1) {
        $html .= '<div class="pagination">';

        // Previous
        $html .= $p['page'] > 1
            ? '<a class="btn btn-outline btn-sm" href="' . htmlspecialchars(pageUrl($p['page'] - 1)) . '">‹ Prev</a>'
            : '<span class="btn btn-outline btn-sm disabled">‹ Prev</span>';

        // Page numbers (window of 2 around current, plus first/last)
        $last = 0;
        for ($i = 1; $i <= $p['total_pages']; $i++) {
            if ($i === 1 || $i === $p['total_pages'] || abs($i - $p['page']) <= 2) {
                if ($last && $i - $last > 1) {
                    $html .= '<span class="pagination-gap">…</span>';
                }
                $html .= $i === $p['page']
                    ? '<span class="btn btn-primary btn-sm">' . $i . '</span>'
                    : '<a class="btn btn-outline btn-sm" href="' . htmlspecialchars(pageUrl($i)) . '">' . $i . '</a>';
                $last = $i;
            }
        }

        // Next
        $html .= $p['page'] < $p['total_pages']
            ? '<a class="btn btn-outline btn-sm" href="' . htmlspecialchars(pageUrl($p['page'] + 1)) . '">Next ›</a>'
            : '<span class="btn btn-outline btn-sm disabled">Next ›</span>';

        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '<style>
        .pagination-bar { display:flex; align-items:center; justify-content:space-between;
                          flex-wrap:wrap; gap:10px; padding:14px 20px; border-top:1px solid var(--border); }
        .pagination-info { color:var(--muted); font-size:0.85rem; }
        .pagination { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .pagination .disabled { opacity:0.4; pointer-events:none; }
        .pagination-gap { color:var(--muted); padding:0 4px; }
    </style>';

    return $html;
}
