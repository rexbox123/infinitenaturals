<x-app-layout>
    <x-slot name="header">{{ __('Profile') }}</x-slot>

    <p class="-mt-4 mb-6 text-sm"><a href="{{ route('dashboard') }}" class="link">&larr; Back to my account</a></p>
    <div>
        <div class="space-y-6">
            <div class="p-4 sm:p-8 rounded-2xl border border-gold-200 bg-white">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 rounded-2xl border border-gold-200 bg-white">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 rounded-2xl border border-gold-200 bg-white">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
