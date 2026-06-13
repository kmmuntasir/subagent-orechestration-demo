<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Product\CreateProductDTO;
use App\DTO\Product\ProductResponseDTO;
use App\DTO\Product\UpdateProductDTO;
use App\Repositories\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        private ProductRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return  array<int, ProductResponseDTO>
     */
    public function listProducts(array $filters = []): array
    {
        $products = $this->repository->list($filters);

        return array_map(
            fn ($product) => ProductResponseDTO::fromModel($product),
            iterator_to_array($products),
        );
    }

    public function getProduct(int $id): ProductResponseDTO
    {
        $product = $this->repository->findById($id);

        if ($product === null) {
            throw new ModelNotFoundException("Product [{$id}] not found.");
        }

        return ProductResponseDTO::fromModel($product);
    }

    public function createProduct(CreateProductDTO $dto): ProductResponseDTO
    {
        $product = $this->repository->create($dto->toArray());

        // Load includes for response
        $product = $this->repository->findById($product->id);

        return ProductResponseDTO::fromModel($product);
    }

    public function updateProduct(int $id, UpdateProductDTO $dto): ProductResponseDTO
    {
        $product = $this->repository->update($id, $dto->toArray());

        // Load includes for response
        $product = $this->repository->findById($product->id);

        return ProductResponseDTO::fromModel($product);
    }

    public function deleteProduct(int $id): void
    {
        $deleted = $this->repository->delete($id);

        if (! $deleted) {
            throw new ModelNotFoundException("Product [{$id}] not found.");
        }
    }
}
