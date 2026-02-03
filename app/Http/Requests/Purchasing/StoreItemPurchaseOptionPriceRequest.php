<?php

namespace App\Http\Requests\Purchasing;

use App\Models\ItemPurchaseOption;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Validate requests to create purchase option prices.
 */
class StoreItemPurchaseOptionPriceRequest extends FormRequest
{
    private ?string $finalCurrency = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('purchasing-suppliers-manage');
    }

    /**
     * Prepare the input before validation runs.
     */
    public function prepareForValidation(): void
    {
        $tenantCurrency = $this->resolveTenantCurrency();
        $supplierCurrency = $this->resolveSupplierCurrency();
        $normalizedCurrency = $this->normalizeCurrencyInput();

        if ($normalizedCurrency !== null) {
            $this->finalCurrency = $normalizedCurrency;
            $this->merge(['price_currency_code' => $normalizedCurrency]);
        } elseif ($supplierCurrency !== null) {
            $this->finalCurrency = $supplierCurrency;
            $this->merge(['price_currency_code' => $supplierCurrency]);
        } elseif ($tenantCurrency !== null) {
            $this->finalCurrency = $tenantCurrency;
            $this->merge(['price_currency_code' => $tenantCurrency]);
        } else {
            $this->finalCurrency = null;
        }

        if ($this->finalCurrency !== null && $tenantCurrency !== null && $this->finalCurrency === $tenantCurrency) {
            $this->mergeFxDefaults();
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantCurrency = $this->resolveTenantCurrency();
        $currencyCode = $this->finalCurrency;
        $fxRequired = $tenantCurrency !== null && $currencyCode !== null && $currencyCode !== $tenantCurrency;

        return [
            'price_cents' => ['required', 'integer', 'min:0'],
            'price_currency_code' => ['required', 'string', 'size:3'],
            'fx_rate' => $fxRequired ? ['required', 'numeric'] : ['nullable', 'numeric'],
            'fx_rate_as_of' => $fxRequired ? ['required', 'date'] : ['nullable', 'date'],
        ];
    }

    /**
     * Ensure validation responses include all expected keys.
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
     * Normalize error shape.
     *
     * @param array<string, array<int, string>> $errors
     */
    private function normalizeErrors(array $errors): array
    {
        foreach (['price_cents', 'price_currency_code', 'fx_rate', 'fx_rate_as_of'] as $field) {
            if (! array_key_exists($field, $errors) || ! is_array($errors[$field])) {
                $errors[$field] = [];
            }
        }

        return $errors;
    }

    /**
     * Normalize the currency input to an uppercase string.
     */
    private function normalizeCurrencyInput(): ?string
    {
        $input = $this->input('price_currency_code');

        if (! is_string($input)) {
            return null;
        }

        $trimmed = trim($input);

        if ($trimmed === '') {
            return null;
        }

        return strtoupper($trimmed);
    }

    /**
     * Resolve the supplier currency for the option being priced.
     */
    private function resolveSupplierCurrency(): ?string
    {
        $option = $this->route('option');

        if (! $option instanceof ItemPurchaseOption) {
            return null;
        }

        $currency = $option->supplier?->currency_code;

        if (! $currency) {
            return null;
        }

        return strtoupper($currency);
    }

    /**
     * Merge FX defaults when the currency matches the tenant currency.
     */
    private function mergeFxDefaults(): void
    {
        $fxRate = $this->hasNonEmpty('fx_rate') ? $this->input('fx_rate') : '1';
        $fxRateAsOf = $this->hasNonEmpty('fx_rate_as_of')
            ? $this->input('fx_rate_as_of')
            : Carbon::now()->toDateString();

        $this->merge([
            'fx_rate' => $fxRate,
            'fx_rate_as_of' => $fxRateAsOf,
        ]);
    }

    /**
     * Resolve the tenant currency code (uppercase).
     */
    private function resolveTenantCurrency(): ?string
    {
        $tenantCurrency = $this->user()?->tenant?->currency_code;

        if (! is_string($tenantCurrency)) {
            return null;
        }

        $trimmed = trim($tenantCurrency);

        if ($trimmed === '') {
            return null;
        }

        return strtoupper($trimmed);
    }

    /**
     * Determine if the named field has a non-empty value.
     */
    private function hasNonEmpty(string $field): bool
    {
        return $this->has($field) && $this->input($field) !== '' && $this->input($field) !== null;
    }
}
