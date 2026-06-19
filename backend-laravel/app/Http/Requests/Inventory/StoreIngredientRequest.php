<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreIngredientRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'stock' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:20',
            'low_stock_threshold' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del ingrediente es obligatorio.',
            'stock.min' => 'El stock inicial no puede ser negativo.',
            'low_stock_threshold.min' => 'El umbral de stock mínimo no puede ser negativo.',
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
            'error' => 'Error de validación'
        ], 400));
    }
}
