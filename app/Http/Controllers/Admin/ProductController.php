<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\HtmlSanitizer;
use App\Services\Inventory\InventoryService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['category', 'variants', 'primaryImage'])
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)));
            })
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('missing') === 'images', fn ($q) => $q->doesntHave('images'))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::query()->ordered()->get(),
        ]);
    }

    public function create()
    {
        $product = new Product(['status' => ProductStatus::Draft]);
        $product->setRelation('variants', collect([new ProductVariant(['name' => 'Default', 'is_active' => true, 'track_inventory' => true])]));

        return view('admin.products.form', ['product' => $product, 'categories' => Category::query()->ordered()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::query()->create($data['product']);
            $this->syncVariants($product, $data['variants'], $request);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created. Add photos below.');
    }

    public function edit(Product $product)
    {
        $product->load(['variants', 'images', 'category']);

        return view('admin.products.form', ['product' => $product, 'categories' => Category::query()->ordered()->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($product, $data, $request) {
            $product->update($data['product']);
            $this->syncVariants($product, $data['variants'], $request);
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product saved.');
    }

    public function archive(Product $product)
    {
        $product->update(['status' => ProductStatus::Archived]);

        return back()->with('status', "{$product->name} archived — hidden from the store but kept for order history.");
    }

    public function restore(Product $product)
    {
        $product->update(['status' => ProductStatus::Draft]);

        return back()->with('status', "{$product->name} restored as a draft.");
    }

    public function destroy(Product $product)
    {
        if (OrderItem::query()->where('product_id', $product->id)->exists()) {
            return back()->withErrors(['product' => "{$product->name} appears in past orders, so it can't be deleted. Archive it instead."]);
        }

        $paths = $product->images()->pluck('path');
        $product->delete();
        Storage::disk('public')->delete($paths->all());

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name'))]);

        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:50000'],
            'benefits' => ['nullable', 'string', 'max:50000'],
            'ingredients' => ['nullable', 'string', 'max:50000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:100', 'distinct'],
            'variants.*.price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'variants.*.sale_price' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'variants.*.sale_starts_at' => ['nullable', 'date'],
            'variants.*.sale_ends_at' => ['nullable', 'date', 'after_or_equal:variants.*.sale_starts_at'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.stock_original' => ['nullable', 'integer'],
            'variants.*.track_inventory' => ['boolean'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'variants.*.price.regex' => 'Enter prices like 69 or 69.00.',
            'variants.*.sale_price.regex' => 'Enter prices like 59 or 59.00.',
        ]);

        $variants = [];
        foreach (array_values($v['variants']) as $i => $row) {
            $price = Money::toCents($row['price']);
            $sale = Money::toCents($row['sale_price'] ?? null);
            if ($sale !== null && $sale >= $price) {
                throw ValidationException::withMessages(["variants.{$i}.sale_price" => 'The sale price must be lower than the regular price.']);
            }
            if (! empty($row['sku'])) {
                $clash = ProductVariant::query()->where('sku', $row['sku'])
                    ->when(! empty($row['id']), fn ($q) => $q->whereKeyNot($row['id']))->exists();
                if ($clash) {
                    throw ValidationException::withMessages(["variants.{$i}.sku" => 'This SKU is already used by another product.']);
                }
            }
            $variants[] = [
                'id' => $row['id'] ?? null,
                'attributes' => [
                    'name' => $row['name'],
                    'sku' => $row['sku'] ?: null,
                    'price' => $price,
                    'sale_price' => $sale,
                    'sale_starts_at' => $row['sale_starts_at'] ?? null,
                    'sale_ends_at' => $row['sale_ends_at'] ?? null,
                    'track_inventory' => (bool) ($row['track_inventory'] ?? false),
                    'is_active' => (bool) ($row['is_active'] ?? false),
                    'weight_grams' => $row['weight_grams'] ?? null,
                    'sort_order' => $i,
                ],
                'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
                'stock_original' => isset($row['stock_original']) ? (int) $row['stock_original'] : null,
            ];
        }

        return [
            'product' => [
                'name' => $v['name'],
                'slug' => $v['slug'],
                'category_id' => $v['category_id'] ?? null,
                'status' => $v['status'],
                'is_featured' => $request->boolean('is_featured'),
                'sort_order' => $v['sort_order'] ?? 0,
                'short_description' => $v['short_description'] ?? null,
                'description' => $this->sanitizer->clean($v['description'] ?? null),
                'benefits' => $this->sanitizer->clean($v['benefits'] ?? null),
                'ingredients' => $this->sanitizer->clean($v['ingredients'] ?? null),
                'meta_title' => $v['meta_title'] ?? null,
                'meta_description' => $v['meta_description'] ?? null,
            ],
            'variants' => $variants,
        ];
    }

    /** Create/update/delete variants. Stock edits are applied as a recorded delta, never a blind overwrite. */
    private function syncVariants(Product $product, array $rows, Request $request): void
    {
        $keep = [];
        foreach ($rows as $row) {
            $variant = $row['id'] ? $product->variants()->whereKey($row['id'])->first() : null;

            if ($variant) {
                $variant->update($row['attributes']);
                if ($row['stock'] !== null && $row['stock_original'] !== null && $row['stock'] !== $row['stock_original']) {
                    $this->inventory->adjust($variant, $row['stock'] - $row['stock_original'], 'adjustment', $request->user(), 'Edited on product form');
                }
            } else {
                $variant = $product->variants()->create($row['attributes']);
                if ($row['stock']) {
                    $this->inventory->setLevel($variant, $row['stock'], 'initial', $request->user());
                }
            }
            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep)->get()->each->delete();
    }
}
