<x-admin-layout title="Messages">
    <x-admin.table>
        <x-slot:head><tr><th class="px-4 py-3">From</th><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Received</th><th class="px-4 py-3"></th></tr></x-slot:head>
        @forelse ($messages as $m)
            <tr class="{{ $m->read_at ? '' : 'bg-gold-50 font-semibold' }}">
                <td class="px-4 py-3">{{ $m->name }}<span class="block text-xs font-normal text-stone-500">{{ $m->email }}</span></td>
                <td class="px-4 py-3">{{ $m->subject ?: Str::limit($m->message, 60) }}</td>
                <td class="px-4 py-3 font-normal text-stone-600">{{ $m->created_at->format('M j, g:i A') }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.messages.show', $m) }}" class="font-normal text-umber-700 underline">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-stone-600">No messages yet.</td></tr>
        @endforelse
    </x-admin.table>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-admin-layout>
