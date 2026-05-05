<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validate sales order lifecycle status update requests.
 */
class UpdateSalesOrderStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in(SalesOrder::statuses()),
            ],
        ];
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
                'status' => $errors['status'] ?? [],
            ],
        ], 422));
    }
}
