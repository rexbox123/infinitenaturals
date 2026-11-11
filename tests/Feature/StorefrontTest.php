<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use Database\Seeders\StoreContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreContentSeeder::class);
    }

    public function test_home_page_shows_brand_content_and_featured_products(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('An Essence of Natural Beauty')
            ->assertSee('Rejuvenating Serum')
            ->assertSee('Our Mission')
            ->assertSee('833-558-5503');
    }

    public function test_shop_lists_all_seeded_products_with_real_prices(): void
    {
        $this->get('/shop')->assertOk()
            ->assertSee('Hyaluronic Cleanser')->assertSee('$37.00')
            ->assertSee('Rose and Peony 5.3 oz')->assertSee('$8.50')
            ->assertSee('10 products');
    }

    public function test_search_filters_by_name_and_ingredient(): void
    {
        $this->get('/shop?q=honeysuckle')->assertOk()->assertSee('Shower Gel Honeysuckle')->assertDontSee('Hyaluronic Toner');
        $this->get('/shop?q=Kakadu')->assertOk()->assertSee('Rejuvenating Serum')->assertDontSee('Heart Soap Rose');
    }

    public function test_price_filter_and_sorting(): void
    {
        $response = $this->get('/shop?max=10&sort=price_asc')->assertOk();
        $response->assertSee('Heart Soap Rose')->assertDontSee('Multi Vitamin Serum');
        $response->assertSeeInOrder(['Rose and', 'Heart Soap Rose']); // $8.50 before $9.99
    }

    public function test_category_page_uses_old_wordpress_url(): void
    {
        $this->get('/product-category/face-serums')->assertOk()
            ->assertSee('Multi Vitamin Serum')->assertDontSee('Heart Soap Rose');
    }

    public function test_product_page_shows_tabs_price_and_related_products(): void
    {
        $this->get('/product/rejuvenating-serum')->assertOk()
            ->assertSee('$69.00')
            ->assertSee('Benefits')
            ->assertSee('Ingredients')
            ->assertSee('Sodium Ascorbyl Phosphate')
            ->assertSee('You may also like')
            ->assertSee('Multi Vitamin Serum');
    }

    public function test_sold_out_products_cannot_be_added(): void
    {
        $variant = Product::query()->where('slug', 'heart-soap-rose')->first()->variants->first();
        $this->get('/product/heart-soap-rose')->assertOk()->assertSee('Sold out');
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertSessionHasErrors('quantity');
    }

    public function test_draft_and_archived_products_are_hidden(): void
    {
        $product = Product::query()->where('slug', 'heart-soap-rose')->first();
        $product->update(['status' => ProductStatus::Archived]);

        $this->get('/product/heart-soap-rose')->assertNotFound();
        $this->get('/shop')->assertDontSee('Heart Soap Rose');
    }

    public function test_policy_pages_from_the_current_site_are_available(): void
    {
        foreach (['about-us' => 'French-milled', 'shipping-policy' => 'APO or FPO', 'returns-policy' => '30 days',
            'privacy-policy' => 'cookies', 'terms-and-conditions' => 'Limitation of Liability', 'faq' => 'Where do you ship'] as $slug => $text) {
            $this->get("/{$slug}")->assertOk()->assertSee($text);
        }
        $this->get('/contact-us')->assertOk()->assertSee('info@infinitenaturals.com')->assertSee('Send us a message');
    }

    public function test_contact_form_stores_and_emails_message(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->post('/contact-us', ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello'])->assertRedirect('/contact-us');
        $this->assertDatabaseHas('contact_messages', ['email' => 'ann@example.com']);
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\ContactMessageReceived::class);
    }

    public function test_contact_honeypot_drops_spam(): void
    {
        $this->post('/contact-us', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'spam', 'website' => 'x'])->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_sitemap_lists_products(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/product/rejuvenating-serum'), false);
    }
}
