<?php

namespace App\Http\Requests\Sales;

use App\Models\Item;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\Validation\Rule;

/**
 * Validate external product import requests.
 */
class ImportExternalProductsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('inventory-products-manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => [
                Rule::requiredIf(! $this->boolean('is_local_file_import')),
                'nullable',
                'string',
                Rule::in(['woocommerce', 'shopify']),
            ],
            'is_local_file_import' => [
                'nullable',
                'boolean',
            ],
            'create_fulfillment_recipes' => [
                'nullable',
                'boolean',
            ],
            'import_all_as_manufacturable' => [
                'nullable',
                'boolean',
            ],
            'import_all_as_purchasable' => [
                'nullable',
                'boolean',
            ],
            'bulk_base_uom_id' => [
                'nullable',
                'integer',
                Rule::exists('uoms', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'rows' => [
                'required',
                'array',
                'min:1',
            ],
            'rows.*.external_id' => [
                'required',
                'string',
                'max:255',
                'distinct',
            ],
            'rows.*.name' => [
                'required',
                'string',
                'max:255',
            ],
            'rows.*.sku' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.external_source' => [
                'nullable',
                'string',
                Rule::in(['woocommerce', 'shopify']),
            ],
            'rows.*.base_uom_id' => [
                'nullable',
                'integer',
                Rule::exists('uoms', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'rows.*.is_active' => [
                'nullable',
                'boolean',
            ],
            'rows.*.is_sellable' => [
                'nullable',
                'boolean',
            ],
            'rows.*.is_manufacturable' => [
                'nullable',
                'boolean',
            ],
            'rows.*.is_purchasable' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Configure cross-field validation for bulk/default import behavior.
     */
    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $rows = $this->input('rows', []);
            $bulkBaseUomId = $this->input('bulk_base_uom_id');

            if (! is_array($rows)) {
                return;
            }

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $externalSource = $this->resolvedRowExternalSource($row);
                $externalId = $this->normalizedExternalId($row['external_id'] ?? null);

                if (
                    ! $this->boolean('is_local_file_import')
                    && array_key_exists('external_source', $row)
                    && ($this->input('source') === null || $this->input('source') === '')
                ) {
                    $validator->errors()->add(
                        'source',
                        'The ecommerce store field is required.'
                    );
                }

                if (
                    ! $this->boolean('is_local_file_import')
                    && array_key_exists('external_source', $row)
                    && $row['external_source'] !== null
                    && $row['external_source'] !== ''
                    && $this->normalizedExternalSource($row['external_source']) !== $this->normalizedExternalSource($this->input('source'))
                ) {
                    $validator->errors()->add(
                        "rows.{$index}.external_source",
                        'The selected row source must match the selected ecommerce store.'
                    );
                }

                if (
                    $this->boolean('is_local_file_import')
                    && $externalSource !== null
                    && $externalId !== null
                    && Item::query()
                        ->where('tenant_id', $this->user()?->tenant_id)
                        ->whereRaw('LOWER(TRIM(external_source)) = ?', [$externalSource])
                        ->whereRaw('TRIM(external_id) = ?', [$externalId])
                        ->exists()
                ) {
                    $validator->errors()->add(
                        "rows.{$index}.external_id",
                        'A product with the same external source and external ID already exists.'
                    );
                }

                $hasRowBaseUom = array_key_exists('base_uom_id', $row) && $row['base_uom_id'] !== null && $row['base_uom_id'] !== '';

                if (! $hasRowBaseUom && ($bulkBaseUomId === null || $bulkBaseUomId === '')) {
                    $validator->errors()->add(
                        "rows.{$index}.base_uom_id",
                        'A base unit of measure is required for each imported row unless a bulk base unit of measure is selected.'
                    );
                }
            }
        });
    }

    /**
     * Return stable JSON validation errors for AJAX consumers.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * Resolve the normalized external source for a submitted import row.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolvedRowExternalSource(array $row): ?string
    {
        $rowSource = $this->normalizedExternalSource($row['external_source'] ?? null);

        if ($rowSource !== null) {
            return $rowSource;
        }

        return $this->normalizedExternalSource($this->input('source'));
    }

    /**
     * Normalize an external source value for duplicate checks.
     */
    private function normalizedExternalSource(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize an external ID value for duplicate checks.
     */
    private function normalizedExternalId(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
