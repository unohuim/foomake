@props([
    'eventName' => 'validation-error',
])

<div
    {{ $attributes->class(['w-full']) }}
    x-data="{
        visible: false,
        message: '',
        init() {
            window.addEventListener(@js($eventName), (event) => {
                this.message = String(event?.detail?.message || 'A validation error occurred.');
                this.visible = true;
            });
        },
    }"
    x-show="visible"
    x-cloak
    role="alert"
    aria-live="assertive"
>
    <div class="flex items-start gap-3 border border-red-200 bg-red-50 px-4 py-3 text-red-700 sm:rounded-lg">
        <div class="min-w-0 flex-1 text-sm font-medium" x-text="message"></div>

        <button
            type="button"
            class="ml-3 inline-flex shrink-0 items-center justify-center text-red-500 transition hover:text-red-700"
            x-on:click="visible = false"
            aria-label="Close validation message"
        >
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
