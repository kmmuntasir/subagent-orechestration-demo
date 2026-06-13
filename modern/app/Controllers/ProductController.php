<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ProductServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

readonly class ProductController
{
    public function __construct(
        private ProductServiceInterface $service,
    ) {}

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
        ];

        $products = $this->service->listProducts($filters);

        return response()->json([
            'data' => array_map(fn ($dto) => $dto->toArray(), $products),
        ]);
    }

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $product = $this->service->getProduct($id);

            return response()->json($product->toArray());
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Product not found'], 404);
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $dto = new \App\DTO\Product\CreateProductDTO(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            price: (float) $validated['price'],
            stock: (int) $validated['stock'],
            categoryId: $validated['category_id'] ?? null,
        );

        $product = $this->service->createProduct($dto);

        return response()->json($product->toArray(), 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|numeric|min:0',
                'stock' => 'sometimes|integer|min:0',
                'category_id' => 'nullable|integer|exists:categories,id',
            ]);

            $dto = new \App\DTO\Product\UpdateProductDTO(
                name: $validated['name'] ?? null,
                description: $validated['description'] ?? null,
                price: isset($validated['price']) ? (float) $validated['price'] : null,
                stock: isset($validated['stock']) ? (int) $validated['stock'] : null,
                categoryId: $validated['category_id'] ?? null,
            );

            $product = $this->service->updateProduct($id, $dto);

            return response()->json($product->toArray());
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Product not found'], 404);
        }
    }

    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $this->service->deleteProduct($id);

            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Product not found'], 404);
        }
    }
}
