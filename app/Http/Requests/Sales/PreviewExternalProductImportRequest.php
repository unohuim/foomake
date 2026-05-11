<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validate external product preview requests.
 */
class PreviewExternalProductImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
                Rule::in(['woocommerce', 'shopify', 'file-upload']),
            ],
            'rows' => [
                'nullable',
                'array',
            ],
            'rows.*.external_id' => [
                'required_if:source,file-upload',
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.external_source' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.name' => [
                'required_if:source,file-upload',
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.sku' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.default_price_cents' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'rows.*.image_url' => [
                'nullable',
                'string',
                'url',
                'max:2048',
            ],
            'rows.*.base_uom_id' => [
                'nullable',
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
