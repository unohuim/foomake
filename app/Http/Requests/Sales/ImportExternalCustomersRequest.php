<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validate external customer import requests.
 */
class ImportExternalCustomersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('system-users-manage');
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
            'rows.*.email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'rows.*.phone' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.city' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.region' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.postal_code' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.country_code' => [
                'nullable',
                'string',
                'size:2',
                'alpha:ascii',
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
