---
description: "Generates all DTO classes from dtos.yaml."
model: claude-sonnet-4-6
---

You generate the DTO classes. Read `output/specs/dtos.yaml`.

## Rules

- `readonly class` for every DTO, constructor property promotion
- `declare(strict_types=1);`, namespace `App\DTO\{Entity}`
- Base DTO (`ProductDTO`): all base fields; `static fromModel(Model $model): self` factory; `toArray(): array`
- Create DTO: only `constructor_params`, all required ones non-nullable; `toArray(): array`
- Update DTO: all params nullable with `= null` defaults; `toArray(): array` uses `array_filter` to drop nulls
- Response DTO: all response fields; `fromModel()` that handles `includes_relationship` (eager-loaded relation → nested array or null); `toArray(): array`
- Use exact field names and types from the spec

Output each DTO to `modern/app/DTO/{Entity}/{ClassName}.php`. Output ONLY file contents.
