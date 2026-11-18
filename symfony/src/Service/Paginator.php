<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds a paginator payload with the same keys as Laravel's LengthAwarePaginator.
 */
final class Paginator
{
    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    public static function paginate(array $items, int $total, Request $request, int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));

        $path = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
        $query = $request->query->all();
        unset($query['page']);

        $url = static function (int $targetPage) use ($path, $query): string {
            $query['page'] = $targetPage;

            return $path.'?'.http_build_query($query);
        };

        $links = [];
        $links[] = [
            'url' => $page > 1 ? $url($page - 1) : null,
            'label' => '&laquo; Previous',
            'active' => false,
        ];

        for ($i = 1; $i <= $lastPage; ++$i) {
            $links[] = [
                'url' => $url($i),
                'label' => (string) $i,
                'active' => $i === $page,
            ];
        }

        $links[] = [
            'url' => $page < $lastPage ? $url($page + 1) : null,
            'label' => 'Next &raquo;',
            'active' => false,
        ];

        return [
            'current_page' => $page,
            'data' => $items,
            'first_page_url' => $url(1),
            'from' => 0 === $total ? null : (($page - 1) * $perPage) + 1,
            'last_page' => $lastPage,
            'last_page_url' => $url($lastPage),
            'links' => $links,
            'next_page_url' => $page < $lastPage ? $url($page + 1) : null,
            'path' => $path,
            'per_page' => $perPage,
            'prev_page_url' => $page > 1 ? $url($page - 1) : null,
            'to' => 0 === $total ? null : min($page * $perPage, $total),
            'total' => $total,
        ];
    }
}
