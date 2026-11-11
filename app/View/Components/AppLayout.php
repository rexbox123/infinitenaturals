<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** Breeze account pages (profile, verification) render inside the storefront chrome. */
class AppLayout extends Component
{
    public function render(): View
    {
        return view('layouts.app');
    }
}
