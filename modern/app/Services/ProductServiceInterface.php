<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Product\CreateProductDTO;
use App\DTO\Product\ProductResponseDTO;
use App\DTO\Product\UpdateProductDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface ProductServiceInterface
{
    /**
     * List products with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return  array<int, ProductResponseDTO>
     */
    public function listProducts(array $filters = []): array;

    /**
     * Get a product by ID.
     *
     * @throws  ModelNotFoundException
     */
    public function getProduct(int $id): ProductResponseDTO;

    /**
     * Create a new product.
     */
    public function createProduct(CreateProductDTO $dto): ProductResponseDTO;

    /**
     * Update a product.
     *
     * @throws  ModelNotFoundException
     */
    public function updateProduct(int $id, UpdateProductDTO $dto): ProductResponseDTO;

    /**
     * Delete a product.
     *
     * @throws  ModelNotFoundException
     */
    public function deleteProduct(int $id): void;
}
