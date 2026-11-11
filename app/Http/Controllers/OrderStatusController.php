<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\StripeCheckout;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    /**
     * Order status page. Access requires the order's secret token (from the
     * confirmation email/redirect), ownership, or staff permission.
     */
    public function show(Request $request, Order $order)
    {
        $token = (string) $request->query('token');
        $allowed = ($token !== '' && hash_equals($order->access_token, $token))
            || ($request->user() && $order->user_id === $request->user()->id)
            || $request->user()?->can('orders.view');

        abort_unless($allowed, 404);

        $order->load(['items.variant.product.primaryImage', 'events' => fn ($q) => $q->where('is_customer_visible', true)]);

        return view('store.order', [
            'order' => $order,
            'token' => $order->access_token,
            'canResume' => $order->isAwaitingOnlinePayment() && StripeCheckout::isConfigured(),
        ]);
    }

    public function lookupForm()
    {
        return view('store.order-lookup');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::query()
            ->where('number', strtoupper(trim($data['number'])))
            ->where('email', strtolower(trim($data['email'])))
            ->first();

        if (! $order) {
            return back()->withErrors(['number' => 'We could not find an order with that number and email address.'])->withInput();
        }

        return redirect()->route('orders.show', ['order' => $order->number, 'token' => $order->access_token]);
    }
}
