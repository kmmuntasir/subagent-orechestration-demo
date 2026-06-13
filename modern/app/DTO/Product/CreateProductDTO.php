<?php

declare(strict_types=1);

namespace App\DTO\Product;

readonly class CreateProductDTO
{
    public function __construct(
        public string $name,
        public ?string $description,
        public float $price,
        public int $stock,
        public ?int $categoryId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'category_id' => $this->categoryId,
        ];
    }
}
