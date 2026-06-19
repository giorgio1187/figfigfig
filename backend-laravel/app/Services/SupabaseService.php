<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SupabaseService
{
    protected string $url;
    protected string $key;
    protected string $serviceRoleKey;
    protected string $jwtSecret;

    public function __construct()
    {
        $this->url = config('services.supabase.url', env('SUPABASE_URL', ''));
        $this->key = config('services.supabase.anon_key', env('SUPABASE_ANON_KEY', ''));
        $this->serviceRoleKey = config('services.supabase.service_role_key', env('SUPABASE_SERVICE_ROLE_KEY', ''));
        $this->jwtSecret = config('services.supabase.jwt_secret', env('SUPABASE_JWT_SECRET', ''));
    }

    /**
     * Propaga la identidad del usuario actual a la conexión PostgreSQL activa.
     * Esto es fundamental si se tienen habilitadas políticas RLS (Row Level Security) 
     * en la base de datos de Supabase.
     * 
     * Nota: Debe llamarse dentro de una transacción activa para que 'SET LOCAL' 
     * tenga efecto en la sesión actual.
     *
     * @param string $userId UUID del usuario
     * @param string|null $role Rol del usuario (ej: 'authenticated')
     * @return void
     */
    public function setPostgresSessionUser(string $userId, ?string $role = 'authenticated'): void
    {
        try {
            // Establece el ID de usuario en las variables de sesión de PostgreSQL
            DB::statement("SET LOCAL request.jwt.claim.sub = ?", [$userId]);
            DB::statement("SET LOCAL request.jwt.claim.role = ?", [$role]);
            
            Log::info("RLS context propagated to Postgres session: user_id={$userId}, role={$role}");
        } catch (Exception $e) {
            Log::error("Failed to set Postgres RLS session context: " . $e->getMessage());
            throw new Exception("Error al inicializar contexto de seguridad en la base de datos: " . $e->getMessage());
        }
    }

    /**
     * Limpia el contexto RLS de la sesión de base de datos actual.
     *
     * @return void
     */
    public function clearPostgresSessionUser(): void
    {
        try {
            DB::statement("RESET request.jwt.claim.sub");
            DB::statement("RESET request.jwt.claim.role");
        } catch (Exception $e) {
            Log::error("Failed to reset Postgres session context: " . $e->getMessage());
        }
    }

    /**
     * Retorna un cliente HTTP preconfigurado para realizar peticiones directas 
     * a las REST APIs de Supabase (PostgREST, Auth, Storage, etc.).
     *
     * @param bool $useServiceRole Si es true, usa la llave de rol de servicio (evita políticas RLS)
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function getHttpClient(bool $useServiceRole = false): \Illuminate\Http\Client\PendingRequest
    {
        $token = $useServiceRole ? $this->serviceRoleKey : $this->key;

        if (empty($this->url) || empty($token)) {
            throw new Exception("La URL o las llaves API de Supabase no están configuradas correctamente.");
        }

        return Http::baseUrl($this->url)
            ->withHeaders([
                'apikey' => $token,
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation'
            ]);
    }

    /**
     * Decodifica y valida un token JWT emitido por Supabase Auth (GoTrue).
     * Útil si el frontend envía el JWT directamente a Laravel.
     *
     * @param string $token Token JWT Bearer
     * @return array|null Retorna el payload del token decodificado o null si es inválido
     */
    public function verifyAndDecodeJwt(string $token): ?array
    {
        if (empty($this->jwtSecret)) {
            Log::warning("Supabase JWT Secret no configurado. Validación de JWT omitida.");
            return null;
        }

        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            [$headerB64, $payloadB64, $signatureB64] = $parts;

            // Reconstruir firma HS256 para verificación
            $signature = base64_decode(strtr($signatureB64, '-_', '+/'));
            $expectedSignature = hash_hmac('sha256', "$headerB64.$payloadB64", $this->jwtSecret, true);

            if (!hash_equals($signature, $expectedSignature)) {
                Log::warning("Firma JWT inválida.");
                return null;
            }

            $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);

            // Verificar expiración (exp)
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Log::warning("El token JWT ha expirado.");
                return null;
            }

            return $payload;
        } catch (Exception $e) {
            Log::error("Error decodificando JWT de Supabase: " . $e->getMessage());
            return null;
        }
    }
}
