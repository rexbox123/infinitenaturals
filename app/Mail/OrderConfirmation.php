<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.setting('store.name')." order {$this->order->number}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-confirmation', with: [
            'order' => $this->order->loadMissing('items'),
            'statusUrl' => route('orders.show', ['order' => $this->order->number, 'token' => $this->order->access_token]),
        ]);
    }
}
