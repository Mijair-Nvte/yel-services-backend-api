<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $sales;

    public function __construct(Collection $sales)
    {
        $this->sales = $sales;
    }

  public function collection(): \Illuminate\Support\Collection
    {
        return $this->sales;
    }

    // 1. Aquí definimos las cabeceras (Agregamos Correo y Teléfono)
    public function headings(): array
    {
        return [
            'Fecha de Venta',
            'Cliente',
            'Correo Cliente',
            'Teléfono Cliente',
            'Servicio / Producto',
            'Monto Bruto',
            'Vendedor',
            'Comisión',
            'Estatus'
        ];
    }

    // 2. Aquí mapeamos los datos de la BD a las columnas del Excel
    public function map($sale): array
    {
        $customerName = $sale->customer 
            ? trim($sale->customer->first_name . ' ' . $sale->customer->last_name) 
            : 'Cliente Desconocido';

        return [
            \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y'),
            $customerName,
            $sale->customer->email ?? 'N/A',   // <-- Correo agregado
            $sale->customer->phone ?? 'N/A',   // <-- Teléfono agregado
            $sale->product_name ?? 'N/A',
            $sale->total_amount,
            $sale->seller ? $sale->seller->name : 'N/A',
            $sale->commission_amount,
            $this->formatStatus($sale->commission_status),
        ];
    }

    // Opcional: Para que la cabecera quede en negritas
public function styles(Worksheet $sheet): array
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    // Helper para traducir el estatus
    private function formatStatus($status)
    {
        return match($status) {
            'pending' => 'Pendiente',
            'paid' => 'Pagada',
            'not_applicable' => 'No Aplica',
            default => 'N/A'
        };
    }
}