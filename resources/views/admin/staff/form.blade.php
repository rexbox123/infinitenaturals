<x-admin-layout :title="$member->exists ? 'Edit '.$member->name : 'Add staff'">
    <a href="{{ route('admin.staff.index') }}" class="text-sm underline">&larr; Staff</a>
    <form method="post" action="{{ $member->exists ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="mt-4 max-w-xl">
        @csrf @if($member->exists) @method('put') @endif
        <x-admin.card>
            <div class="grid gap-4">
                <x-admin.field name="name" label="Name" :value="$member->name" required />
                <x-admin.field name="email" label="Email (login)" type="email" :value="$member->email" required />
                <div><label for="role" class="label">Role</label>
                    <select id="role" name="role" class="input">@foreach (['admin', 'manager', 'staff'] as $r)<option value="{{ $r }}" @selected(old('role', $member->role) === $r)>{{ config("store.roles.$r") }}</option>@endforeach</select></div>
                <x-admin.field name="password" label="{{ $member->exists ? 'New password (leave blank to keep)' : 'Password' }}" type="password" autocomplete="new-password" :required="! $member->exists" />
                <x-admin.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
                @if ($member->exists)<x-admin.checkbox name="is_active" label="Active" :checked="$member->is_active" />@endif
            </div>
        </x-admin.card>
        <button class="btn-primary btn-sm mt-4">Save</button>
    </form>
    @if ($member->exists && ! $member->is(auth()->user()))
        <form method="post" action="{{ route('admin.staff.destroy', $member) }}" class="mt-6" onsubmit="return confirm('Remove admin access for this person?')">@csrf @method('delete')<button class="text-sm text-red-700 underline">Remove admin access</button></form>
    @endif
</x-admin-layout>
