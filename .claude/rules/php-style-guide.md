# PHP Style Guide

Baseline **PSR-12** + project additions below. Laravel PER Coding Style supplements PSR-12 for framework idioms.

## Formatting

- PHP CS Fixer or Laravel Pint for formatting (Pint = project default).
- Line length: 120 chars max.
- Indent: 4 spaces. No tabs.
- Opening brace same line for classes/methods (Allman forbidden).
- `declare(strict_types=1);` first statement every file.
- One class per file. Filename match class name (`ProductRepository.php` → `class ProductRepository`).
- Trailing comma multi-line arrays, argument lists, parameters.

## Naming Conventions

### Files
- Classes: PascalCase match class name (`ProductController.php`, `CreateProductDTO.php`)
- Scripts/views (legacy only): snake_case (`edit_product.php`)

### Classes, Variables, Functions
- PascalCase for classes, interfaces, traits, enums (`ProductService`, `ProductRepositoryInterface`)
- camelCase for methods/variables (`findById`, `$categoryName`)
- SCREAMING_SNAKE_CASE for constants (`MAX_UPLOAD_SIZE`)
- snake_case for DB columns/table names (`category_id`, `products`)
- Adjective `Interface` suffix on contracts (`ProductRepositoryInterface`)

```php
// Classes & interfaces
class Product {}
interface ProductRepositoryInterface {}

// Enums
enum ProductStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

// Constants
const MAX_PAGE_SIZE = 100;

// Variables and methods
$productName = $request->input('name');
$created = $service->createProduct($dto);
```

### Acronyms
- Keep case consistent: `URL`, `ID`, `HTTP`, `API` (all caps in constants, idiomatic elsewhere — `findById`, not `findByID`).

## Code Structure

### Functions and Methods

- Keep short, focused (one responsibility, ideally <50 lines).
- Early returns reduce nesting.
- Type every parameter + return type. Use nullable/union types explicitly.

```php
public function getProduct(int $id): ProductResponseDTO
{
    $product = $this->repository->findById($id)
        ?? throw new ModelNotFoundException("Product [{$id}] not found.");

    return ProductResponseDTO::fromModel($product);
}
```

### Classes and Constructors

- Use constructor property promotion. Inject deps as readonly promoted properties.
- DTOs = `readonly class` with promoted properties — immutable by construction.

```php
readonly class CreateProductDTO
{
    public function __construct(
        public string $name,
        public string $description,
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
```

### Error Handling

Throw typed exceptions; catch at controller boundary, convert to JSON.

```php
try {
    return $this->service->updateProduct($id, $dto);
} catch (ModelNotFoundException $e) {
    return response()->json(['error' => 'Product not found'], 404);
}
```

## Namespace Organization

PSR-4 maps namespace to path. Within each file, group use statements in order:

1. Framework/library classes (`Illuminate\...`)
2. App interfaces/contracts
3. App classes (DTOs, Models, Services)
4. Global/builtin

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Contracts\ProductCache;
use App\DTO\Product\CreateProductDTO;
use App\DTO\Product\ProductResponseDTO;
use App\Repositories\ProductRepositoryInterface;
```

## Documentation & Syntax Lookup

When confirming PSR-12/PER rules, PHP syntax detail, or Laravel/Eloquent conventions, **use Context7 MCP server** to pull authoritative docs instead of guessing. Resolve library first (e.g., `php`, `laravel`, `php-fig`), then query exact rule/symbol. Prefer over training-data recall for any style/syntax question cannot answer confidently.

## Things to Avoid

- Missing or `mixed` types when concrete type fits — type every signature
- String-interpolated or concatenated SQL — use Eloquent bindings or `DB::select('... where x = ?', [$x])`
- `var_dump`, `print_r`, `dd()` in committed code — use logger (`Log::...`)
- `@` error-suppression operator
- `extract()`, `eval()`, `include` of user input — injection vectors
- Unescaped output — always `htmlspecialchars()` / Blade `{{ }}` when rendering HTML
- Public properties on non-DTO classes — use methods, never expose state directly
- Magic numbers — define constants
- `else` after early return — flatten branch