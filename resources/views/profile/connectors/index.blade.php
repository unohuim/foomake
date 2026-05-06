<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Connectors') }}
        </h2>
    </x-slot>

    <script type="application/json" id="profile-connectors-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="profile-connectors-index"
        data-payload="profile-connectors-index-payload"
        x-data="profileConnectorsIndex"
    >
        <div class="max-w-4xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900">WooCommerce</h3>
                    <p class="mt-1 text-sm text-gray-600">Manage the tenant WooCommerce store used for product preview imports.</p>
                </div>

                <div class="space-y-6 px-6 py-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Connection status</p>
                            <p class="mt-1 text-sm text-gray-600" x-text="statusLabel"></p>
                            <p class="mt-2 text-xs text-red-600" x-text="lastError"></p>
                        </div>

                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold uppercase"
                                :class="isConnected ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'"
                                x-text="isConnected ? 'Connected' : 'Disconnected'"
                            ></span>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-red-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-700 hover:bg-red-50"
                                x-show="isConnected"
                                x-on:click="disconnect()"
                            >
                                Disconnect
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-5">
                        <h4 class="text-sm font-semibold text-gray-900">Connect or reconnect</h4>
                        <p class="mt-1 text-sm text-gray-600">Credentials are verified before saving and are never rendered back after storage.</p>

                        <div class="mt-4 space-y-4">
                            <label class="block text-sm font-medium text-gray-700">
                                Store URL
                                <input
                                    type="url"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.store_url"
                                >
                                <span class="mt-1 block text-xs text-red-600" x-text="fieldError('store_url')"></span>
                            </label>

                            <label class="block text-sm font-medium text-gray-700">
                                Consumer Key
                                <input
                                    type="password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.consumer_key"
                                >
                                <span class="mt-1 block text-xs text-red-600" x-text="fieldError('consumer_key')"></span>
                            </label>

                            <label class="block text-sm font-medium text-gray-700">
                                Consumer Secret
                                <input
                                    type="password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.consumer_secret"
                                >
                                <span class="mt-1 block text-xs text-red-600" x-text="fieldError('consumer_secret')"></span>
                            </label>
                        </div>

                        <div class="mt-5 flex items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                x-on:click="save()"
                            >
                                Save WooCommerce Connection
                            </button>
                            <span class="text-sm text-red-600" x-text="formMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
