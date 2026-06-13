<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    /**
     * List products with optional filters, eager loading, and ordering.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, Product>
     */
    public function list(array $filters = []): Collection;

    /**
     * Find a product by ID with eager-loaded relationships.
     *
     * @param int $id
     * @return Product|null
     */
    public function findById(int $id): ?Product;

    /**
     * Create a new product.
     *
     * @param array<string, mixed> $data
     * @return Product
     */
    public function create(array $data): Product;

    /**
     * Update an existing product.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return Product
     */
    public function update(int $id, array $data): Product;

    /**
     * Delete a product by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
