<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(Page $page)
    {
        if ($page->slug === 'contact-us') {
            return redirect()->route('contact');
        }

        abort_unless($page->is_published || request()->user()?->can('content.manage'), 404);

        return view('store.page', compact('page'));
    }
}
