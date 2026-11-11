<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return view('account.index', [
            'orders' => $request->user()->orders()->withCount('items')->latest('id')->paginate(10),
        ]);
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return redirect()->route('orders.show', ['order' => $order->number, 'token' => $order->access_token]);
    }
}
