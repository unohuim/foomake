<x-guest-layout>
    <form method="POST" action="{{ route('invitations.register.store', $token) }}">
        @csrf

        <div class="mb-4 rounded-md bg-gray-50 p-4 text-sm text-gray-700">
            <p class="font-medium text-gray-900">{{ $invitation->email }}</p>
            <p class="mt-1">You are joining {{ $invitation->tenant?->tenant_name ?? 'this team' }} as {{ $invitation->role?->name ?? 'a member' }}.</p>
        </div>

        @if ($requiresPassword)
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        @endif

        <div class="mt-4 flex items-center justify-end">
            <x-primary-button>
                {{ $requiresPassword ? __('Create Account') : __('Accept Invitation') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
