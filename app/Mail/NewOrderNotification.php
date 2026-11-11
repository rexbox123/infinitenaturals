<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New order {$this->order->number} — ".money($this->order->total));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-order', with: [
            'order' => $this->order->loadMissing('items'),
            'adminUrl' => route('admin.orders.show', $this->order),
        ]);
    }
}
