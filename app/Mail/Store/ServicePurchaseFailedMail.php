<?php

namespace App\Mail\Store;

use App\Models\OrgSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServicePurchaseFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Recibe los datos de la venta que falló para poder
     * ofrecerle soporte o detalles específicos al cliente.
     */
    public function __construct(
        public OrgSale $sale,
        public string $customerName
    ) {}

    /**
     * Configuración del asunto para alertar al cliente de forma sutil.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Problema con el pago de tu servicio',
        );
    }

    /**
     * Define la vista correspondiente al correo de fallo.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store.purchase-failed',
        );
    }
}