<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxRateController extends Controller
{
    public function index()
    {
        return view('admin.tax.index', ['rates' => TaxRate::query()->orderBy('state')->get()]);
    }

    public function create()
    {
        return view('admin.tax.form', ['rate' => new TaxRate(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        TaxRate::query()->create($this->validated($request));

        return redirect()->route('admin.tax-rates.index')->with('status', 'Tax rate added.');
    }

    public function edit(TaxRate $taxRate)
    {
        return view('admin.tax.form', ['rate' => $taxRate]);
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        $taxRate->update($this->validated($request, $taxRate));

        return redirect()->route('admin.tax-rates.index')->with('status', 'Tax rate saved.');
    }

    public function destroy(TaxRate $taxRate)
    {
        $taxRate->delete();

        return back()->with('status', 'Tax rate deleted.');
    }

    private function validated(Request $request, ?TaxRate $rate = null): array
    {
        $data = $request->validate([
            'state' => ['required', Rule::in(array_keys(config('store.shipping_states'))), Rule::unique('tax_rates', 'state')->ignore($rate?->id)],
            'rate' => ['required', 'numeric', 'min:0', 'max:25'],
        ]);

        return [
            'state' => $data['state'],
            'rate' => $data['rate'],
            'applies_to_shipping' => $request->boolean('applies_to_shipping'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
