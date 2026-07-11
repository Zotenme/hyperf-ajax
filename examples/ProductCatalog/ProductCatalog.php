<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf Ajax.
 *
 * @link     https://github.com/Zotenme/hyperf-ajax
 * @document https://github.com/Zotenme/hyperf-ajax/blob/main/README.md
 * @contact  zotenme@gmail.com
 * @license  https://github.com/Zotenme/hyperf-ajax/blob/main/LICENSE.md
 */

namespace App\Catalog;

final class ProductCatalog
{
    private const PER_PAGE = 4;

    /** @var list<array{id: int, name: string, category: string, price: int}> */
    private array $products = [
        ['id' => 1, 'name' => 'Mechanical keyboard', 'category' => 'office', 'price' => 120],
        ['id' => 2, 'name' => 'Wireless mouse', 'category' => 'office', 'price' => 65],
        ['id' => 3, 'name' => 'USB-C dock', 'category' => 'office', 'price' => 180],
        ['id' => 4, 'name' => 'Desk lamp', 'category' => 'home', 'price' => 45],
        ['id' => 5, 'name' => 'Coffee grinder', 'category' => 'home', 'price' => 90],
        ['id' => 6, 'name' => 'Travel backpack', 'category' => 'travel', 'price' => 140],
        ['id' => 7, 'name' => 'Packing cubes', 'category' => 'travel', 'price' => 35],
        ['id' => 8, 'name' => 'Noise-cancelling headphones', 'category' => 'travel', 'price' => 260],
        ['id' => 9, 'name' => 'Monitor arm', 'category' => 'office', 'price' => 110],
        ['id' => 10, 'name' => 'Pour-over kettle', 'category' => 'home', 'price' => 80],
    ];

    /**
     * @param array{category: string, minPrice: int} $filters
     * @return array{
     *     items: list<array{id: int, name: string, category: string, price: int}>,
     *     filters: array{category: string, minPrice: int},
     *     page: int,
     *     nextPage: null|int,
     *     total: int
     * }
     */
    public function page(array $filters, int $page = 1): array
    {
        $items = array_values(array_filter(
            $this->products,
            static fn (array $product): bool => ($filters['category'] === '' || $product['category'] === $filters['category'])
                && $product['price'] >= $filters['minPrice']
        ));
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'items' => array_slice($items, $offset, self::PER_PAGE),
            'filters' => $filters,
            'page' => $page,
            'nextPage' => $offset + self::PER_PAGE < count($items) ? $page + 1 : null,
            'total' => count($items),
        ];
    }
}
