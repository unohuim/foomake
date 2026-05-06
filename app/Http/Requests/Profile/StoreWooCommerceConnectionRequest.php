<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validate WooCommerce connector credential updates.
 */
class StoreWooCommerceConnectionRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    /**
     * Determine if the current user may manage connector credentials.
     */
    public function authorize(): bool
    {
        return Gate::allows('system-users-manage');
    }

    /**
     * Prepare the route source for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => (string) $this->route('source', 'woocommerce'),
        ]);
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => [
                'required',
                'string',
                Rule::in(['woocommerce']),
            ],
            'store_url' => [
                'required',
                'url',
                'max:255',
                'starts_with:http://,https://',
            ],
            'consumer_key' => [
                'required',
                'string',
                'max:255',
            ],
            'consumer_secret' => [
                'required',
                'string',
                'max:255',
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
