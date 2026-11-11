<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Cart;
use App\Models\DiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class CartTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    public function test_add_update_and_remove_items_with_accurate_totals(): void
    {
        $serum = $this->variant(6900, 10);
        $soap = $this->variant(850, 10);

        $this->addToCart($serum, 1);
        $this->addToCart($soap, 2);
        $this->addToCart($soap, 1); // merges into the same line

        $cart = Cart::query()->first();
        $this->assertCount(2, $cart->items);
        $this->assertSame(3, $cart->items->firstWhere('product_variant_id', $soap->id)->quantity);

        $this->get('/cart')->assertOk()->assertSee('$94.50'); // 69.00 + 3 × 8.50

        $soapLine = $cart->items->firstWhere('product_variant_id', $soap->id);
        $this->patch(route('cart.items.update', $soapLine), ['quantity' => 1])->assertRedirect('/cart');
        $this->get('/cart')->assertSee('$77.50');

        $this->delete(route('cart.items.destroy', $soapLine))->assertRedirect('/cart');
        $this->get('/cart')->assertSee('$69.00')->assertDontSee('$77.50');
    }

    public function test_cannot_add_more_than_stock(): void
    {
        $variant = $this->variant(1000, 2);
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(0, Cart::query()->withCount('items')->first()?->items_count ?? 0);
    }

    public function test_cannot_modify_someone_elses_cart_line(): void
    {
        $variant = $this->variant();
        $this->addToCart($variant);
        $item = Cart::query()->first()->items->first();

        $this->flushSession();
        $this->patch(route('cart.items.update', $item), ['quantity' => 5])->assertNotFound();
        $this->delete(route('cart.items.destroy', $item))->assertNotFound();
    }

    public function test_cart_quantities_shrink_when_stock_drops(): void
    {
        $variant = $this->variant(1000, 5);
        $this->addToCart($variant, 5);
        $variant->forceFill(['stock_quantity' => 2])->save();

        $this->get('/cart')->assertSee('Only 2 of');
        $this->assertSame(2, Cart::query()->first()->items->first()->quantity);
    }

    public function test_discount_codes(): void
    {
        $this->addToCart($this->variant(4000, 5));
        DiscountCode::query()->create(['code' => 'WELCOME10', 'type' => DiscountType::Percent, 'value' => 10, 'is_active' => true]);
        DiscountCode::query()->create(['code' => 'OLD', 'type' => DiscountType::Percent, 'value' => 10, 'is_active' => true, 'ends_at' => now()->subDay()]);

        $this->post(route('cart.discount.apply'), ['code' => 'nope'])->assertSessionHasErrors('code');
        $this->post(route('cart.discount.apply'), ['code' => 'old'])->assertSessionHasErrors(['code' => 'This discount code has expired.']);
        $this->post(route('cart.discount.apply'), ['code' => 'welcome10'])->assertSessionHasNoErrors()->assertRedirect();

        $this->get('/cart')->assertSee('WELCOME10')->assertSee('-$4.00')->assertSee('$36.00');
    }

    public function test_guest_cart_is_kept_after_login(): void
    {
        $variant = $this->variant();
        $this->addToCart($variant, 2);
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->get('/cart')->assertSee($variant->product->name);
        $this->assertSame($user->id, Cart::query()->first()->user_id);
    }
}
