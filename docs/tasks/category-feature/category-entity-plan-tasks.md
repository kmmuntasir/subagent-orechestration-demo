# Task Breakdown: Category Entity (Layered Stack)

**Source Plan:** [`category-entity-plan.md`](./category-entity-plan.md)
**Project:** SOD (subagent-orchestration-demo)
**Date:** 2026-06-13
**Slug:** `category-entity`
**Scope:** 10 new files (full Category layered stack) + 2 modified files (routes, Product import fix).

Every Category file mirrors its existing `Product` counterpart 1:1. Read the referenced `Product` source before writing each task. Codebase analysis confirmed: all 11 Product files exist; zero Category files exist; `Product.php:42` references `Category::class` with no import; no Providers/bindings, tests, migrations, or bootstrap exist (out of scope per plan §3/§10-R2).

---

## Parallelization Strategy

The Category feature is built strictly bottom-up across five layers, since each layer compiles only on top of the layer beneath it (the Service references the Repository interface; the Controller references the Service interface). Within any single batch, however, every task touches a disjoint file path, so parallel work never produces a merge conflict — the only two shared files in the whole plan (`routes/api.php` and `Product.php`) are deliberately isolated in the final batch. The model is the single foundation: Batch 1 must complete before any other layer can reference it, so the pipeline serializes on Task 1 and then fans out. After that, each batch merges only after the batch it depends on is fully merged, keeping every dependency edge satisfied at merge time.

```
Batch 1  ┌───────────────────────┐
         │ Task 1 (Model)        │
         └───────────┬───────────┘
                     ▼
Batch 2  ┌───────────────────────┐  ┌───────────────────────┐
         │ Task 2 (DTOs)         │  │ Task 3 (Repository)   │
         └───────────┬───────────┘  └───────────┬───────────┘
                     └────────────┬─────────────┘
                                  ▼
Batch 3  ┌───────────────────────┐
         │ Task 4 (Service)      │
         └───────────┬───────────┘
                     ▼
Batch 4  ┌───────────────────────┐
         │ Task 5 (Controller)   │
         └───────────┬───────────┘
                     ▼
Batch 5  ┌───────────────────────┐  ┌───────────────────────┐
         │ Task 6 (Routes)       │  │ Task 7 (Product fix)  │
         └───────────────────────┘  └───────────────────────┘
```

### Merge Order

Merge Batch 1 (Task 1, the Category model) to `main` before Batch 2 begins, because every subsequent layer's namespace and type references depend on it. Each following batch merges only after its dependency batch is merged: Batch 2 after Batch 1; Batch 3 after Batch 2; Batch 4 after Batch 3; Batch 5 after Batch 4. Because no two tasks within the same batch touch the same file, intra-batch merges never conflict — the two Batch 5 tasks edit `routes/api.php` and `Product.php` respectively, and Batch 2's tasks live in `DTO/Category/` vs `Repositories/`.

### Task Summary

| # | Batch | Target File(s) | Dependencies | Can Parallel With |
|---|-------|----------------|--------------|-------------------|
| 1 | 1 | `modern/app/Models/Category.php` | — | (sole task in batch) |
| 2 | 2 | `modern/app/DTO/Category/*.php` | Task 1 | Task 3 |
| 3 | 2 | `modern/app/Repositories/CategoryRepositoryInterface.php`, `CategoryRepository.php` | Task 1 | Task 2 |
| 4 | 3 | `modern/app/Services/CategoryServiceInterface.php`, `CategoryService.php` | Task 2, Task 3 | (sole task in batch) |
| 5 | 4 | `modern/app/Controllers/CategoryController.php` | Task 2, Task 4 | (sole task in batch) |
| 6 | 5 | `modern/routes/api.php` | Task 5 | Task 7 |
| 7 | 5 | `modern/app/Models/Product.php` | Task 1 | Task 6 |

### Developer Tracks

