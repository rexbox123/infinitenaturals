<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;

class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = collect([route('home'), route('shop'), route('contact')])
            ->concat(Category::query()->active()->get()->map(fn ($c) => route('category.show', $c)))
            ->concat(Product::query()->visible()->get()->map(fn ($p) => route('product.show', $p)))
            ->concat(Page::query()->published()->where('slug', '!=', 'contact-us')->get()->map(fn ($p) => route('page.show', $p)));

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml']);
    }
}
