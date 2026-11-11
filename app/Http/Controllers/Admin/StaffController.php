<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    private const ROLES = ['admin', 'manager', 'staff'];

    public function index()
    {
        return view('admin.staff.index', ['staff' => User::query()->staff()->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.staff.form', ['member' => new User]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('admin.staff.index')->with('status', "{$user->name} can now sign in at /login.");
    }

    public function edit(User $staff)
    {
        abort_if($staff->role === 'customer', 404);

        return view('admin.staff.form', ['member' => $staff]);
    }

    public function update(Request $request, User $staff)
    {
        abort_if($staff->role === 'customer', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $isSelf = $staff->is($request->user());
        $demotingLastAdmin = $staff->role === 'admin'
            && ($data['role'] !== 'admin' || ! $request->boolean('is_active'))
            && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1;
        if ($demotingLastAdmin || ($isSelf && ($data['role'] !== $staff->role || ! $request->boolean('is_active')))) {
            return back()->withErrors(['role' => 'You cannot remove admin access from yourself or from the last active admin.']);
        }

        $staff->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $staff->password = $data['password'];
        }
        $staff->role = $data['role'];
        $staff->is_active = $request->boolean('is_active');
        $staff->save();

        return redirect()->route('admin.staff.index')->with('status', 'Staff member saved.');
    }

    public function destroy(Request $request, User $staff)
    {
        abort_if($staff->role === 'customer', 404);
        if ($staff->is($request->user()) || ($staff->role === 'admin' && User::query()->where('role', 'admin')->count() <= 1)) {
            return back()->withErrors(['staff' => 'You cannot delete yourself or the last admin.']);
        }

        // Keep the person's order/stock history; just remove admin access.
        $staff->role = 'customer';
        $staff->is_active = false;
        $staff->save();

        return back()->with('status', "{$staff->name} no longer has admin access.");
    }
}
