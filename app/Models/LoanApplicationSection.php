<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'section_id',
        'status',
        'data',
    ];

    /**
     * Casteo automático del campo JSON.
     * Al consultar $section->data obtendrás un arreglo limpio de PHP automáticamente.
     */
    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Relación inversa con la solicitud contenedora
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
