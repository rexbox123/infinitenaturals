<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    public function index()
    {
        return view('admin.banners.index', [
            'banners' => Banner::query()->orderBy('placement')->orderBy('sort_order')->get()->groupBy('placement'),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.banners.form', ['banner' => new Banner([
            'placement' => $request->query('placement', 'hero'),
            'is_active' => true,
        ])]);
    }

    public function store(Request $request)
    {
        Banner::query()->create($this->validated($request));

        return redirect()->route('admin.banners.index')->with('status', 'Banner created.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.form', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->update($this->validated($request, $banner));

        return redirect()->route('admin.banners.index')->with('status', 'Banner saved.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();

        return back()->with('status', 'Banner deleted.');
    }

    private function validated(Request $request, ?Banner $banner = null): array
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(array_keys(Banner::PLACEMENTS))],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_image' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], ['button_url.regex' => 'Links must start with / (a page on this site) or https://.']);

        $attributes = collect($data)->except(['image', 'remove_image'])->all();
        $attributes['sort_order'] = $data['sort_order'] ?? 0;
        $attributes['is_active'] = $request->boolean('is_active');

        if ($request->boolean('remove_image') && $banner?->image_path) {
            Storage::disk('public')->delete($banner->image_path);
            $attributes['image_path'] = null;
        }
        if ($request->hasFile('image')) {
            $attributes['image_path'] = $request->file('image')->store('banners', 'public');
        }

        return $attributes;
    }
}
