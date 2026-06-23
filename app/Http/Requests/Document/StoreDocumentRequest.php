<?php

namespace App\Http\Requests\Document;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Store;
use App\Support\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('category') && ! $this->filled('document_type')) {
            $this->merge(['document_type' => $this->input('category')]);
        }

        if ($this->has('tags')) {
            $this->merge(['tags' => $this->normalizeTags($this->input('tags'))]);
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeTags(mixed $tags): ?array
    {
        if ($tags === null || $tags === '') {
            return null;
        }

        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $tags = $decoded;
            } else {
                $tags = array_map('trim', explode(',', $tags));
            }
        }

        if (! is_array($tags)) {
            return null;
        }

        $normalized = array_values(array_filter(
            array_map(static fn ($tag) => is_string($tag) ? trim($tag) : '', $tags),
            static fn ($tag) => $tag !== ''
        ));

        return $normalized === [] ? null : $normalized;
    }

    public function rules(): array
    {
        $types = implode(',', DocumentRules::DOCUMENT_TYPES);
        $entities = implode(',', DocumentRules::ENTITY_TYPES);
        $statuses = implode(',', DocumentRules::UPLOAD_STATUSES);

        return [
            'file'          => DocumentRules::fileValidationRule(),
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'category'      => 'sometimes|in:' . $types,
            'document_type' => 'required|in:' . $types,
            'tags'          => 'nullable|array',
            'tags.*'        => 'string|max:50',
            'entity_type'   => 'required|in:' . $entities,
            'entity_id'     => 'required|integer|min:1',
            'status'        => 'nullable|in:' . $statuses,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $entityType = $this->input('entity_type');
            $entityId   = (int) $this->input('entity_id');

            $exists = match ($entityType) {
                'merchant' => Merchant::whereKey($entityId)->exists(),
                'store'    => Store::whereKey($entityId)->exists(),
                'customer' => Customer::whereKey($entityId)->exists(),
                default    => false,
            };

            if (! $exists) {
                $validator->errors()->add('entity_id', "The selected {$entityType} does not exist.");
            }
        });
    }

    public function documentType(): string
    {
        return $this->input('document_type') ?? $this->input('category');
    }
}
