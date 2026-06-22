<?php

namespace App\Http\Requests\Api\Partner\Insurance;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceApplicationRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        // Al ser el dashboard de clientes autónomo, permitimos la petición si está autenticado
        return auth()->check();
    }

    /**
     * Reglas de validación aplicadas a la solicitud.
     */
    public function rules(): array
    {
        return [
            'applicant_name'    => ['required', 'string', 'max:255'],
            'applicant_email'   => ['required', 'email', 'max:255'],
            'applicant_phone'   => ['required', 'string', 'max:30'],
            'applicant_dob'     => ['required', 'date', 'before:today'], // Debe ser una fecha válida en el pasado
            'applicant_address' => ['required', 'string', 'max:255'],
            'applicant_state'   => ['required', 'string', 'max:10'],
            'insurance_type'    => ['nullable', 'string', 'max:100'],
            'metadata'          => ['nullable', 'array'],
        ];
    }

    /**
     * Mensajes de error personalizados (Opcional, pero ideal para el Frontend en Next.js)
     */
    public function messages(): array
    {
        return [
            'applicant_name.required'  => 'El nombre completo es obligatorio.',
            'applicant_email.required' => 'El correo electrónico es obligatorio.',
            'applicant_email.email'    => 'El formato del correo electrónico no es válido.',
            'applicant_phone.required' => 'El número de teléfono es obligatorio.',
            'applicant_dob.required'   => 'La fecha de nacimiento es prioritaria y obligatoria.',
            'applicant_dob.before'     => 'La fecha de nacimiento debe ser una fecha pasada.',
            'applicant_address.required' => 'La dirección es obligatoria.',
            'applicant_state.required'   => 'El estado es obligatorio.',
        ];
    }
}