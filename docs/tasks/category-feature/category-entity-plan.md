# Plan: Add Category Entity (Layered Stack) + Wire Product Relationship

**Project:** SOD (subagent-orchestration-demo)
**Date:** 2026-06-13
**Slug:** `category-entity`
**Scope:** One new entity + one existing-file wiring change. Mirrors the existing `Product` layer exactly.

---

## 1. Overview

The `modern/` codebase is a partial Laravel skeleton implementing a strict layered
architecture (Model → DTO → Repository → Service → Controller → Route) for a single
entity, **Product**.

Today the `Product` model already declares a relationship to a `Category` class that
does not exist:

```php
// modern/app/Models/Product.php:40-43
public function category()
{
    return $this->belongsTo(Category::class, 'category_id'); // ← Category undefined
}
```

- No `use App\Models\Category;` import is present.
- No `Category` model, DTO, repository, service, controller, or route exists.
- The legacy source of truth (`legacy/products.php`) confirms the `categories` table
  and its columns (`id`, `name`) exist and are already referenced by the modern code:
  - `legacy/products.php:60` — `LEFT JOIN categories c ON p.category_id = c.id`
  - `legacy/products.php:58` — `c.name as category_name`
  - `modern/app/Controllers/ProductController.php:49,73` — `exists:categories,id` validation
  - `modern/app/DTO/Product/ProductResponseDTO.php:27-33` — reads `category->id`, `category->name`

**Goal:** Build the full `Category` layered stack (mirroring `Product`) and fix the
broken `Product → Category` reference, delivering a complete, working
`/api/categories` CRUD surface plus a resolved product-category relationship.

---

## 2. Why This Matters

- **Closes a real bug** — the `Product::category()` relation currently references a
  non-existent class.
- **Mirrors an existing, proven pattern** — every file in the Category stack has a
  1:1 Product counterpart to copy structure from. Low risk, high clarity.
- **Simple scope** — Category is a leaf entity: no foreign keys of its own, no nested
  relations to load. Even simpler than Product. Ideal for a demo.

---

## 3. Scope

### In scope
- New `Category` Eloquent model.
- Four Category DTOs (base, create, update, response).
- Category repository interface + Eloquent implementation.
- Category service interface + implementation.
- Category controller (full CRUD).
- Category routes registered in `modern/routes/api.php`.
- Fix `modern/app/Models/Product.php` — add the missing `use App\Models\Category;`.
- Add reverse relation `Category::products()` (hasMany) for symmetry.

### Out of scope (intentionally)
- **Migrations.** The project contains zero migrations — `Product` assumes the
  `products` table already exists in the database. `Category` follows the same
  assumption for `categories`. Adding a migration would break the established pattern.
- **composer.json / config / bootstrap / .env.** The Laravel skeleton is partial;
  these do not exist. Not introduced by this change.
- **Tests.** No tests exist for `Product`. To keep parity and scope tight, tests are
  not added here (can be a follow-up plan).
- **Changes to Product DTOs / Repository / Service / Controller.** The Product layer
  already correctly reads category data via `with('category')` and
  `ProductResponseDTO`. No Product-layer code changes are needed beyond the model
  import fix.

---

## 4. Assumptions & Constraints

