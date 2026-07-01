<?php

namespace App\Mail\Store;

use App\Models\OrgSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InternalSaleNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param OrgSale $sale Registro completo de la venta.
     * @param string $recipientName Nombre de quien recibe (Admin o Afiliado).
     * @param string $roleType Tipo de rol para condicionar textos en el Blade ('Administrador' o 'Afiliado').
     */
    public function __construct(
        public OrgSale $sale,
        public string $recipientName,
        public string $roleType
    ) {}

    /**
     * Asunto dinámico que muestra el nombre del producto directamente.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔔 Nueva venta registrada: ' . $this->sale->product_name,
        );
    }

    /**
     * Apunta a la plantilla de notificaciones internas.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store.internal-notification',
        );
    }
}