<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('store.contact', [
            'page' => Page::query()->where('slug', 'contact-us')->first(),
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: real visitors never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', 'Thank you — we will be in touch soon.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::query()->create($data + ['ip_address' => $request->ip()]);

        if ($to = setting('store.email')) {
            Mail::to($to)->send(new ContactMessageReceived($message));
        }

        return redirect()->route('contact')->with('status', 'Thank you — we will be in touch soon.');
    }
}
