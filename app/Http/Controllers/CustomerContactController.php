<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Handle AJAX CRUD for customer contacts.
 */
class CustomerContactController extends Controller
{
    /**
     * Store a new contact for the customer.
     */
    public function store(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('sales-customers-manage');

        $validated = $this->validatePayload($request, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $contact = DB::transaction(function () use ($request, $customer, $validated): CustomerContact {
            $existingCount = CustomerContact::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->count();

            $contact = CustomerContact::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'customer_id' => $customer->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'role' => $validated['role'] ?? null,
                'is_primary' => $existingCount === 0,
            ]);

            if (($validated['is_primary'] ?? false) && $existingCount > 0) {
                $this->makePrimary($customer, $contact);
            }

            return $contact->fresh();
        });

        return response()->json([
            'data' => $this->contactData($contact),
        ], 201);
    }

    /**
     * Update an existing customer contact.
     */
    public function update(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        Gate::authorize('sales-customers-manage');
        $this->abortIfWrongCustomer($customer, $contact);

        $validated = $this->validatePayload($request, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $contact = DB::transaction(function () use ($customer, $contact, $validated): CustomerContact {
            CustomerContact::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->get();

            $contact->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'role' => $validated['role'] ?? null,
            ]);

            if (($validated['is_primary'] ?? false) === true) {
                $this->makePrimary($customer, $contact);
            }

            return $contact->fresh();
        });

        return response()->json([
            'data' => $this->contactData($contact),
        ]);
    }

    /**
     * Mark a contact as the primary contact for the customer.
     */
    public function setPrimary(Customer $customer, CustomerContact $contact): JsonResponse
    {
        Gate::authorize('sales-customers-manage');
        $this->abortIfWrongCustomer($customer, $contact);

        $contact = DB::transaction(function () use ($customer, $contact): CustomerContact {
            CustomerContact::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->get();

            $this->makePrimary($customer, $contact);

            return $contact->fresh();
        });

        return response()->json([
            'data' => $this->contactData($contact),
        ]);
    }

    /**
     * Delete a customer contact when allowed by the primary-contact rules.
     */
    public function destroy(Customer $customer, CustomerContact $contact): JsonResponse
    {
        Gate::authorize('sales-customers-manage');
        $this->abortIfWrongCustomer($customer, $contact);

        $response = DB::transaction(function () use ($customer, $contact): JsonResponse {
            $contacts = CustomerContact::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($contact->is_primary && $contacts->count() > 1) {
                return response()->json([
                    'message' => 'Primary contact cannot be deleted while other contacts exist.',
                ], 422);
            }

            $contact->delete();

            return response()->json([
                'message' => 'Deleted.',
            ]);
        });

        return $response;
    }

    /**
     * Validate request payload and normalize JSON validation errors.
     *
     * @param  array<string, array<int, string>>  $rules
     * @return array<string, mixed>|JsonResponse
     */
    private function validatePayload(Request $request, array $rules): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        return $validator->validated();
    }

    /**
     * Return a stable JSON validation error response.
     */
    private function validationErrorResponse(ValidatorContract $validator): JsonResponse
    {
        $errors = $validator->errors()->toArray();

        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'first_name' => $errors['first_name'] ?? [],
                'last_name' => $errors['last_name'] ?? [],
                'email' => $errors['email'] ?? [],
                'phone' => $errors['phone'] ?? [],
                'role' => $errors['role'] ?? [],
                'is_primary' => $errors['is_primary'] ?? [],
            ],
        ], 422);
    }

    /**
     * Promote a contact to primary and demote the remaining contacts for the customer.
     */
    private function makePrimary(Customer $customer, CustomerContact $contact): void
    {
        CustomerContact::query()
            ->where('customer_id', $customer->id)
            ->where('id', '!=', $contact->id)
            ->update(['is_primary' => false]);

        if (! $contact->is_primary) {
            $contact->update(['is_primary' => true]);
        }
    }

    /**
     * Abort when the contact does not belong to the provided customer.
     */
    private function abortIfWrongCustomer(Customer $customer, CustomerContact $contact): void
    {
        if ($contact->customer_id !== $customer->id) {
            abort(404);
        }
    }

    /**
     * Build the JSON response payload for a contact.
     *
     * @return array<string, int|string|bool|null>
     */
    private function contactData(CustomerContact $contact): array
    {
        return [
            'id' => $contact->id,
            'tenant_id' => $contact->tenant_id,
            'customer_id' => $contact->customer_id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => $contact->full_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'role' => $contact->role,
            'is_primary' => $contact->is_primary,
        ];
    }
}
