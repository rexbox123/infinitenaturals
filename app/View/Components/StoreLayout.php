<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StoreLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $canonical = null,
        public bool $noindex = false,
        public bool $overlayHeader = false,
    ) {}

    public function render(): View
    {
        return view('layouts.store');
    }
}