| # | Assumption | Evidence |
|---|------------|----------|
| A1 | The `categories` table exists with at least `id` (PK) and `name` columns. | `legacy/products.php:58-60` JOIN + select |
| A2 | `categories` follows the same timestamp convention as `products` — `created_at` present, `updated_at` absent (`$timestamps = false`). | `Product.php:22` |
| A3 | PSR-4 maps `App\` → `modern/app/`. Files placed under `modern/app/...` resolve correctly. | Existing Product stack uses this layout |
| A4 | PHP 8.4, Laravel 11+, `declare(strict_types=1)` mandatory in every file. | `.claude/rules/php-development-rules.md`, `persona.md` |

**Constraint:** Every new file must follow the project style guide (PSR-12 + PER,
readonly DTOs, constructor property promotion, interface-injected deps, parameterized
queries only). See `.claude/rules/php-style-guide.md`.

---

## 5. Category Schema (inferred)

Single source of truth: legacy usage + existing modern references.

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | int (PK) | no | Auto-increment |
| `name` | string | no | Category name; `legacy/products.php:58` aliases as `category_name` |
| `created_at` | datetime | yes | Mirrors Product convention |

No other columns are referenced anywhere in the codebase. Scope deliberately excludes
speculative fields (e.g. `slug`, `description`) to stay minimal and faithful.

---

## 6. Detailed File Specs

Each file mirrors its `Product` counterpart. Path, responsibility, and exact shape below.

### 6.1 Model — `modern/app/Models/Category.php` (NEW)

- Namespace: `App\Models`
- `use Illuminate\Database\Eloquent\Model;`
- `class Category extends Model`
- `$table = 'categories';`
- `public $timestamps = false;`  *(mirrors Product.php:22)*
- `$fillable = ['name', 'created_at'];`
- `$casts = ['created_at' => 'datetime'];`
- `@property` docblocks for `id`, `name`, `created_at`
- **Relation:** `products()` → `return $this->hasMany(Product::class, 'category_id');`
  - Requires `use App\Models\Product;`

### 6.2 DTOs — `modern/app/DTO/Category/*.php` (4 NEW files)

Mirror `modern/app/DTO/Product/` exactly. All `readonly class`.

#### 6.2.1 `CategoryDTO.php`
- Namespace `App\DTO\Category`, `use App\Models\Category;`
- Properties: `public int $id`, `public string $name`, `public ?string $createdAt`
- `fromModel(Category $model): self` — maps `id`, `name`, `createdAt` formatted `'Y-m-d H:i:s'`
- `toArray(): array` — keys `id`, `name`, `created_at`

#### 6.2.2 `CreateCategoryDTO.php`
- Namespace `App\DTO\Category`, no use
- Properties: `public string $name`
- `toArray(): array` — `['name' => $this->name]`

#### 6.2.3 `UpdateCategoryDTO.php`
- Namespace `App\DTO\Category`, no use
- Properties: `public ?string $name = null`
- `toArray(): array` — `array_filter(['name' => $this->name], fn ($v) => $v !== null)`

#### 6.2.4 `CategoryResponseDTO.php`
- Namespace `App\DTO\Category`, `use App\Models\Category;`
- Properties: `public int $id`, `public string $name`, `public ?string $createdAt`
- `fromModel(Category $model): self` — `id`, `name`, `createdAt` formatted
- `toArray(): array` — keys `id`, `name`, `created_at`
- *(No nested relation param — Category is a leaf. Simpler than ProductResponseDTO.)*

### 6.3 Repository — `modern/app/Repositories/CategoryRepositoryInterface.php` + `CategoryRepository.php` (NEW)

Mirror `ProductRepository(Interface)`.

**Interface** (`App\Repositories`, `use App\Models\Category`, `use Illuminate\Database\Eloquent\Collection`):
- `list(array $filters = []): Collection`
- `findById(int $id): ?Category`
- `create(array $data): Category`
- `update(int $id, array $data): Category`
- `delete(int $id): bool`

**Implementation** (`App\Repositories`):
- Constructor: `private Category $model` (readonly promoted), injected via container.
- `list()` — `newQuery()`; optional `search` filter on `name` (`like`); `orderByDesc('created_at')`; `get()`.
- `findById()` — `$this->model->find($id);`
- `create()` — `$this->model->create($data);`
- `update()` — `findOrFail($id)`, `fill($data)`, `save()`, `return $product->fresh();`
- `delete()` — `findOrFail($id)`, `return ...->delete();`

### 6.4 Service — `modern/app/Services/CategoryServiceInterface.php` + `CategoryService.php` (NEW)

Mirror `ProductService(Interface)`.

**Interface** (`App\Services`, DTO + `ModelNotFoundException` uses):
- `listCategories(array $filters = []): array`  *(returns `array<int, CategoryResponseDTO>`)*
- `getCategory(int $id): CategoryResponseDTO` *(throws `ModelNotFoundException`)*
- `createCategory(CreateCategoryDTO $dto): CategoryResponseDTO`
- `updateCategory(int $id, UpdateCategoryDTO $dto): CategoryResponseDTO` *(throws)*
- `deleteCategory(int $id): void` *(throws)*

**Implementation** (`App\Services`):
- Constructor: `private CategoryRepositoryInterface $repository` (readonly promoted).
- Each method maps to a repository call, converts via `CategoryResponseDTO::fromModel()`.
- `getCategory` / `deleteCategory` throw `ModelNotFoundException("Category [{$id}] not found.")` on null/false.

### 6.5 Controller — `modern/app/Controllers/CategoryController.php` (NEW)

Mirror `ProductController` (`App\Controllers`).

- Constructor: `private CategoryServiceInterface $service` (readonly promoted).
- `index(Request $request)` — filters from query (`search`); `response()->json(['data' => ...])`.
- `show(int $id)` — try `getCategory` → json; catch `ModelNotFoundException` → 404.
- `store(Request $request)` — validate `['name' => 'required|string|max:255']`; build
  `CreateCategoryDTO`; call `createCategory`; return json **201**.
- `update(int $id, Request $request)` — validate `['name' => 'sometimes|string|max:255']`;
  build `UpdateCategoryDTO`; call `updateCategory`; json; 404 on not found.
- `destroy(int $id)` — call `deleteCategory`; return **204**; 404 on not found.

**Note:** No `exists:` validation rule needed on Category (it owns its own rows).
Simpler than Product's `category_id` validation.

### 6.6 Routes — `modern/routes/api.php` (MODIFY)

Add a `categories` resource group inside the existing `api` prefix, mirroring products:

```php
Route::prefix('api')->group(function () {
    // ... existing product routes ...

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
});
```

Add `use App\Controllers\CategoryController;` at top.

### 6.7 Wiring fix — `modern/app/Models/Product.php` (MODIFY)

Add the missing import so the existing `category()` relation resolves:

```php
use App\Models\Category;
```

No other change to `Product.php` — the relation body at lines 40-43 is already correct;
it only lacked the import.

---

## 7. REST Endpoints (result)

| Method | Path | Controller@method | Success |
|--------|------|-------------------|---------|
| GET | `/api/categories` | `CategoryController@index` | 200 `{data:[...]}` |
| GET | `/api/categories/{id}` | `CategoryController@show` | 200 / 404 |
| POST | `/api/categories` | `CategoryController@store` | 201 |
| PUT | `/api/categories/{id}` | `CategoryController@update` | 200 / 404 |
| DELETE | `/api/categories/{id}` | `CategoryController@destroy` | 204 / 404 |

Bonus (already works once Category exists): `GET /api/products` and
`GET /api/products/{id}` return the nested `category: {id, name}` object via
`ProductResponseDTO`, because `ProductRepository` already eager-loads `with('category')`.

---

## 8. Acceptance Criteria

- [ ] `modern/app/Models/Category.php` exists; `$table='categories'`, `$timestamps=false`, `products()` hasMany relation.
- [ ] All four Category DTOs exist under `modern/app/DTO/Category/`, each `readonly class` with correct properties, `fromModel()` (where applicable), and `toArray()`.
- [ ] `CategoryRepositoryInterface` + `CategoryRepository` exist; all five methods present; constructor injects `Category` model.
- [ ] `CategoryServiceInterface` + `CategoryService` exist; all five methods present; constructor injects `CategoryRepositoryInterface`.
- [ ] `CategoryController` exists; five CRUD methods; correct HTTP status codes (200/201/204/404).
- [ ] `/api/categories` CRUD routes registered in `modern/routes/api.php`.
- [ ] `modern/app/Models/Product.php` has `use App\Models\Category;` import.
- [ ] Every new PHP file begins with `declare(strict_types=1);`.
- [ ] Every new PHP file has full param + return type declarations.
- [ ] No `mixed` types, no string-concatenated SQL, no public mutable properties (DTOs readonly).
- [ ] Dependency direction respected: Controller → Service (interface) → Repository (interface) → Model.

---

## 9. Dependencies & Order

The Category stack is self-contained and can be built bottom-up, each layer depending
only on the one beneath it:

```
Model ──▶ DTO ──▶ Repository ──▶ Service ──▶ Controller ──▶ Route
 (6.1)     (6.2)    (6.3)         (6.4)       (6.5)         (6.6)
```

- The Product wiring fix (6.7) depends only on the Category **Model** (6.1) existing.
- Layers 6.2–6.5 are each independent of the others within the same layer (one developer
  can own a vertical slice), but each requires its lower layer to compile.

---

## 10. Risks / Open Questions

| # | Risk / Question | Mitigation |
|---|-----------------|------------|
| R1 | `categories` table shape unverified (no migration exists). Columns inferred from legacy + DTO usage only. | A1 holds per legacy source. If DB differs, only Model `$fillable`/`$casts` need adjustment. |
| R2 | No runnable Laravel bootstrap (no composer.json/config/.env). Code cannot be executed locally without scaffolding. | Out of scope. This plan delivers correct, layered source files only. |
| R3 | Should `Category` support `description`/`slug`? | No — not referenced anywhere. Deliberately omitted to stay minimal. |

---

## 11. Files Touched Summary

| Action | Path | Section |
|--------|------|---------|
| NEW | `modern/app/Models/Category.php` | 6.1 |
| NEW | `modern/app/DTO/Category/CategoryDTO.php` | 6.2.1 |
| NEW | `modern/app/DTO/Category/CreateCategoryDTO.php` | 6.2.2 |
| NEW | `modern/app/DTO/Category/UpdateCategoryDTO.php` | 6.2.3 |
| NEW | `modern/app/DTO/Category/CategoryResponseDTO.php` | 6.2.4 |
| NEW | `modern/app/Repositories/CategoryRepositoryInterface.php` | 6.3 |
| NEW | `modern/app/Repositories/CategoryRepository.php` | 6.3 |
| NEW | `modern/app/Services/CategoryServiceInterface.php` | 6.4 |
| NEW | `modern/app/Services/CategoryService.php` | 6.4 |
| NEW | `modern/app/Controllers/CategoryController.php` | 6.5 |
| MODIFY | `modern/routes/api.php` | 6.6 |
| MODIFY | `modern/app/Models/Product.php` | 6.7 |

**10 new files, 2 modified.** No deletions.
