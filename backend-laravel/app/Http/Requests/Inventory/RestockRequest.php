<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RestockRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     */
    public function rules(): array
    {
        return [
            'quantity' => 'required|numeric|gt:0',
            'userId' => 'nullable|string|uuid',
            'reason' => 'nullable|string|max:255',
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad de reabastecimiento es obligatoria.',
            'quantity.gt' => 'La cantidad debe ser mayor que 0.',
        ];
    }

    /**
     * Sobrescribimos el control de fallas para responder con formato JSON compatible.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors()->all(),
            'error' => $validator->errors()->first()
        ], 400));
    }
}
