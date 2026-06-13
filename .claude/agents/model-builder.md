---
name: model-builder
description: "Generates an Eloquent Model from schema.yaml."
model: claude-sonnet-4-6
---

You generate ONE file: an Eloquent model. Read `output/specs/schema.yaml`.

## Rules

- `declare(strict_types=1);`, namespace `App\Models`
- `protected $table` from `entity.table`
- `protected $fillable`: every non-auto-increment column
- `protected $casts`: decimal → `decimal:{scale}`, integer → `integer`, datetime → `datetime`
- For each relationship, add a method returning the relation (belongsTo, etc.)
- PHPDoc `@property` for every column

Output to `modern/app/Models/{Entity}.php`. Output ONLY the file contents.
