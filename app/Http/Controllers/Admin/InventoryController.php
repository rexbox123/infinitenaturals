<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $threshold = (int) setting('inventory.low_stock_threshold', 5);

        $variants = ProductVariant::query()->with('product')
            ->whereHas('product', fn ($q) => $q->where('status', '!=', 'archived'))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(fn ($w) => $w->where('sku', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%")));
            })
            ->when($request->query('filter') === 'low', fn ($q) => $q->where('track_inventory', true)->where('stock_quantity', '<=', $threshold))
            ->when($request->query('filter') === 'out', fn ($q) => $q->where('track_inventory', true)->where('stock_quantity', '<=', 0))
            ->orderBy('stock_quantity')
            ->paginate(50)->withQueryString();

        return view('admin.inventory.index', [
            'variants' => $variants,
            'threshold' => $threshold,
            'recent' => StockMovement::query()->with(['variant.product', 'user', 'order'])->latest('id')->take(15)->get(),
        ]);
    }

    public function show(ProductVariant $variant)
    {
        return view('admin.inventory.show', [
            'variant' => $variant->load('product'),
            'movements' => $variant->stockMovements()->with(['user', 'order'])->paginate(50),
            'reasons' => array_intersect_key(StockMovement::REASONS, array_flip(['restock', 'adjustment', 'damaged', 'return'])),
        ]);
    }

    public function adjust(Request $request, ProductVariant $variant, InventoryService $inventory)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:add,remove,set'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', Rule::in(['restock', 'adjustment', 'damaged', 'return'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            match ($data['mode']) {
                'add' => $inventory->adjust($variant, $data['quantity'], $data['reason'], $request->user(), $data['note'] ?? null),
                'remove' => $inventory->adjust($variant, -$data['quantity'], $data['reason'], $request->user(), $data['note'] ?? null),
                'set' => $inventory->setLevel($variant, $data['quantity'], $data['reason'], $request->user(), $data['note'] ?? null),
            };
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('status', "Stock for {$variant->displayName()} is now {$variant->fresh()->stock_quantity}.");
    }
}
