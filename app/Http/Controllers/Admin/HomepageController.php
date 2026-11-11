<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\Request;

class HomepageController extends Controller
{
    public const FIELDS = [
        'home.intro_title' => 'Introduction heading',
        'home.intro_body' => 'Introduction text',
        'home.mission_title' => 'Mission heading',
        'home.mission_body' => 'Mission text',
        'home.featured_title' => 'Featured products heading',
        'home.featured_subtitle' => 'Featured products subheading',
        'home.contact_title' => 'Contact section heading',
        'home.contact_body' => 'Contact section text',
    ];

    public function edit(Settings $settings)
    {
        return view('admin.homepage', ['fields' => self::FIELDS, 'settings' => $settings]);
    }

    public function update(Request $request, Settings $settings)
    {
        $rules = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $rules[str_replace('.', '_', $key)] = ['nullable', 'string', str_ends_with($key, '_body') ? 'max:5000' : 'max:255'];
        }
        $data = $request->validate($rules);

        $settings->setMany(collect(self::FIELDS)->keys()
            ->mapWithKeys(fn ($key) => [$key => $data[str_replace('.', '_', $key)] ?? null])->all());

        return back()->with('status', 'Homepage saved. Featured products are chosen on each product (“Feature on homepage”); slides and promotions under Banners.');
    }
}
