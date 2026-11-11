<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        $next = (int) $product->images()->max('sort_order') + 1;
        foreach ($request->file('images') as $file) {
            $product->images()->create([
                'path' => $file->store("products/{$product->id}", 'public'),
                'alt' => $product->name,
                'sort_order' => $next++,
            ]);
        }

        return back()->with('status', 'Photos uploaded.');
    }

    /** Update alt text and order (first image is the main photo). */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'images' => ['array'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['images'] ?? [] as $id => $row) {
            $product->images()->whereKey($id)->update([
                'alt' => $row['alt'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ]);
        }

        return back()->with('status', 'Photos updated.');
    }

    public function destroy(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('status', 'Photo removed.');
    }
}
