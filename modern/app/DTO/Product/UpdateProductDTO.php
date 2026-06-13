<?php

declare(strict_types=1);

namespace App\DTO\Product;

readonly class UpdateProductDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?float $price = null,
        public ?int $stock = null,
        public ?int $categoryId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'category_id' => $this->categoryId,
        ], fn ($value) => $value !== null);
    }
}
