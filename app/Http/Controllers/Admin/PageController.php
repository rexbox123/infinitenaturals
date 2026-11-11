<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::query()->orderByDesc('is_system')->orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['is_published' => false, 'show_in_footer' => true])]);
    }

    public function store(Request $request)
    {
        $page = new Page($this->validated($request));
        $page->save();

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $page->update($this->validated($request, $page));

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    public function destroy(Page $page)
    {
        abort_if($page->is_system, 403, 'Built-in pages cannot be deleted. Unpublish it instead.');
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        // System pages keep their URL so menus and links keep working.
        if ($page?->is_system) {
            $request->merge(['slug' => $page->slug]);
        } else {
            $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('title'))]);
        }

        $reserved = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => explode('/', $r->uri())[0])->filter(fn ($s) => $s && ! str_contains($s, '{'))->unique()->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('pages', 'slug')->ignore($page?->id), Rule::notIn($page?->is_system ? [] : $reserved)],
            'body' => ['nullable', 'string', 'max:100000'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], ['slug.not_in' => 'That address is used by another part of the site.']);

        return [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'body' => $this->sanitizer->clean($data['body'] ?? null),
            'meta_description' => $data['meta_description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => $request->boolean('is_published'),
            'show_in_footer' => $request->boolean('show_in_footer'),
        ];
    }
}