Three tracks fan out from Batch 2 onward; Batch 1 is serialized on the model so all three wait on it before starting.

- **Dev A — Domain/DTO track:** Task 1 (model) → Task 2 (DTOs). Owns the entity's shape and data contracts. After Batch 2 merges, Dev A can pick up Task 7 (the Product import fix) in Batch 5, since it is a one-line model-side change and Dev A already owns the model layer.
- **Dev B — Data/Service track:** Waits for Batch 1, then Task 3 (repository) in Batch 2 → Task 4 (service) in Batch 3. Owns persistence and business logic. This track is the critical path through Batch 3 and gates the controller.
- **Dev C — HTTP/wiring track:** Waits for the service contract, then Task 5 (controller) in Batch 4 → Task 6 (routes) in Batch 5. Owns request/response handling and route registration. Dev C is the last to start but finishes the feature end-to-end.

Early batches are serialized on the model: only after Task 1 merges can the tracks fan out. Dev A and Dev B run in parallel from Batch 2; Dev C joins at Batch 4.

---

## Tasks

### Task 1: Create Category Eloquent model

**Target File(s):** `modern/app/Models/Category.php` (NEW)

**Description:**
Create a new Eloquent model mirroring the existing `Product` model exactly, swapping the entity to Category. Before writing any code, open `modern/app/Models/Product.php` and use it as the structural source of truth — same file shape, same property ordering, same docblock conventions.

The model represents the `categories` table, which has columns `id` (int PK), `name` (string, non-null), and `created_at` (datetime, nullable). It must follow the project's timestamp convention: disable Eloquent's automatic timestamps (`$timestamps = false`) and list `created_at` explicitly in `$fillable` so it can be mass-assigned manually.

The model needs three things beyond the standard Eloquent boilerplate: a `$table` property set to `'categories'`, a `$fillable` array containing `['name', 'created_at']`, and a `$casts` array casting `created_at` to `datetime`. Add `@property` docblocks above the class for IDE autocompletion of `id`, `name`, and `created_at`. Because the model is referenced by `Product::category()` (which currently uses `Category::class` with no import), this file must exist for the Product relationship to resolve.

