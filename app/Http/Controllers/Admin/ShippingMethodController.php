<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Support\Money;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function index()
    {
        return view('admin.shipping.index', ['methods' => ShippingMethod::query()->orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.shipping.form', ['method' => new ShippingMethod(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        ShippingMethod::query()->create($this->validated($request));

        return redirect()->route('admin.shipping-methods.index')->with('status', 'Shipping method created.');
    }

    public function edit(ShippingMethod $shippingMethod)
    {
        return view('admin.shipping.form', ['method' => $shippingMethod]);
    }

    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $shippingMethod->update($this->validated($request));

        return redirect()->route('admin.shipping-methods.index')->with('status', 'Shipping method saved.');
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $shippingMethod->delete();

        return back()->with('status', 'Shipping method deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'free_over' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rate' => Money::toCents($data['rate']),
            'free_over' => Money::toCents($data['free_over'] ?? null),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
