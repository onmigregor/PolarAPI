<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiKey
{
    /**
     * Valida que la petición venga con la clave interna correcta.
     * Esta clave es compartida entre el Hub y el Admin via .env.
     * Es permanente y no depende de la base de datos.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('app.internal_api_key');
        $providedKey = $request->header('X-Internal-Key')
            ?? $request->bearerToken();

        if (empty($expectedKey) || $providedKey !== $expectedKey) {
            \Illuminate\Support\Facades\Log::warning("InternalApiKey: Authentication failed on [" . $request->fullUrl() . "]. Expected: [" . substr($expectedKey, 0, 5) . "...], Provided: [" . substr($providedKey, 0, 5) . "...]");
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid internal API key.',
            ], 401);
        }

        return $next($request);
    }
}