Finally, define the inverse relationship: a `products()` method returning `$this->hasMany(Product::class, 'category_id')`. This requires a `use App\Models\Product;` statement at the top — note this is the mirror of Product's `category()` `belongsTo`. The full file:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class Category extends Model
{
    protected $table = 'categories';

    public $timestamps = false;

    protected $fillable = ['name', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
```

> Note: the relation return type (`HasMany`) and its import are added beyond the literal Product mirror to satisfy the style-guide rule "type every signature" (`.claude/rules/php-style-guide.md`). Product's untyped `category()` is pre-existing tech debt; do not replicate that gap in new code.

**Acceptance Criteria:**
- [ ] `modern/app/Models/Category.php` exists with `declare(strict_types=1);` as first statement
- [ ] Namespace is `App\Models`; class is `Category extends Model`
- [ ] `$table` = `'categories'`, `$timestamps` = `false`
- [ ] `$fillable` contains exactly `['name', 'created_at']`
- [ ] `$casts` maps `'created_at' => 'datetime'`
- [ ] `@property` docblocks present for `id`, `name`, `created_at`
- [ ] `products()` method returns `HasMany` via `$this->hasMany(Product::class, 'category_id')` with correct `use` statements
- [ ] No public mutable properties; all signatures typed; no `mixed`

**Dependencies:** None

---

### Task 2: Create Category DTO set (4 files)

**Target File(s):** `modern/app/DTO/Category/CategoryDTO.php`, `modern/app/DTO/Category/CreateCategoryDTO.php`, `modern/app/DTO/Category/UpdateCategoryDTO.php`, `modern/app/DTO/Category/CategoryResponseDTO.php` (all NEW)

**Description:**
Create the full Category DTO set mirroring the existing Product DTOs. Before writing, open every file under `modern/app/DTO/Product/` (`ProductDTO.php`, `CreateProductDTO.php`, `UpdateProductDTO.php`, `ProductResponseDTO.php`) and mirror their structure exactly, swapping `Product`→`Category` and adjusting to Category's two columns (`id`, `name`) plus nullable `created_at`. All four DTOs are `readonly class` with constructor property promotion. Every file must begin with `declare(strict_types=1);` and carry full param/return types — no `mixed`, no public mutable properties.

**`CategoryDTO.php`** (base) — readonly class with `use App\Models\Category;`. Three constructor-promoted properties: `public int $id`, `public string $name`, `public ?string $createdAt`. Provide `public static function fromModel(Category $model): self` mapping `id: $model->id`, `name: $model->name`, and `createdAt: $model->created_at?->format('Y-m-d H:i:s')` (note the nullsafe `?->` — `created_at` is nullable). Provide `toArray(): array` returning snake_case keys: `['id' => ..., 'name' => ..., 'created_at' => ...]`.

**`CreateCategoryDTO.php`** — readonly class with NO `use` statement (it never touches the model). Single constructor-promoted property `public string $name`. `toArray(): array` returns `['name' => $this->name]`.

**`UpdateCategoryDTO.php`** — readonly class with NO `use` statement. Single constructor-promoted property `public ?string $name = null` (nullable, defaulted — partial update). `toArray(): array` must filter nulls exactly like `UpdateProductDTO`: `return array_filter(['name' => $this->name], fn ($value) => $value !== null);`.

**`CategoryResponseDTO.php`** — readonly class with `use App\Models\Category;`. Identical shape to the base `CategoryDTO`: props `public int $id`, `public string $name`, `public ?string $createdAt`; `fromModel(Category $model): self` mapping the same three fields (nullsafe date format); `toArray()` returning the same snake_case keys. The difference from Product's `ResponseDTO`: Category is a leaf entity, so `fromModel` takes NO relationship/embedded-entity parameter — just the three scalar fields. Do not invent an embedded relation.

**Acceptance Criteria:**
- [ ] All four files exist under `modern/app/DTO/Category/` with `declare(strict_types=1);`
- [ ] Every class is `readonly class` with constructor property promotion
- [ ] `CategoryDTO` and `CategoryResponseDTO` `use App\Models\Category;` and provide `fromModel(Category $model): self`
- [ ] `CreateCategoryDTO` and `UpdateCategoryDTO` have NO `use` statements
- [ ] Date mapping uses `$model->created_at?->format('Y-m-d H:i:s')` (nullsafe)
- [ ] `UpdateCategoryDTO::toArray()` uses `array_filter(..., fn ($value) => $value !== null)`
- [ ] `toArray()` return type is `array` (or `array<string, mixed>`), keys are snake_case: `id`, `name`, `created_at`
- [ ] No `mixed` typed params; no public mutable properties; `CategoryResponseDTO` has no relationship param

**Dependencies:** Task 1

---

### Task 3: Create Category Repository interface and Eloquent implementation

**Target File(s):** `modern/app/Repositories/CategoryRepositoryInterface.php`, `modern/app/Repositories/CategoryRepository.php` (both NEW)

**Description:**
Create the Category data-access layer: a contract plus its Eloquent implementation, mirroring the Product repository pair exactly. Before writing, open both `modern/app/Repositories/ProductRepositoryInterface.php` and `ProductRepository.php` and mirror their method signatures, constructor shape, and query patterns — swapping `Product`→`Category`. Eloquent queries live ONLY in the repository implementation; the interface declares the contract. Every method needs full param and return types; `declare(strict_types=1);` leads each file.

**`CategoryRepositoryInterface.php`** — interface with two `use` statements: `use App\Models\Category;` and `use Illuminate\Database\Eloquent\Collection;`. Declare the same five methods as the Product interface, with `Category` as the entity type:

```php
public function list(array $filters = []): Collection;
public function findById(int $id): ?Category;
public function create(array $data): Category;
public function update(int $id, array $data): Category;
public function delete(int $id): bool;
```

**`CategoryRepository.php`** — concrete class implementing the interface. Constructor injects the model via readonly promotion: `public function __construct(private Category $model) {}`. Mirror each method from `ProductRepository`:

- `list(array $filters = []): Collection` — builds `$this->model->newQuery()`. Unlike Product (which filters on `name` OR `description`), Category has only a `name` column, so the `search` filter applies to `name` alone: `$query->where('name', 'like', "%{$search}%")` (no `orWhere` — Category has no description). Then `->orderByDesc('created_at')`, `->get()`.
- `findById(int $id): ?Category` — `return $this->model->find($id);`
- `create(array $data): Category` — `return $this->model->create($data);`
- `update(int $id, array $data): Category` — `$category = $this->model->findOrFail($id); $category->fill($data); $category->save(); return $category->fresh();` (bare `->fresh()`, NO eager-load argument — Category is a leaf).
- `delete(int $id): bool` — `$category = $this->model->findOrFail($id); return $category->delete();`

Do NOT add `with('category')`, `fresh(['category'])`, or any relationship eager-loading — that is a Product-specific pattern for its parent lookup; Category has no parent.

**Acceptance Criteria:**
- [ ] Both files exist with `declare(strict_types=1);`
- [ ] Interface named `CategoryRepositoryInterface`, uses `App\Models\Category` + `Illuminate\Database\Eloquent\Collection`, declares all five methods with exact signatures above
- [ ] `CategoryRepository implements CategoryRepositoryInterface`
- [ ] Constructor is `public function __construct(private Category $model) {}` (readonly-promoted model)
- [ ] `list()` applies `search` filter on `name` only (no `orWhere` on description), then `orderByDesc('created_at')`, `->get()`
- [ ] `findById`/`create`/`update`/`delete` mirror Product exactly; `update` uses bare `->fresh()` with no eager-load arg
- [ ] No raw SQL string interpolation; all queries via Eloquent bindings
- [ ] No `mixed` types; no public mutable properties

**Dependencies:** Task 1

---

### Task 4: Create CategoryServiceInterface and CategoryService

**Target File(s):** `modern/app/Services/CategoryServiceInterface.php`, `modern/app/Services/CategoryService.php`

**Description:**
Create two new files that mirror the existing Product service layer exactly. Before writing any code, open `modern/app/Services/ProductServiceInterface.php` and `modern/app/Services/ProductService.php` and reproduce their structure with Category-specific names. The Category entity is a leaf (no relations), but maintain parity with Product — including the reload-via-`findById()` pattern after create/update.

**`CategoryServiceInterface.php`** — declare `declare(strict_types=1);`, namespace `App\Services`. Group use statements per the style guide (framework → app interfaces → app DTOs): `Illuminate\Database\Eloquent\ModelNotFoundException`, then `App\DTO\Category\{CreateCategoryDTO, CategoryResponseDTO, UpdateCategoryDTO}`. Declare five methods mirroring the Product interface signatures, substituting Category types:

```php
public function listCategories(array $filters = []): array;             // array<int, CategoryResponseDTO>
public function getCategory(int $id): CategoryResponseDTO;
public function createCategory(CreateCategoryDTO $dto): CategoryResponseDTO;
public function updateCategory(int $id, UpdateCategoryDTO $dto): CategoryResponseDTO;
public function deleteCategory(int $id): void;
```

**`CategoryService.php`** — `readonly class` in namespace `App\Services`. Use statements grouped (framework → app interfaces → app DTOs): `Illuminate\Database\Eloquent\ModelNotFoundException`, `App\Repositories\CategoryRepositoryInterface`, `App\DTO\Category\{CreateCategoryDTO, CategoryResponseDTO, UpdateCategoryDTO}`. Constructor readonly-promoted injecting the repository interface:

```php
public function __construct(
    private CategoryRepositoryInterface $repository,
) {}
```

Method bodies mirror `ProductService` line-for-line with Category names and the exception message pattern `"Category [{$id}] not found."`:
- `listCategories()`: `return array_map(fn ($c) => CategoryResponseDTO::fromModel($c), iterator_to_array($this->repository->list($filters)));`
- `getCategory()`: null-check from `findById($id)` → throw `ModelNotFoundException("Category [{$id}] not found.");` → `return CategoryResponseDTO::fromModel($category);`
- `createCategory()`: `create($dto->toArray())`, then reload via `findById($category->id)`, then `CategoryResponseDTO::fromModel($category)`.
- `updateCategory()`: `update($id, $dto->toArray())`, reload via `findById($category->id)`, then `CategoryResponseDTO::fromModel($category)`.
- `deleteCategory()`: `if (! $this->repository->delete($id)) { throw new ModelNotFoundException("Category [{$id}] not found."); }`

Full param and return types on every signature; no `mixed`; no public mutable properties.

**Acceptance Criteria:**
- [ ] `CategoryServiceInterface.php` exists with `declare(strict_types=1);`, namespace `App\Services`, and all 5 methods with exact signatures above
- [ ] `CategoryService.php` is a `readonly class` with readonly-promoted constructor injecting `CategoryRepositoryInterface`
- [ ] Exception messages use exact pattern `"Category [{$id}] not found."` in both `getCategory` and `deleteCategory`
- [ ] `createCategory` and `updateCategory` reload via `findById()` before returning `CategoryResponseDTO::fromModel()`
- [ ] Use statements grouped: framework first, then app interfaces, then app DTOs; no unused imports
- [ ] Every method has explicit param and return types; no `mixed` anywhere

**Dependencies:** Task 2, Task 3

---

### Task 5: Create CategoryController

**Target File(s):** `modern/app/Controllers/CategoryController.php`

**Description:**
Create a new controller that mirrors `modern/app/Controllers/ProductController.php` exactly. Open that file first and reproduce its structure with Category names. The controller is a `readonly class` in namespace `App\Controllers` with `declare(strict_types=1);` as the first statement.

Use statements, grouped (framework → app interfaces → app DTOs): `Illuminate\Database\Eloquent\ModelNotFoundException`, `Illuminate\Http\Request`, `App\Services\CategoryServiceInterface`, `App\DTO\Category\{CreateCategoryDTO, UpdateCategoryDTO}`. Constructor readonly-promoted injecting the service interface:

```php
public function __construct(
    private CategoryServiceInterface $service,
) {}
```

Implement five CRUD methods. HTTP status codes and response-wrapping conventions are critical and differ by method — follow ProductController precisely:

- `index(Request $request)` — build filters array (e.g. `['search' => $request->query('search')]`), call `listCategories`, wrap result: `return response()->json(['data' => array_map(fn ($dto) => $dto->toArray(), $categories)])`. Status 200 (default). The `['data' => ...]` wrapper appears ONLY here.
- `show(int $id)` — try `getCategory` → `return response()->json($category->toArray())` (200, NOT wrapped); catch `ModelNotFoundException` → `return response()->json(['error' => 'Category not found'], 404)`.
- `store(Request $request)` — validate with rules `['name' => 'required|string|max:255']`; build `new CreateCategoryDTO(...)` from validated data; `return response()->json($category->toArray(), 201)` (201 Created, NOT wrapped).
- `update(int $id, Request $request)` — validate with rules `['name' => 'sometimes|string|max:255']` (NO `exists:` rule — Category owns its own rows, so existence is enforced at the service/repository layer, not via a DB lookup in validation); build `new UpdateCategoryDTO(...)`; `return response()->json($category->toArray())` (200, NOT wrapped); catch `ModelNotFoundException` → 404.
- `destroy(int $id)` — `return response()->json(null, 204)` (204 No Content); catch `ModelNotFoundException` → `return response()->json(['error' => 'Category not found'], 404)`.

All 404 responses use the exact body `['error' => 'Category not found']` with status 404. Keep controllers thin — validation, DTO construction, service call, JSON response, and exception-to-404 translation only. No business or query logic.

**Acceptance Criteria:**
- [ ] `CategoryController.php` is a `readonly class` with `declare(strict_types=1);` and namespace `App\Controllers`
- [ ] Constructor is readonly-promoted and injects `CategoryServiceInterface`
- [ ] `index` wraps response in `['data' => ...]`; `show`, `store`, `update` return the DTO array directly (not wrapped)
- [ ] `store` returns HTTP 201; `destroy` returns HTTP 204; `show`/`index`/`update` return 200 (default)
- [ ] All three lookup methods (`show`, `update`, `destroy`) catch `ModelNotFoundException` and return `['error' => 'Category not found']` with status 404
- [ ] `store` validation rules are `['name' => 'required|string|max:255']`; `update` rules are `['name' => 'sometimes|string|max:255']` with no `exists:` rule
- [ ] Use statements grouped: framework first, then app interfaces, then app DTOs; controller contains no business or query logic

**Dependencies:** Task 2, Task 4

---

### Task 6: Register Category CRUD routes

**Target File(s):** `modern/routes/api.php`

**Description:**
The Category entity's controller (`CategoryController`) is wired into the HTTP layer in the existing route file. The file currently registers only `ProductController` inside a single `Route::prefix('api')` group (lines 1-10). Two changes are required.

First, add the `CategoryController` import to the top use-block alongside the existing `ProductController` import:

```php
use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
```

Second, append five Category routes **inside the existing** `Route::prefix('api')` group body — do NOT create a second group or a nested `prefix('categories')` closure, since that would alter the structure shared with the Product routes. Append after the `products/{id}` DELETE line:

```php
Route::prefix('api')->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
});
```

No other lines in the file change. The `{id}` route parameter mirrors the Product conventions; Laravel's implicit model binding is not used here (controllers accept the raw `int $id`), matching the existing Product route style.

**Acceptance Criteria:**
- [ ] `use App\Controllers\CategoryController;` added at the top, ordered before `ProductController` (alphabetical) or grouped with it
- [ ] All five Category routes present inside the existing `Route::prefix('api')` group
- [ ] No duplicate `Route::prefix('api')` group or nested Category-specific group created
- [ ] `php artisan route:list` shows `/api/categories`, `/api/categories/{id}` for GET/POST/PUT/DELETE
- [ ] No trailing whitespace or formatting deviations from existing style

**Dependencies:** Task 5

---

### Task 7: Add missing Category import to Product model

**Target File(s):** `modern/app/Models/Product.php`

**Description:**
The `Product` model (line ~7) currently imports only `use Illuminate\Database\Eloquent\Model;`. Its `category()` relationship method (lines 40-43) references `Category::class` without importing it, which leaves a broken/unresolvable class reference at static-analysis and runtime:

```php
public function category()
{
    return $this->belongsTo(Category::class, 'category_id');
}
```

Add a single import to the use-block near the top of the file:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
```

No other change is permitted. The relationship method body, `$fillable`, `$casts`, `$table`, and all other lines remain untouched. The fix only makes the existing `Category::class` reference resolvable; the relation logic was already correct.

**Acceptance Criteria:**
- [ ] `use App\Models\Category;` present in the use-block of `Product.php`
- [ ] Import ordered per PSR-12 conventions (alphabetical within same root, `App\*` before `Illuminate\*`)
- [ ] The `category()` method body unchanged
- [ ] No other lines modified (diff is exactly one added line)
- [ ] `php -l Product.php` passes and `Category::class` resolves in static analysis

**Dependencies:** Task 1
