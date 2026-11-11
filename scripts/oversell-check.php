<?php

/**
 * Concurrency check: many customers try to buy the same limited-stock item at
 * the same instant. Verifies that exactly `stock` orders succeed and stock
 * never goes negative.
 *
 * Needs MySQL or PostgreSQL (SQLite serialises all writes, so it proves nothing)
 * and the pcntl extension. Creates and then deletes its own test data.
 *
 *   php scripts/oversell-check.php [stock=3] [buyers=20]
 *
 * With Docker:
 *   docker compose exec app php scripts/oversell-check.php 3 20
 */

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\Checkout\CheckoutException;
use App\Services\Checkout\PlaceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (app()->environment('production')) {
    fwrite(STDERR, "Refusing to run in production.\n");
    exit(1);
}
if (! function_exists('pcntl_fork')) {
    fwrite(STDERR, "The pcntl extension is required.\n");
    exit(1);
}
if (DB::getDriverName() === 'sqlite') {
    fwrite(STDERR, "Run this against MySQL/PostgreSQL; SQLite cannot show concurrency problems.\n");
    exit(1);
}

$stock = (int) ($argv[1] ?? 3);
$buyers = (int) ($argv[2] ?? 20);
$tag = 'oversell-'.Str::lower(Str::random(6));

// --- setup ---------------------------------------------------------------
$category = Category::query()->create(['name' => $tag, 'slug' => $tag, 'is_active' => true]);
$product = Product::query()->create(['category_id' => $category->id, 'name' => "Concurrency test {$tag}", 'slug' => $tag, 'status' => 'active']);
$variant = $product->variants()->create(['name' => 'Default', 'price' => 1000, 'track_inventory' => true, 'is_active' => true]);
$variant->forceFill(['stock_quantity' => $stock])->save();
$shipping = ShippingMethod::query()->create(['name' => $tag, 'rate' => 0, 'is_active' => true]);

$carts = [];
for ($i = 0; $i < $buyers; $i++) {
    $cart = Cart::query()->create(['token' => (string) Str::uuid()]);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
    $carts[] = $cart->id;
}

echo "Product with stock {$stock}; {$buyers} buyers checking out simultaneously…\n";

// --- race ------------------------------------------------------------------
DB::disconnect();
$startAt = microtime(true) + 1.0;
$children = [];

foreach ($carts as $i => $cartId) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        DB::reconnect();
        time_sleep_until($startAt);
        try {
            app(PlaceOrder::class)->handle(Cart::query()->findOrFail($cartId), [
                'email' => "{$tag}-{$i}@example.test", 'phone' => '555-0100', 'first_name' => 'Test', 'last_name' => "Buyer {$i}",
                'address_line1' => '1 Test St', 'city' => 'Ocala', 'state' => 'FL', 'postal_code' => '34480',
                'shipping_method_id' => $shipping->id, 'payment_method' => 'stripe',
            ]);
            exit(0);
        } catch (CheckoutException) {
            exit(2);
        } catch (Throwable $e) {
            fwrite(STDERR, "Buyer {$i}: ".$e->getMessage()."\n");
            exit(3);
        }
    }
    $children[] = $pid;
}

$results = [0 => 0, 2 => 0, 3 => 0];
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    $code = pcntl_wexitstatus($status);
    $results[$code] = ($results[$code] ?? 0) + 1;
}

// --- verify ----------------------------------------------------------------
DB::reconnect();
$variant->refresh();
$orders = Order::query()->where('email', 'like', "{$tag}-%")->count();
$movements = (int) DB::table('stock_movements')->where('product_variant_id', $variant->id)->sum('quantity_change');

printf("Succeeded: %d   Refused (sold out): %d   Errors: %d\n", $results[0], $results[2], $results[3]);
printf("Orders created: %d   Stock left: %d   Stock movements total: %d\n", $orders, $variant->stock_quantity, $movements);

$ok = $orders === $stock && $results[0] === $stock && $variant->stock_quantity === 0 && $movements === -$stock && $results[3] === 0;
echo $ok ? "PASS: no overselling.\n" : "FAIL: inventory is inconsistent!\n";

// --- cleanup ---------------------------------------------------------------
Order::query()->where('email', 'like', "{$tag}-%")->delete();
Cart::query()->whereIn('id', $carts)->delete();
$product->delete();
$category->delete();
$shipping->delete();

exit($ok ? 0 : 1);
