<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Database-backed cart identified by a token kept in the session, and linked
 * to the user when they are signed in (so it follows them across devices).
 */
class CartService
{
    private const SESSION_KEY = 'cart_token';

    private ?Cart $cart = null;

    /** Session token + user the cached cart was resolved for. */
    private ?string $resolvedFor = null;

    /** Current cart, or null when the visitor has none yet (no DB write). */
    public function current(): ?Cart
    {
        $token = session(self::SESSION_KEY);
        $key = $this->cacheKey();

        if ($this->cart && $this->resolvedFor === $key) {
            return $this->cart;
        }
        $this->cart = null;

        $cart = $token ? Cart::query()->where('token', $token)->first() : null;

        if (! $cart && ($user = auth()->user())) {
            $cart = Cart::query()->where('user_id', $user->id)->latest('id')->first();
            if ($cart) {
                session([self::SESSION_KEY => $cart->token]);
            }
        }

        if ($cart && $cart->user_id && $cart->user_id !== auth()->id()) {
            // Never expose a signed-in customer's cart to someone else on this session.
            $cart = null;
        }

        $this->resolvedFor = $this->cacheKey();

        return $this->cart = $cart?->load(['items.variant.product.primaryImage', 'discountCode']);
    }

    public function currentOrCreate(): Cart
    {
        if ($cart = $this->current()) {
            return $cart;
        }

        $cart = Cart::query()->create([
            'token' => (string) Str::uuid(),
            'user_id' => auth()->id(),
        ]);
        session([self::SESSION_KEY => $cart->token]);
        $this->resolvedFor = $this->cacheKey();

        return $this->cart = $cart->load(['items', 'discountCode']);
    }

    public function add(ProductVariant $variant, int $quantity): CartItem
    {
        $this->assertPurchasable($variant);
        $cart = $this->currentOrCreate();

        $item = $cart->items()->firstOrNew(['product_variant_id' => $variant->id]);
        $newQuantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $this->assertQuantity($variant, $newQuantity);

        $item->quantity = $newQuantity;
        $item->save();
        $cart->touch();
        $this->refresh();

        return $item;
    }

    public function update(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($item);

            return;
        }

        $this->assertQuantity($item->variant, $quantity);
        $item->update(['quantity' => $quantity]);
        $this->refresh();
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
        $this->refresh();
    }

    public function clear(): void
    {
        if ($cart = $this->current()) {
            $cart->items()->delete();
            $cart->update(['discount_code_id' => null]);
        }
        $this->refresh();
    }

    public function ownsItem(CartItem $item): bool
    {
        return $this->current()?->id === $item->cart_id;
    }

    /** After login: adopt the guest cart, merging it into the user's previous cart. */
    public function attachToUser(User $user, ?string $guestToken): void
    {
        $guest = $guestToken ? Cart::query()->where('token', $guestToken)->whereNull('user_id')->first() : null;
        $existing = Cart::query()->where('user_id', $user->id)->latest('id')->first();

        if ($guest && $existing && $guest->id !== $existing->id) {
            foreach ($guest->items as $item) {
                $line = $existing->items()->firstOrNew(['product_variant_id' => $item->product_variant_id]);
                $line->quantity = min(config('store.max_quantity_per_line'), ($line->exists ? $line->quantity : 0) + $item->quantity);
                $line->save();
            }
            $guest->delete();
            $cart = $existing;
        } else {
            $cart = $guest ?? $existing;
            $cart?->update(['user_id' => $user->id]);
        }

        if ($cart) {
            session([self::SESSION_KEY => $cart->token]);
        }
        $this->cart = null;
    }

    /**
     * Drop lines that can no longer be bought and clamp quantities to stock.
     * Returns customer-facing notices about anything that changed.
     *
     * @return list<string>
     */
    public function reconcile(): array
    {
        $cart = $this->current();
        if (! $cart) {
            return [];
        }

        $notices = [];
        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if (! $variant || ! $variant->is_active || ! $variant->product?->isVisible()) {
                $notices[] = 'An item in your cart is no longer available and was removed.';
                $item->delete();

                continue;
            }

            $max = $variant->purchasableQuantity();
            if ($max === 0) {
                $notices[] = "{$variant->displayName()} is out of stock and was removed from your cart.";
                $item->delete();
            } elseif ($item->quantity > $max) {
                $notices[] = "Only {$max} of {$variant->displayName()} available — quantity updated.";
                $item->update(['quantity' => $max]);
            }
        }

        if ($notices) {
            $this->refresh();
        }

        return array_values(array_unique($notices));
    }

    /** The cart is cached only for the current request, session token and user. */
    private function cacheKey(): string
    {
        return spl_object_id(request()).'|'.session(self::SESSION_KEY).'|'.auth()->id();
    }

    private function refresh(): void
    {
        $this->cart?->refresh()->load(['items.variant.product.primaryImage', 'discountCode']);
    }

    private function assertPurchasable(ProductVariant $variant): void
    {
        if (! $variant->is_active || ! $variant->product?->isVisible()) {
            throw ValidationException::withMessages(['variant_id' => 'This product is not available.']);
        }
    }

    private function assertQuantity(ProductVariant $variant, int $quantity): void
    {
        $max = $variant->purchasableQuantity();

        if ($max === 0) {
            throw ValidationException::withMessages(['quantity' => "{$variant->displayName()} is out of stock."]);
        }

        if ($quantity > $max) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$max} of {$variant->displayName()} can be added to your cart.",
            ]);
        }
    }
}
