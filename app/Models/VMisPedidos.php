<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VMisPedidos extends Model
{
    // Indicamos explícitamente el nombre de la vista
    protected $table = 'v_mis_pedidos';

    // Como es una vista, no tiene una llave primaria tradicional o auto-incremental que Laravel pueda manejar igual
    // Esto evita errores si intentas hacer un find() accidentalmente
    protected $primaryKey = null;
    public $incrementing = false;

    // Las vistas no manejan timestamps de Laravel por defecto en las consultas de inserción
    public $timestamps = false;
    
    // Convertimos la fecha que viene de la base de datos a una instancia de Carbon para poder formatearla fácil
    protected $casts = [
        'purchase_date' => 'datetime',
    ];
}