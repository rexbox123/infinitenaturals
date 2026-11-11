<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = User::query()->customers()
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->sales()], 'total')
            ->when($request->query('q'), function ($q, $term) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
            })
            ->latest('id')->paginate(25)->withQueryString();

        // Guest checkouts (no account) grouped by email, so the owner sees every buyer.
        $guests = Order::query()->whereNull('user_id')
            ->selectRaw('email, MAX(first_name) as first_name, MAX(last_name) as last_name, COUNT(*) as orders_count, MAX(created_at) as last_order_at')
            ->when($request->query('q'), fn ($q, $term) => $q->where('email', 'like', "%{$term}%"))
            ->groupBy('email')->orderByDesc('last_order_at')->take(25)->get();

        return view('admin.customers.index', compact('customers', 'guests'));
    }

    public function show(User $customer)
    {
        abort_if($customer->role !== 'customer', 404);

        $orders = Order::query()
            ->where(fn ($q) => $q->where('user_id', $customer->id)->orWhere('email', $customer->email))
            ->withCount('items')->latest('id')->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'totalSpent' => $orders->filter(fn ($o) => in_array($o->payment_status->value, ['paid', 'partially_refunded']))->sum(fn ($o) => $o->total - $o->amount_refunded),
        ]);
    }

    public function edit(User $customer)
    {
        abort_if($customer->role !== 'customer', 404);

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, User $customer)
    {
        abort_if($customer->role !== 'customer', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $customer->fill($data);
        $customer->is_active = $request->boolean('is_active');
        $customer->save();

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer saved.');
    }
}
