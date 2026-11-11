<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Product::query()->visible()->where('is_featured', true)
            ->with(['activeVariants', 'primaryImage', 'category'])
            ->orderBy('sort_order')->orderBy('name')->take(8)->get();

        if ($featured->isEmpty()) {
            $featured = Product::query()->visible()->with(['activeVariants', 'primaryImage', 'category'])
                ->orderBy('sort_order')->take(4)->get();
        }

        return view('store.home', [
            'heroes' => Banner::query()->live('hero')->get(),
            'promos' => Banner::query()->live('promo')->get(),
            'categories' => Category::query()->active()->ordered()
                ->withCount(['products' => fn ($q) => $q->visible()])->get(),
            'featured' => $featured,
        ]);
    }
}
