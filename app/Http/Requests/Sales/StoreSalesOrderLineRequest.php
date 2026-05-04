<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validate sales order line create requests.
 */
class StoreSalesOrderLineRequest extends FormRequest
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
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|Rule>>
     */
    public function rules(): array
    {
        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->where('is_sellable', true),
            ],
            'quantity' => [
                'required',
                'string',
                'regex:/^\d{1,12}(?:\.\d{1,6})?$/',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $quantity = $this->input('quantity');

            if (! is_string($quantity) || $quantity === '') {
                return;
            }

            if (! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $quantity)) {
                return;
            }

            if (bccomp($quantity, '0', 6) <= 0) {
                $validator->errors()->add('quantity', 'The quantity must be greater than 0.');
            }
        });
    }

    /**
     * Return stable JSON validation errors for AJAX consumers.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'item_id' => $errors['item_id'] ?? [],
                'quantity' => $errors['quantity'] ?? [],
            ],
        ], 422));
    }
}
