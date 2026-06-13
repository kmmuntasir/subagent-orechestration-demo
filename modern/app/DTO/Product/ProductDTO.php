<?php

declare(strict_types=1);

namespace App\DTO\Product;

use App\Models\Product;

readonly class ProductDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public float $price,
        public int $stock,
        public ?int $categoryId,
        public ?string $createdAt,
    ) {}

    public static function fromModel(Product $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            price: (float) $model->price,
            stock: $model->stock,
            categoryId: $model->category_id,
            createdAt: $model->created_at?->format('Y-m-d H:i:s'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'category_id' => $this->categoryId,
            'created_at' => $this->createdAt,
        ];
    }
}
