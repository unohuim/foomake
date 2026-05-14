<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * Validate external sales order preview requests.
 */
class PreviewExternalSalesOrderImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('sales-sales-orders-manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(['file-upload', 'woocommerce', 'shopify'])],
            'rows' => ['nullable', 'array'],
            'rows.*.external_id' => ['required', 'string', 'max:255'],
            'rows.*.external_source' => ['required', 'string', 'max:255'],
            'rows.*.external_status' => ['nullable', 'string', 'max:255'],
            'rows.*.status' => ['nullable', 'string', 'max:255'],
            'rows.*.date' => ['nullable', 'date'],
            'rows.*.contact_name' => ['nullable', 'string', 'max:255'],
            'rows.*.customer' => ['required', 'array'],
            'rows.*.customer.external_id' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.name' => ['required', 'string', 'max:255'],
            'rows.*.customer.email' => ['nullable', 'email', 'max:255'],
            'rows.*.customer.phone' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.address_line_1' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.address_line_2' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.city' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.region' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.postal_code' => ['nullable', 'string', 'max:255'],
            'rows.*.customer.country_code' => ['nullable', 'string', 'size:2'],
            'rows.*.lines' => ['required', 'array', 'min:1'],
            'rows.*.lines.*.external_id' => ['nullable', 'string', 'max:255'],
            'rows.*.lines.*.product_external_id' => ['nullable', 'string', 'max:255'],
            'rows.*.lines.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.lines.*.quantity' => ['nullable', 'string', 'max:255'],
            'rows.*.lines.*.unit_price' => ['nullable', 'string', 'max:255'],
            'rows.*.lines.*.unit_price_cents' => ['nullable', 'integer', 'min:0'],
            'rows.*.lines.*.currency_code' => ['nullable', 'string', 'size:3'],
        ];
    }

    /**
     * Configure cross-field validation for grouped file-upload rows.
     */
    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $rows = $this->input('rows', []);

            if (! is_array($rows) || $rows === []) {
                return;
            }

            $normalizedSources = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowSource = $this->normalizedExternalSource($row['external_source'] ?? null);

                if ($rowSource === null) {
                    $validator->errors()->add(
                        "rows.{$index}.external_source",
                        'Each imported order row must include an external source.'
                    );

                    continue;
                }

                $normalizedSources[] = $rowSource;

                if ($this->input('source') !== 'file-upload' && $rowSource !== $this->normalizedExternalSource($this->input('source'))) {
                    $validator->errors()->add(
                        "rows.{$index}.external_source",
                        'The selected row source must match the selected ecommerce store.'
                    );
                }
            }

            if ($this->input('source') === 'file-upload' && count(array_unique($normalizedSources)) > 1) {
                $validator->errors()->add(
                    'rows',
                    'Every row in one import file must use the same external source.'
                );
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
     * Normalize an external source value for grouped-row validation.
     */
    private function normalizedExternalSource(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }
}
