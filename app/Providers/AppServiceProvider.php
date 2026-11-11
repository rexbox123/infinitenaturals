<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Payments\StripeApi;
use App\Services\Payments\StripeSdkApi;
use App\Services\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->scoped(CartService::class);
        $this->app->singleton(StripeApi::class, fn () => new StripeSdkApi(config('services.stripe.secret')));
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Role-based permissions (config/store.php → permissions).
        foreach (array_keys(config('store.permissions')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('order-lookup', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Keep the shopper's cart when they sign in or register.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                app(CartService::class)->attachToUser($event->user, session('cart_token'));
            }
        });

        View::composer('layouts.store', function ($view) {
            $view->with([
                'navCategories' => Category::query()->active()->ordered()->get(),
                'announcements' => Banner::query()->live('announcement')->get(),
                'footerPages' => Page::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
                'cartCount' => app(CartService::class)->current()?->itemCount() ?? 0,
            ]);
        });
    }
}
