<?php
namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CustomFieldService
{
    public function definitions(string $entity): Collection
    {
        return CustomFieldDefinition::where('entity', $entity)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function values(string $entity, int $entityId): array
    {
        return CustomFieldValue::where('entity', $entity)
            ->where('entity_id', $entityId)
            ->pluck('value', 'key')
            ->all();
    }

    public function visibility(string $entity, int $entityId): array
    {
        if (!Schema::hasColumn('custom_field_values', 'show_in_info')) {
            return [];
        }
        return CustomFieldValue::where('entity', $entity)
            ->where('entity_id', $entityId)
            ->pluck('show_in_info', 'key')
            ->map(fn ($v) => (bool) $v)
            ->all();
    }

    public function save(string $entity, int $entityId, array $input): void
    {
        $defs = $this->definitions($entity);
        $visibleKeys = (array) ($input['cf_visible'] ?? []);
        foreach ($defs as $def) {
            $key = 'cf_'.$def->key;
            if (!array_key_exists($key, $input) && !$def->is_required) {
                continue;
            }
            $val = $input[$key] ?? null;
            if ($def->is_required && ($val === null || $val === '')) {
                continue; // validation should catch
            }
            CustomFieldValue::updateOrCreate(
                ['entity' => $entity, 'entity_id' => $entityId, 'key' => $def->key],
                Schema::hasColumn('custom_field_values', 'show_in_info')
                    ? ['value' => is_array($val) ? json_encode($val) : $val, 'show_in_info' => in_array($def->key, $visibleKeys, true)]
                    : ['value' => is_array($val) ? json_encode($val) : $val]
            );
        }
    }

    public function validationRules(string $entity): array
    {
        $rules = [];
        foreach ($this->definitions($entity) as $def) {
            $r = [];
            if ($def->is_required) $r[] = 'required';
            else $r[] = 'nullable';
            if ($def->field_type === 'number') $r[] = 'numeric';
            if ($def->field_type === 'date') $r[] = 'date';
            if ($def->field_type === 'select' && is_array($def->options)) {
                $r[] = 'in:'.implode(',', $def->options);
            }
            $rules['cf_'.$def->key] = $r;
        }
        return $rules;
    }
}
