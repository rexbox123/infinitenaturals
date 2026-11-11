<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Lavender Bar',
            'category_id' => Category::factory()->create()->id,
            'status' => 'active',
            'is_featured' => '1',
            'short_description' => 'A bar.',
            'description' => '<p>Good <script>alert(1)</script><strong>soap</strong></p>',
            'variants' => [
                ['name' => '5.3 oz', 'sku' => 'IN-LAV-150', 'price' => '8.50', 'sale_price' => '', 'stock' => '12', 'track_inventory' => '1', 'is_active' => '1'],
                ['name' => '3.5 oz', 'sku' => 'IN-LAV-100', 'price' => '6', 'sale_price' => '5', 'stock' => '4', 'track_inventory' => '1', 'is_active' => '1'],
            ],
        ], $overrides);
    }

    public function test_manager_creates_product_with_variants_and_initial_stock(): void
    {
        $this->actingAs($this->user('manager'))->post(route('admin.products.store'), $this->payload())->assertRedirect();

        $product = Product::query()->with('variants')->where('slug', 'lavender-bar')->sole();
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertStringNotContainsString('<script', $product->description);
        $this->assertStringContainsString('<strong>soap</strong>', $product->description);
        $this->assertCount(2, $product->variants);
        $this->assertSame(850, $product->variants[0]->price);
        $this->assertSame(500, $product->variants[1]->sale_price);
        $this->assertSame(12, $product->variants[0]->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $product->variants[0]->id, 'quantity_change' => 12, 'reason' => 'initial']);

        $this->get('/product/lavender-bar')->assertOk()->assertSee('5.3 oz')->assertSee('$8.50');
    }

    public function test_validation_rejects_sale_price_above_price_and_duplicate_sku(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload(['variants' => [1 => ['sale_price' => '9']]]))
            ->assertSessionHasErrors('variants.1.sale_price');

        $this->variant(1000, 1, ['sku' => 'IN-LAV-150']);
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload())->assertSessionHasErrors('variants.0.sku');
    }

    public function test_editing_stock_applies_a_delta_so_concurrent_sales_are_not_lost(): void
    {
        $admin = $this->user('admin');
        $variant = $this->variant(1000, 10);
        $product = $variant->product;

        // Form was opened when stock was 10; a sale then brought it to 9; admin adds 5 → 14, not 15.
        $variant->forceFill(['stock_quantity' => 9])->save();
        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'slug' => $product->slug, 'status' => 'active',
            'variants' => [['id' => $variant->id, 'name' => 'Default', 'price' => '10.00', 'stock' => '15', 'stock_original' => '10', 'track_inventory' => '1', 'is_active' => '1']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(14, $variant->fresh()->stock_quantity);
    }

    public function test_archive_restore_and_delete_rules(): void
    {
        $admin = $this->user('admin');
        $variant = $this->variant();
        $product = $variant->product;

        $this->actingAs($admin)->post(route('admin.products.archive', $product))->assertRedirect();
        $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
        $this->actingAs($admin)->post(route('admin.products.restore', $product));
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);

        $order = Order::factory()->create();
        $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);
        $this->actingAs($admin)->delete(route('admin.products.destroy', $product))->assertSessionHasErrors('product');
        $this->assertModelExists($product);

        $unsold = $this->variant()->product;
        $this->actingAs($admin)->delete(route('admin.products.destroy', $unsold))->assertRedirect(route('admin.products.index'));
        $this->assertModelMissing($unsold);
    }

    public function test_image_upload_and_delete(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        $product = $this->variant()->product;

        $this->actingAs($admin)->post(route('admin.products.images.store', $product), [
            'images' => [UploadedFile::fake()->image('serum.jpg', 800, 800)],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $image = $product->images()->sole();
        Storage::disk('public')->assertExists($image->path);

        $this->actingAs($admin)->post(route('admin.products.images.store', $product), [
            'images' => [UploadedFile::fake()->create('evil.php', 10, 'application/x-php')],
        ])->assertSessionHasErrors();

        $this->actingAs($admin)->delete(route('admin.products.images.destroy', [$product, $image]));
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_inventory_adjustments_are_logged_and_cannot_go_negative(): void
    {
        $staff = $this->user('staff');
        $variant = $this->variant(1000, 3);

        $this->actingAs($staff)->post(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 7, 'reason' => 'restock'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(10, $variant->fresh()->stock_quantity);

        $this->actingAs($staff)->post(route('admin.inventory.adjust', $variant), ['mode' => 'remove', 'quantity' => 11, 'reason' => 'damaged'])->assertSessionHasErrors('quantity');
        $this->assertSame(10, $variant->fresh()->stock_quantity);

        $this->actingAs($staff)->post(route('admin.inventory.adjust', $variant), ['mode' => 'set', 'quantity' => 6, 'reason' => 'adjustment', 'note' => 'Stocktake']);
        $this->assertSame(6, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $variant->id, 'quantity_change' => -4, 'quantity_after' => 6, 'note' => 'Stocktake', 'user_id' => $staff->id]);
    }

    public function test_pages_and_homepage_are_editable_and_sanitized(): void
    {
        $this->seed(\Database\Seeders\StoreContentSeeder::class);
        $manager = $this->user('manager');
        $page = \App\Models\Page::query()->where('slug', 'faq')->sole();

        $this->actingAs($manager)->put(route('admin.pages.update', $page), [
            'title' => 'FAQ', 'body' => '<p>Updated <a href="javascript:alert(1)">x</a></p><img src=x onerror=alert(1)>', 'is_published' => '1', 'show_in_footer' => '1',
        ])->assertRedirect();
        $page->refresh();
        $this->assertSame('faq', $page->slug, 'system page keeps its URL');
        $this->assertStringNotContainsString('javascript:', $page->body);
        $this->assertStringNotContainsString('onerror', $page->body);
        $this->actingAs($manager)->delete(route('admin.pages.destroy', $page))->assertForbidden();

        $this->actingAs($manager)->put(route('admin.homepage.update'), ['home_mission_title' => 'Our Promise', 'home_mission_body' => 'Kind to skin.'])->assertRedirect();
        $this->get('/')->assertSee('Our Promise')->assertSee('Kind to skin.');
    }
}
