@props([
    'name',
    'checked' => false,
    'disabled' => false,
    'event' => 'ui-toggle:changed',
    'value' => null,
])

<button
    type="button"
    role="switch"
    x-data="{ checked: @js((bool) $checked) }"
    x-bind:aria-checked="checked ? 'true' : 'false'"
    @disabled($disabled)
    x-on:click.stop.prevent="
        if ($el.disabled) {
            return;
        }

        checked = !checked;
        $dispatch(@js($event), {
            name: @js($name),
            checked,
            value: checked,
            row: @js($value),
            record: @js($value),
            id: @js(is_array($value) ? ($value['id'] ?? null) : null),
        });
    "
    {{ $attributes->merge([
        'class' => 'group relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full bg-gray-200 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-500 disabled:cursor-not-allowed disabled:opacity-50',
    ]) }}
    x-bind:class="checked ? 'bg-lime-500' : 'bg-gray-200'"
>
    <span class="sr-only">{{ $slot->isEmpty() ? __('Toggle') : $slot }}</span>
    <span
        aria-hidden="true"
        class="pointer-events-none inline-block h-5 w-5 translate-x-0 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
        x-bind:class="checked ? 'translate-x-5' : 'translate-x-0'"
    ></span>
</button>
