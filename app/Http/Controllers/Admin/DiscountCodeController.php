<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountCodeController extends Controller
{
    public function index()
    {
        return view('admin.discounts.index', ['discounts' => DiscountCode::query()->latest('id')->paginate(25)]);
    }

    public function create()
    {
        return view('admin.discounts.form', ['discount' => new DiscountCode(['type' => DiscountType::Percent, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        DiscountCode::query()->create($this->validated($request));

        return redirect()->route('admin.discounts.index')->with('status', 'Discount code created.');
    }

    public function edit(DiscountCode $discount)
    {
        return view('admin.discounts.form', compact('discount'));
    }

    public function update(Request $request, DiscountCode $discount)
    {
        $discount->update($this->validated($request, $discount));

        return redirect()->route('admin.discounts.index')->with('status', 'Discount code saved.');
    }

    public function destroy(DiscountCode $discount)
    {
        if ($discount->orders()->exists()) {
            $discount->update(['is_active' => false]);

            return back()->with('status', 'This code was used on orders, so it was deactivated instead of deleted.');
        }
        $discount->delete();

        return back()->with('status', 'Discount code deleted.');
    }

    private function validated(Request $request, ?DiscountCode $discount = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('discount_codes', 'code')->ignore($discount?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'percent' => ['required_if:type,percent', 'nullable', 'integer', 'min:1', 'max:100'],
            'amount' => ['required_if:type,fixed', 'nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'min_subtotal' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
        ], ['code.regex' => 'Use letters, numbers, dashes and underscores only.']);

        $type = DiscountType::from($data['type']);

        return [
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'value' => match ($type) {
                DiscountType::Percent => (int) $data['percent'],
                DiscountType::Fixed => Money::toCents($data['amount']),
                DiscountType::FreeShipping => 0,
            },
            'min_subtotal' => Money::toCents($data['min_subtotal'] ?? null),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
