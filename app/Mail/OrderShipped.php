<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order {$this->order->number} has shipped");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-shipped', with: [
            'order' => $this->order->loadMissing('items'),
            'statusUrl' => route('orders.show', ['order' => $this->order->number, 'token' => $this->order->access_token]),
        ]);
    }
}
