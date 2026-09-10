<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class OrgEventTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uid',
        'org_company_id',
        'org_event_id',
        'org_event_ticket_type_id',
        'org_customer_id',
        'org_sale_id',
        'status',
        'reserved_until',
        'attendee_first_name',
        'attendee_last_name',
        'attendee_email',
        'scanned_at',
    ];

    protected $casts = [
        'reserved_until' => 'datetime',
        'scanned_at' => 'datetime',
    ];

    // --- RELACIONES ---

    public function company()
    {
        return $this->belongsTo(OrgCompany::class, 'org_company_id');
    }

    public function event()
    {
        return $this->belongsTo(OrgEvent::class, 'org_event_id');
    }

    public function ticketType()
    {
        return $this->belongsTo(OrgEventTicketType::class, 'org_event_ticket_type_id');
    }

    public function customer()
    {
        return $this->belongsTo(OrgCustomer::class, 'org_customer_id');
    }

    public function sale()
    {
        return $this->belongsTo(OrgSale::class, 'org_sale_id');
    }

    // --- SCOPES Y HELPERS PARA EL MANEJO DE TIEMPO Y ESTADOS ---

    /**
     * Scope para obtener boletos que realmente están disponibles para la venta.
     * (Incluye los que están marcados como 'available' O los que estaban 'reserved' pero su tiempo ya expiró).
     */
    public function scopeAvailableForPurchase($query)
    {
        return $query->where('status', 'available')
            ->orWhere(function ($q) {
                $q->where('status', 'reserved')
                  ->where('reserved_until', '<', Carbon::now());
            });
    }

    /**
     * Verifica si una reserva temporal ya expiró.
     */
    public function isReservationExpired(): bool
    {
        if ($this->status !== 'reserved') {
            return false;
        }

        return $this->reserved_until && $this->reserved_until->isPast();
    }

    /**
     * Obtiene el nombre del asistente o recurre al comprador.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->attendee_first_name) {
            return "{$this->attendee_first_name} {$this->attendee_last_name}";
        }

        if ($this->relationLoaded('customer') && $this->customer) {
            return "{$this->customer->first_name} {$this->customer->last_name}";
        }

        return 'Asistente por asignar';
    }
}