<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

// Extendemos de 'Pivot' en lugar de 'Model' para que funcione perfecto con belongsToMany
class OrgEventTicketType extends Pivot 
{
    use HasFactory;

    // Le indicamos explícitamente el nombre de la tabla
    protected $table = 'org_event_ticket_types';

    // Como es pivot, a veces Laravel asume que no hay ID, se lo confirmamos:
    public $incrementing = true;

    protected $fillable = [
        'org_company_id',
        'org_event_id',
        'org_ticket_type_id',
        'capacity',
        'price',
        'metadata',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'price' => 'decimal:2',
        'metadata' => 'array', // Casteo del JSON
    ];

    // Relaciones por si consultas este modelo directamente
    public function event()
    {
        return $this->belongsTo(OrgEvent::class, 'org_event_id');
    }

    public function ticketType()
    {
        return $this->belongsTo(OrgTicketType::class, 'org_ticket_type_id');
    }
}