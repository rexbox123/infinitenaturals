<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name' => 'Name: A–Z',
    ];

    public function index(Request $request)
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, Category $category)
    {
        abort_unless($category->is_active, 404);

        return $this->listing($request, $category);
    }

    private function listing(Request $request, ?Category $category)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'min' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'in_stock' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
        ]);

        // Current price per product = lowest active variant price, honouring live sales.
        $now = now();
        $priceSql = ProductVariant::query()
            ->selectRaw('MIN(CASE WHEN sale_price IS NOT NULL AND sale_price < price
                AND (sale_starts_at IS NULL OR sale_starts_at <= ?) AND (sale_ends_at IS NULL OR sale_ends_at > ?)
                THEN sale_price ELSE price END)', [$now, $now])
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true);

        $query = Product::query()->visible()
            ->select('products.*')
            ->selectSub($priceSql, 'current_price')
            ->with(['activeVariants', 'primaryImage', 'category']);

        if ($category) {
            $query->where('category_id', $category->id);
        }

        if ($term = trim($filters['q'] ?? '')) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('ingredients', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like));
            });
        }

        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', fn ($v) => $v->where('is_active', true)
                ->where(fn ($s) => $s->where('track_inventory', false)->orWhere('stock_quantity', '>', 0)));
        }

        if ($request->boolean('on_sale')) {
            $query->whereHas('variants', fn ($v) => $v->where('is_active', true)
                ->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price')
                ->where(fn ($s) => $s->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', $now))
                ->where(fn ($s) => $s->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', $now)));
        }

        $sort = $filters['sort'] ?? 'featured';
        match ($sort) {
            'newest' => $query->latest('products.created_at'),
            'price_asc' => $query->orderBy('current_price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('current_price')->orderBy('name'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name'),
        };

        // Price filtering happens on the computed current price. The catalogue is small,
        // so filtering the collection keeps the SQL portable across MySQL/PostgreSQL/SQLite.
        $min = isset($filters['min']) ? Money::toCents(number_format((float) $filters['min'], 2, '.', '')) : null;
        $max = isset($filters['max']) ? Money::toCents(number_format((float) $filters['max'], 2, '.', '')) : null;
        $all = $query->get()->filter(fn (Product $p) => ($min === null || $p->current_price >= $min)
            && ($max === null || $p->current_price <= $max))->values();
        $perPage = config('store.products_per_page');
        $page = max(1, (int) $request->query('page', 1));
        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(), $all->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('store.shop', [
            'products' => $products,
            'category' => $category,
            'categories' => Category::query()->active()->ordered()->get(),
            'filters' => $filters + ['sort' => $sort],
            'sorts' => self::SORTS,
        ]);
    }
}
