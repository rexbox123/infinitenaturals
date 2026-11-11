<x-admin-layout title="Message">
    <a href="{{ route('admin.messages.index') }}" class="text-sm underline">&larr; Messages</a>
    <x-admin.card class="mt-4 max-w-3xl">
        <p class="font-semibold">{{ $message->name }} · <a href="mailto:{{ $message->email }}" class="text-umber-700 underline">{{ $message->email }}</a>@if($message->phone) · {{ $message->phone }}@endif</p>
        <p class="text-xs text-stone-500">{{ $message->created_at->format('F j, Y g:i A') }}</p>
        @if ($message->subject)<p class="mt-4 font-semibold">{{ $message->subject }}</p>@endif
        <p class="mt-2 whitespace-pre-line">{{ $message->message }}</p>
        <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'Your message to '.setting('store.name'))) }}" class="btn-primary btn-sm mt-6">Reply by email</a>
    </x-admin.card>
</x-admin-layout>
