<?php

namespace App\Http\Requests\Sales;

use App\Models\ExternalCustomerMapping;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator as ValidationValidator;
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
                Rule::requiredIf(! $this->boolean('is_local_file_import')),
                'nullable',
                'string',
                Rule::in(['woocommerce', 'shopify']),
            ],
            'is_local_file_import' => [
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
            'rows.*.external_source' => [
                'nullable',
                'string',
                Rule::in(['woocommerce', 'shopify']),
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
            'rows.*.is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Configure cross-field validation for duplicate customer imports.
     */
    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $rows = $this->input('rows', []);

            if (! is_array($rows)) {
                return;
            }

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $externalSource = $this->resolvedRowExternalSource($row);
                $externalId = $this->normalizedExternalId($row['external_id'] ?? null);

                if (
                    ! $this->boolean('is_local_file_import')
                    && array_key_exists('external_source', $row)
                    && ($this->input('source') === null || $this->input('source') === '')
                ) {
                    $validator->errors()->add(
                        'source',
                        'The source field is required.'
                    );
                }

                if (
                    ! $this->boolean('is_local_file_import')
                    && array_key_exists('external_source', $row)
                    && $row['external_source'] !== null
                    && $row['external_source'] !== ''
                    && $this->normalizedExternalSource($row['external_source']) !== $this->normalizedExternalSource($this->input('source'))
                ) {
                    $validator->errors()->add(
                        "rows.{$index}.external_source",
                        'The selected row source must match the selected source.'
                    );
                }

                if (
                    $externalSource !== null
                    && $externalId !== null
                    && ExternalCustomerMapping::query()
                        ->where('tenant_id', $this->user()?->tenant_id)
                        ->whereRaw('LOWER(TRIM(source)) = ?', [$externalSource])
                        ->whereRaw('TRIM(external_customer_id) = ?', [$externalId])
                        ->exists()
                ) {
                    $validator->errors()->add(
                        "rows.{$index}.external_id",
                        'A customer with the same external source and external ID already exists.'
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

    /**
     * Resolve the normalized external source for a submitted import row.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolvedRowExternalSource(array $row): ?string
    {
        $rowSource = $this->normalizedExternalSource($row['external_source'] ?? null);

        if ($rowSource !== null) {
            return $rowSource;
        }

        return $this->normalizedExternalSource($this->input('source'));
    }

    /**
     * Normalize an external source value for duplicate checks.
     */
    private function normalizedExternalSource(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize an external ID value for duplicate checks.
     */
    private function normalizedExternalId(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
