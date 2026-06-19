<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateStockRequest extends FormRequest
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
            'stock' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'userId' => 'nullable|string|uuid',
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function messages(): array
    {
        return [
            'stock.required' => 'El valor de stock es requerido.',
            'stock.min' => 'El stock no puede ser negativo.',
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
