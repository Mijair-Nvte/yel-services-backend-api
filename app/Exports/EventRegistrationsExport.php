<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventRegistrationsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $registrations;

    protected $event;

    public function __construct(Collection $registrations, $event = null)
    {
        $this->registrations = $registrations;
        $this->event = $event;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->registrations;
    }

    public function headings(): array
    {
        return [
            'Fecha de Registro',
            'Nombre del Cliente',
            'Correo',
            'Teléfono',
            'Origen (Campaña)',
            'Estatus de Asistencia',
            'Estatus Call Center',
            'Notas',
        ];
    }

  public function map($registration): array
    {
        $customerName = $registration->customer
            ? trim($registration->customer->first_name.' '.$registration->customer->last_name)
            : 'Cliente Desconocido';

        return [
            \Carbon\Carbon::parse($registration->created_at)->format('d/m/Y H:i'),
            $customerName,
            $registration->customer->email ?? 'N/A',
            $registration->customer->phone ?? 'N/A',
            $registration->source ?? 'Orgánico',
            $registration->attended ? 'Asistió' : 'Pendiente / Registrado',
            $registration->contact_status ?? 'Sin contactar',
            $registration->notes ?? 'Sin notas',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

 
}
