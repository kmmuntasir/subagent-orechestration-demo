<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private Product $model,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = $this->model->newQuery()->with('category');

        // Search filter (LIKE across name and description)
        if (isset($filters['search']) && is_string($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter (exact match)
        if (isset($filters['category_id']) && is_int($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Order by created_at DESC
        $query->orderByDesc('created_at');

        return $query->get();
    }

    public function findById(int $id): ?Product
    {
        return $this->model->with('category')->find($id);
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->model->findOrFail($id);
        $product->fill($data);
        $product->save();

        return $product->fresh(['category']);
    }

    public function delete(int $id): bool
    {
        $product = $this->model->findOrFail($id);
        return $product->delete();
    }
}
