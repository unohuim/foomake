<?php

namespace App\Http\Requests\Sales;

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
                'required',
                'string',
                Rule::in(['woocommerce', 'shopify']),
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
            'rows.*.base_uom_id' => [
                'nullable',
                'integer',
                Rule::exists('uoms', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'rows.*.is_active' => [
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
}
