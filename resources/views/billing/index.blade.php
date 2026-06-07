<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ __('Account') }}</p>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Billing') }}
                </h2>
            </div>

            @if ($hasAccess)
                <span class="inline-flex w-fit items-center rounded-full border border-green-200 bg-green-50 px-3 py-1 text-sm font-medium text-green-700">
                    {{ __('Access active') }}
                </span>
            @else
                <span class="inline-flex w-fit items-center rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-medium text-yellow-800">
                    {{ __('Billing required') }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="bg-white p-6 shadow sm:rounded-lg">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ $tenant->tenant_name ?: __('Tenant account') }}
                        </h3>

                        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <dt class="text-sm font-medium text-gray-500">{{ __('Trial ends') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $tenant->trial_ends_at?->format('M j, Y') ?? __('Not available') }}
                                </dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <dt class="text-sm font-medium text-gray-500">{{ __('Subscription') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $subscriptionActive ? __('Active') : __('Not active') }}
                                </dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <dt class="text-sm font-medium text-gray-500">{{ __('Billing provider') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $tenant->billing_provider ?: __('Not connected') }}
                                </dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <dt class="text-sm font-medium text-gray-500">{{ __('Temporary exemption') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $billingExempt ? __('Active') : __('None') }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <aside class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        @if ($canManageBilling)
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('Manage billing') }}</h4>
                            <p class="mt-2 text-sm text-gray-600">
                                @if ($trialActive && ! $subscriptionActive)
                                    {{ __('Add payment details now and keep access uninterrupted after the trial.') }}
                                @else
                                    {{ __('Start or restore subscription access for this tenant.') }}
                                @endif
                            </p>
                            <form method="POST" action="{{ route('billing.checkout.store') }}" class="mt-4">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                    {{ $trialActive && ! $subscriptionActive ? __('Add payment details') : __('Subscribe') }}
                                </button>
                            </form>
                        @else
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('Billing managed by admins') }}</h4>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ __('Ask a tenant admin to update billing for this account.') }}
                            </p>
                        @endif
                    </aside>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
