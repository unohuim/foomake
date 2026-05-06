<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
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
                'required',
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
