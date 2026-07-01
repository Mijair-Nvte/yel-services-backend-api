<?php

namespace App\Mail\Store;

use App\Models\OrgSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServicePurchaseSuccessMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * El constructor recibe la venta y el nombre del cliente.
     * Al ser 'public', se comparten automáticamente con la vista Blade.
     */
    public function __construct(
        public OrgSale $sale,
        public string $customerName
    ) {}

    /**
     * Configuración del remitente y asunto.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Gracias por tu compra! Tu servicio está confirmado',
        );
    }

    /**
     * Define la vista correspondiente al correo de éxito.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store.purchase-success',
        );
    }
}