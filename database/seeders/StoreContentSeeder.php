<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * Real store content taken from the current infinitenaturals.com site.
 * Safe for production and safe to re-run: existing records (and any edits
 * made in the admin) are never overwritten.
 *
 * Deliberately NOT seeded because the site does not state them:
 * stock levels (0 = sold out until entered), SKUs, shipping rates, tax rates.
 */
class StoreContentSeeder extends Seeder
{
    public function run(Settings $settings): void
    {
        $catalog = json_decode(file_get_contents(__DIR__.'/data/catalog.json'), true, flags: JSON_THROW_ON_ERROR);

        $categories = [];
        foreach ($catalog['categories'] as $c) {
            $categories[$c['slug']] = Category::query()->firstOrCreate(
                ['slug' => $c['slug']],
                ['name' => $c['name'], 'sort_order' => $c['sort_order'], 'is_active' => true],
            );
        }

        foreach ($catalog['products'] as $p) {
            $product = Product::query()->firstOrCreate(['slug' => $p['slug']], [
                'category_id' => $categories[$p['category']]->id,
                'name' => $p['name'],
                'short_description' => $p['short_description'],
                'description' => $p['description'],
                'benefits' => $p['benefits'],
                'ingredients' => $p['ingredients'],
                'status' => ProductStatus::Active,
                'is_featured' => $p['featured'],
                'sort_order' => $p['sort_order'],
            ]);

            if ($product->wasRecentlyCreated) {
                $product->variants()->create([
                    'name' => $p['variant_name'],
                    'price' => $p['price_cents'],
                    'track_inventory' => true,
                    'is_active' => true,
                ]);
            }
        }

        foreach (require __DIR__.'/data/pages.php' as $page) {
            $model = Page::query()->firstOrNew(['slug' => $page['slug']]);
            if (! $model->exists) {
                $model->fill($page + ['is_published' => true, 'show_in_footer' => true]);
                $model->is_system = true;
                $model->save();
            }
        }

        if (! Banner::query()->exists()) {
            Banner::query()->create([
                'placement' => 'announcement',
                'title' => 'Free USPS Standard shipping on orders over $50',
                'button_label' => 'Details',
                'button_url' => '/shipping-policy',
            ]);
            Banner::query()->create([
                'placement' => 'hero',
                'eyebrow' => 'As seen in the feature film “An Accidental Zombie (Named Ted)”',
                'title' => 'An Essence of Natural Beauty',
                'subtitle' => 'Lifting your natural beauty to infinity',
                'button_label' => 'Shop now',
                'button_url' => '/shop',
                'sort_order' => 1,
            ]);
            Banner::query()->create([
                'placement' => 'promo',
                'title' => 'Say Goodbye to Dry Skin',
                'subtitle' => 'Our Natural Soaps are enriched with shea butter for the extra nourishment your skin deserves.',
                'button_label' => 'Shop body soaps',
                'button_url' => '/product-category/body-soaps',
                'sort_order' => 1,
            ]);
        }

        // Homepage copy from the current site.
        $homepage = [
            'home.intro_body' => 'Ditch the chemicals and embrace the power of nature! Natural and organic skincare products are taking the beauty world by storm, and for good reason. These gentle formulations packed with botanical goodness offer a wealth of benefits for your skin, promoting a healthy glow and a more sustainable approach to self-care. From soothing sensitive skin to combating signs of aging, discover how natural and organic ingredients can elevate your skincare routine and nurture your natural beauty. Welcome to Infinite Naturals.',
            'home.mission_body' => 'Infinite Naturals is passionate about creating and providing quality natural organic products that take care of your skin and have less impact on our planet.',
            'home.featured_subtitle' => 'Rejuvenate your younger look. Be the beautiful face everyone wants to be.',
            'home.contact_body' => 'We value you as we value the essence of beauty. Let’s talk about how we can improve your beauty regimen. Feel free to ask us about all your beauty inquiries. Contact us by email or phone. We can’t wait to hear from you!',
        ];
        $existing = $settings->all();
        $settings->setMany(array_filter($homepage, fn ($key) => ! array_key_exists($key, $existing), ARRAY_FILTER_USE_KEY));

        // Shipping methods named in the current policy. Rates are unknown, so they start
        // INACTIVE; the owner sets prices and activates them in Admin → Shipping.
        $methods = [
            ['name' => 'USPS Standard', 'description' => 'Processing and shipment normally within 3–10 business days.', 'free_over' => 5000, 'sort_order' => 1],
            ['name' => 'USPS Priority Mail', 'description' => 'Prioritized processing; same business day if ordered before the daily cut-off.', 'free_over' => null, 'sort_order' => 2],
            ['name' => 'USPS Priority Mail Express', 'description' => 'Fastest option; same business day if ordered before the daily cut-off.', 'free_over' => null, 'sort_order' => 3],
        ];
        foreach ($methods as $m) {
            ShippingMethod::query()->firstOrCreate(['name' => $m['name']], $m + ['rate' => 0, 'is_active' => false]);
        }
    }
}
