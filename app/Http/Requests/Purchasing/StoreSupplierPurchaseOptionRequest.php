<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validate requests to create supplier purchase options.
 */
class StoreSupplierPurchaseOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('purchasing-suppliers-manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'pack_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\\d{1,12}(?:\\.\\d{1,6})?$/',
            ],
            'pack_uom_id' => [
                'required',
                'integer',
                Rule::exists('uoms', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        $normalized = $this->normalizeErrors($errors);

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $normalized,
        ], 422));
    }

    /**
     * Ensure required keys exist and are arrays.
     *
     * @param array<string, array<int, string>> $errors
     */
    private function normalizeErrors(array $errors): array
    {
        foreach (['item_id', 'pack_quantity', 'pack_uom_id', 'supplier_sku'] as $field) {
            if (!array_key_exists($field, $errors) || !is_array($errors[$field])) {
                $errors[$field] = [];
            }
        }

        return $errors;
    }
}
