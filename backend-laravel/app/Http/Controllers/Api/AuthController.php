<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class AuthController extends Controller
{
    /**
     * Inicio de sesión del usuario.
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('username', $request->username)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Credenciales inválidas'
                ], 400);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario desactivado. Contacte al administrador.'
                ], 400);
            }

            // Token simple UUID (sin Sanctum) — el frontend lo usa como referencia de sesión
            $token = (string) Str::uuid();

            return response()->json([
                'success' => true,
                'user' => new UserResource($user),
                'token' => $token // Proveemos el token por si el frontend migra a JWT/Sanctum
            ]);
        } catch (Exception $e) {
            Log::error("Login exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error en el servidor'
            ], 500);
        }
    }

    /**
     * Registro de nuevo usuario.
     *
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        try {
            $user = User::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'role' => $request->role ?? 'waiter',
                'station' => $request->station ?? '',
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'user' => new UserResource($user)
            ]);
        } catch (Exception $e) {
            Log::error("Register exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al registrar usuario'
            ], 500);
        }
    }

    /**
     * Verificación y recuperación de sesión activa del usuario.
     *
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSession(string $userId)
    {
        try {
            $user = User::find($userId);

            if ($user && $user->is_active) {
                return response()->json([
                    'success' => true,
                    'user' => new UserResource($user)
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Sesión inválida'
            ], 404);
        } catch (Exception $e) {
            Log::error("GetSession exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error en el servidor'
            ], 500);
        }
    }

    /**
     * Cambiar contraseña de usuario de forma segura.
     *
     * @param Request $request
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request, string $userId)
    {
        $request->validate([
            'oldPassword' => 'required|string',
            'newPassword' => 'required|string|min:6',
        ], [
            'oldPassword.required' => 'La contraseña actual es requerida.',
            'newPassword.required' => 'La nueva contraseña es requerida.',
            'newPassword.min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
        ]);

        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            if (!Hash::check($request->oldPassword, $user->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Contraseña actual incorrecta'
                ], 400);
            }

            $user->password = Hash::make($request->newPassword);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada'
            ]);
        } catch (Exception $e) {
            Log::error("ChangePassword exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar contraseña'
            ], 500);
        }
    }
}
