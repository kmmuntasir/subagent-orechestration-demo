# PHP Testing Rules

## Overview

Project use **Pest** (test framework on PHPUnit) for unit + integration tests. Pest default; PHPUnit assertions still work since Pest run on PHPUnit. Pest docs = primary reference.

## Test Organization

```
modern/
    app/
        Services/
            ProductService.php
            ProductServiceTest.php       # Co-located with source
        Repositories/
            ProductRepository.php
            ProductRepositoryTest.php
        Controllers/
            ProductController.php
            ProductControllerTest.php    # HTTP/integration test
    tests/
        Feature/                          # End-to-end request flows
        Unit/                             # Isolated logic (move here if a class has many tests)
    phpunit.xml
```

Tests live with code they test as `*Test.php`. Move to `tests/Unit/` or `tests/Feature/` only when single class has many tests or suite need real HTTP/database stack.

## Unit Tests

### Dataset-Driven Tests (preferred)

Most scenarios clearest as data-driven tests with Pest datasets:

```php
use App\DTO\Product\CreateProductDTO;
use App\Services\ProductService;

describe('createProduct validation', function () {
    it('rejects a negative price', function ($price) {
        $service = app(ProductService::class);

        expect(fn () => $service->createProduct(
            new CreateProductDTO('Widget', 'desc', price: $price, stock: 1),
        ))->toThrow(InvalidArgumentException::class);
    })->with([
        'negative' => -1.0,
        'zero'     => 0.0,
    ]);
});
```

### Mocking

Use Pest `Mockery` integration (`Mockery::mock`, `app()->bind`) to substitute repository/service interfaces:

```php
use App\Repositories\ProductRepositoryInterface;
use App\DTO\Product\CreateProductDTO;

it('creates a product through the service', function () {
    $repo = Mockery::mock(ProductRepositoryInterface::class);
    $repo->shouldReceive('create')->once()->andReturn(new Product(['id' => 1]));

    $service = new ProductService($repo);
    $result = $service->createProduct(new CreateProductDTO('Widget', 'desc', 9.99, 5));

    expect($result->id)->toBe(1);
});
```

## Feature / HTTP Tests

Controllers tested through HTTP layer with in-memory database:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

it('lists products as JSON', function () {
    use RefreshDatabase;

    Product::factory()->count(3)->create();

    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});
```

## Running Tests

```bash
# All tests
composer test                  # resolves to: pest (or php artisan test)

# Watch mode (requires Pest's --watch)
composer test -- --watch

# Coverage (requires Xdebug or PCOV)
composer test -- --coverage --min=80

# A specific file
composer test -- app/Services/ProductServiceTest.php

# A single dataset / filter
composer test -- --filter='rejects a negative price'
```

## Best Practices

### Test Naming
- Pest: `it('does the thing')` / `test('does the thing')`
- Group related cases with `describe('scope')`

### Assertion Style
Prefer Pest expectation API; fall back to PHPUnit assertions when needed:
- `expect($value)->toBe($expected)`
- `expect($value)->toEqual($expected)` (deep)
- `expect($value)->toBeTruthy()`
- `expect(fn () => ...)->toThrow(Exception::class)`

### Arrange–Act–Assert
- Keep tests in three readable blocks; one logical assertion per test.

## Documentation & Syntax Lookup

When need exact Pest/PHPUnit API details, dataset syntax, or Laravel testing helpers, **use Context7 MCP server** to fetch authoritative docs rather than guess. Resolve library first (e.g., `pestphp`, `laravel`, `phpunit`), then query specific assertion or helper. Prefer over training-data recall for any testing API you cannot state with confidence.

## Coverage Targets

- Services / Repositories (business logic): >80%
- Controllers: critical flows (create, update, delete, 404 paths)
- Models: relationship and cast behavior only — no trivial getter tests