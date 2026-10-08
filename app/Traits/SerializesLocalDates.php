<?php

namespace App\Traits;

trait SerializesLocalDates
{
    /**
     * Sobrescribe el serializador de fechas de Laravel.
     * Evita que se convierta a UTC al exportar a JSON (API).
     * Retorna la hora local exacta de la base de datos (Ej: "2026-10-08T18:30:00")
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d\TH:i:s');
    }
}