<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Validate sales order create requests.
 */
class StoreSalesOrderRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_contacts', 'id')->where('tenant_id', $this->user()?->tenant_id),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $contactId = $this->input('contact_id');
            $customerId = $this->input('customer_id');

            if ($contactId === null || $contactId === '' || $customerId === null || $customerId === '') {
                return;
            }

            $contact = CustomerContact::query()->find($contactId);

            if ($contact && $contact->customer_id !== (int) $customerId) {
                $validator->errors()->add('contact_id', 'The selected contact is invalid.');
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
                'customer_id' => $errors['customer_id'] ?? [],
                'contact_id' => $errors['contact_id'] ?? [],
            ],
        ], 422));
    }
}
