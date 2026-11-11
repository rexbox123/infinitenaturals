<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeReturnController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
| URLs for products, categories and info pages match the previous WordPress
| site (/product/…, /product-category/…, /about-us/…) to preserve SEO and links.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/product-category/{category:slug}', [ShopController::class, 'category'])->name('category.show');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/search', fn () => redirect()->route('shop', request()->only('q')))->name('search');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::post('/cart/discount', [CartController::class, 'applyDiscount'])->name('cart.discount.apply');
Route::delete('/cart/discount', [CartController::class, 'removeDiscount'])->name('cart.discount.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');

// Stripe redirects the customer back here. Both URLs are signed; payment state is
// always confirmed with Stripe server-side, never trusted from the URL.
// The return URL's signature is checked in the controller because Stripe appends ?session_id=… after signing.
Route::get('/checkout/payment/{order:number}/return', [StripeReturnController::class, 'return'])->name('checkout.stripe.return');
Route::get('/checkout/payment/{order:number}/cancel', [StripeReturnController::class, 'cancel'])->middleware('signed')->name('checkout.stripe.cancel');
Route::post('/orders/{order:number}/pay', [StripeReturnController::class, 'resume'])->name('orders.pay');
Route::post('/orders/{order:number}/retry', [StripeReturnController::class, 'retry'])->name('orders.retry');

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

Route::get('/track-order', [OrderStatusController::class, 'lookupForm'])->name('orders.lookup');
Route::post('/track-order', [OrderStatusController::class, 'lookup'])->middleware('throttle:order-lookup')->name('orders.lookup.submit');
Route::get('/orders/{order:number}', [OrderStatusController::class, 'show'])->name('orders.show');

Route::get('/contact-us', [ContactController::class, 'show'])->name('contact');
Route::post('/contact-us', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Old WordPress URLs.
Route::redirect('/my-account', '/account', 301);
Route::redirect('/shop/page/{n}', '/shop', 301);

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('dashboard');
    Route::get('/account/orders/{order:number}', [AccountController::class, 'order'])->name('account.orders.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Admin dashboard — permissions per role are defined in config/store.php
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'can:admin.access'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('can:orders.view')->group(function () {
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order:number}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order:number}/packing-slip', [Admin\OrderController::class, 'packingSlip'])->name('orders.packing-slip');
    });
    Route::middleware('can:orders.manage')->group(function () {
        Route::patch('orders/{order:number}/fulfillment', [Admin\OrderController::class, 'updateFulfillment'])->name('orders.fulfillment');
        Route::post('orders/{order:number}/notes', [Admin\OrderController::class, 'addNote'])->name('orders.notes');
        Route::post('orders/{order:number}/cash-collected', [Admin\OrderController::class, 'markCashCollected'])->name('orders.cash-collected');
        Route::post('orders/{order:number}/sync-payment', [Admin\OrderController::class, 'syncPayment'])->name('orders.sync-payment');
        Route::post('orders/{order:number}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order:number}/resend-confirmation', [Admin\OrderController::class, 'resendConfirmation'])->name('orders.resend');
        Route::post('orders/{order:number}/review', [Admin\OrderController::class, 'clearReview'])->name('orders.review');
    });
    Route::post('orders/{order:number}/refund', [Admin\OrderController::class, 'refund'])->middleware('can:orders.refund')->name('orders.refund');

    Route::middleware('can:inventory.manage')->group(function () {
        Route::get('inventory', [Admin\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/{variant}', [Admin\InventoryController::class, 'show'])->name('inventory.show');
        Route::post('inventory/{variant}/adjust', [Admin\InventoryController::class, 'adjust'])->name('inventory.adjust');
    });

    Route::middleware('can:catalog.manage')->group(function () {
        Route::resource('products', Admin\ProductController::class)->except('show');
        Route::post('products/{product}/archive', [Admin\ProductController::class, 'archive'])->name('products.archive');
        Route::post('products/{product}/restore', [Admin\ProductController::class, 'restore'])->name('products.restore');
        Route::post('products/{product}/images', [Admin\ProductImageController::class, 'store'])->name('products.images.store');
        Route::patch('products/{product}/images', [Admin\ProductImageController::class, 'update'])->name('products.images.update');
        Route::delete('products/{product}/images/{image}', [Admin\ProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::resource('categories', Admin\CategoryController::class)->except('show');
    });

    Route::middleware('can:customers.manage')->group(function () {
        Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/edit', [Admin\CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('can:promotions.manage')->group(function () {
        Route::resource('discounts', Admin\DiscountCodeController::class)->except('show');
        Route::resource('banners', Admin\BannerController::class)->except('show');
    });

    Route::middleware('can:content.manage')->group(function () {
        Route::resource('pages', Admin\PageController::class)->except('show');
        Route::get('homepage', [Admin\HomepageController::class, 'edit'])->name('homepage.edit');
        Route::put('homepage', [Admin\HomepageController::class, 'update'])->name('homepage.update');
    });

    Route::middleware('can:messages.view')->group(function () {
        Route::get('messages', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [Admin\ContactMessageController::class, 'show'])->name('messages.show');
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::resource('shipping-methods', Admin\ShippingMethodController::class)->except('show')->parameters(['shipping-methods' => 'shippingMethod']);
        Route::resource('tax-rates', Admin\TaxRateController::class)->except('show')->parameters(['tax-rates' => 'taxRate']);
    });

    Route::middleware('can:users.manage')->group(function () {
        Route::resource('staff', Admin\StaffController::class)->except('show')->parameters(['staff' => 'staff']);
    });
});

// CMS pages (About, FAQ, policies, …). Registered last so it never shadows other routes.
Route::get('/{page:slug}', [PageController::class, 'show'])->where('page', '[a-z0-9\-]+')->name('page.show');
