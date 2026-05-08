<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * Validate Sales Products create requests.
 */
class StoreSalesProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
            'default_price_amount' => ['nullable', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'default_price_currency_code' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
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
