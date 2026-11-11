<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['slug', 'title', 'body', 'meta_description', 'is_published', 'show_in_footer', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_system' => 'boolean', 'show_in_footer' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function url(): string
    {
        return $this->slug === 'contact-us' ? route('contact') : route('page.show', $this);
    }
}
