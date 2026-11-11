<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\DiscountCode;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Checkout\PriceCalculator;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(PriceCalculator $calculator)
    {
        $notices = $this->carts->reconcile();
        $cart = $this->carts->current();
        $lines = $this->lines();
        $totals = $calculator->calculate($lines, $cart?->discountCode);

        return view('store.cart', compact('cart', 'totals', 'notices'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('store.max_quantity_per_line')],
        ]);

        $variant = ProductVariant::query()->with('product')->findOrFail($data['variant_id']);
        $this->carts->add($variant, (int) $data['quantity']);

        return redirect()->back()->with('cart_added', $variant->displayName());
    }

    public function update(Request $request, CartItem $item)
    {
        abort_unless($this->carts->ownsItem($item), 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.config('store.max_quantity_per_line')]]);
        $this->carts->update($item, (int) $data['quantity']);

        return redirect()->route('cart.show')->with('status', 'Cart updated.');
    }

    public function destroy(CartItem $item)
    {
        abort_unless($this->carts->ownsItem($item), 404);
        $this->carts->remove($item);

        return redirect()->route('cart.show')->with('status', 'Item removed.');
    }

    public function applyDiscount(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);
        $cart = $this->carts->current();
        abort_if(! $cart || $cart->items->isEmpty(), 422, 'Your cart is empty.');

        $code = DiscountCode::findByCode($data['code']);
        $subtotal = (int) $this->lines()->sum(fn ($l) => $l['variant']->currentPrice() * $l['quantity']);
        $reason = $code ? $code->unavailableReason($subtotal, $request->user()?->email) : 'That discount code was not found.';

        if ($reason) {
            return redirect()->back()->withErrors(['code' => $reason])->withInput();
        }

        $cart->update(['discount_code_id' => $code->id]);

        return redirect()->back()->with('status', "Discount code {$code->code} applied.");
    }

    public function removeDiscount()
    {
        $this->carts->current()?->update(['discount_code_id' => null]);

        return redirect()->back()->with('status', 'Discount code removed.');
    }

    private function lines()
    {
        return ($this->carts->current()?->items ?? collect())
            ->map(fn (CartItem $i) => ['variant' => $i->variant, 'quantity' => $i->quantity]);
    }
}
