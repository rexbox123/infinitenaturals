<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\StripeCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeReturnController extends Controller
{
    public function __construct(private readonly StripeCheckout $stripe) {}

    /** Customer returns from Stripe after paying. Confirm with Stripe — never trust the redirect alone. */
    public function return(Request $request, Order $order)
    {
        abort_unless(
            $order->stripe_checkout_session_id && hash_equals($order->stripe_checkout_session_id, (string) $request->query('session_id')),
            404,
        );

        try {
            $this->stripe->refresh($order);
        } catch (Throwable $e) {
            // The webhook will still update the order; show the "confirming" state meanwhile.
            Log::warning('Stripe return verification failed', ['order' => $order->number, 'error' => $e->getMessage()]);
        }

        $message = match ($order->payment_status) {
            PaymentStatus::Paid => 'Thank you! Your payment was successful and your order is confirmed.',
            PaymentStatus::Processing => 'Thank you! Your payment is processing. We will email you once it clears.',
            default => 'We are confirming your payment with our payment provider. This page will update shortly.',
        };

        return redirect()->route('orders.show', ['order' => $order->number, 'token' => $order->access_token])
            ->with('status', $message);
    }

    /** Customer clicked "back" on the Stripe page. */
    public function cancel(Order $order)
    {
        if ($this->stripe->cancel($order)) {
            CheckoutController::restoreCart($order);

            return redirect()->route('cart.show')
                ->with('status', 'Payment was cancelled and you have not been charged. Your items are back in your cart.');
        }

        return redirect()->route('orders.show', ['order' => $order->number, 'token' => $order->access_token]);
    }

    /** "Complete payment" button on a pending order. */
    public function resume(Request $request, Order $order)
    {
        $this->authorizeToken($request, $order);

        if ($url = $this->stripe->resumeUrl($order)) {
            return redirect()->away($url);
        }

        return redirect()->route('orders.show', ['order' => $order->number, 'token' => $order->access_token])
            ->with('status', 'This payment session is no longer open. You can start a new checkout from your cart.');
    }

    /** "Try again" on a failed/cancelled order: refill the cart and go to checkout. */
    public function retry(Request $request, Order $order)
    {
        $this->authorizeToken($request, $order);
        abort_unless(in_array($order->payment_status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true), 409);

        CheckoutController::restoreCart($order);

        return redirect()->route('checkout.show');
    }

    private function authorizeToken(Request $request, Order $order): void
    {
        abort_unless(hash_equals($order->access_token, (string) $request->input('token')), 403);
    }
}
