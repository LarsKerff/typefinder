# TypeFinder _(Beta)_

⚠️ **Beta version**  
This package is currently in **beta**.

---

## What is TypeFinder?

**TypeFinder** generates **type-safe TypeScript types** directly from your **Laravel API Resources**.

It inspects real resource output by running your migrations (in a sandbox) and resolving your resources, ensuring the generated TypeScript types **match reality**, not assumptions.

---

## What does it do?

TypeFinder maps **Laravel API Resources → TypeScript types**, including:

- Nested resources
- Resource collections
- Enums
- Nullable fields
- Optional fields
- Proper imports between generated types

---

## How it works

In short, TypeFinder:

1. **Creates a temporary SQLite sandbox database**
2. **Runs your migrations**
3. **Seeds all tables**
   - One fully-filled row
   - One row with nullable fields set to `null`
4. **Loads relations** defined on the discovered models
5. **Resolves API Resources**
6. **Infers types from real output**
7. **Generates a TypeScript file for each Resource**
8. **Generates an `index.ts` barrel export**

---

## How to run it

```bash
php artisan typefinder:generate
```

## Example

### Laravel API Resource

```php
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Status;

class CourseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => Status::ACTIVE,
            'students' => StudentResource::collection($this->students),
            'description' => $this->description,
            'published_at' => $this->published_at,
            'archived_at' => $this->archived_at,
        ];
    }
}
```

### Generated TypeScript outcome

```ts
import type { Student } from './Student';

export type Course = {
    id: number;
    title: string;
    status: 'active' | 'inactive';
    students: Student[];
    description?: string;
    published_at: string;
    archived_at: string | null;
};
```