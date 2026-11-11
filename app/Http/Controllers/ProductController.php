<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        // Staff may preview drafts; everyone else only sees active products.
        abort_unless($product->isVisible() || request()->user()?->can('catalog.manage'), 404);

        $product->load(['activeVariants', 'images', 'category']);

        $related = Product::query()->visible()
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['activeVariants', 'primaryImage', 'category'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->take(4)->get();

        if ($related->count() < 4) {
            $related = $related->concat(
                Product::query()->visible()->whereNotIn('id', $related->pluck('id')->push($product->id))
                    ->with(['activeVariants', 'primaryImage', 'category'])
                    ->orderByDesc('is_featured')->take(4 - $related->count())->get()
            );
        }

        return view('store.product', compact('product', 'related'));
    }
}
