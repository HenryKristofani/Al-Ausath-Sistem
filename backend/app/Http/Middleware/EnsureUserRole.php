<?php

namespace App\Http\Middleware;

use App\Models\DataPetugas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::guard('sanctum')->user();

        // User must be authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // User must be a DataPetugas (staff)
        if (!$user instanceof DataPetugas) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Normalize role storage, allowing JSON-array strings or plain strings.
        $userRole = $user->peran_akun;
        if (is_string($userRole) && str_starts_with($userRole, '[')) {
            $decoded = json_decode($userRole, true);
            if (is_array($decoded)) {
                $userRole = $decoded;
            }
        }

        $rolesToCheck = is_array($userRole) ? $userRole : [(string) $userRole];
        $normalizedRoles = array_map(fn($role) => trim((string) $role), $rolesToCheck);

        foreach ($roles as $requiredRole) {
            if (in_array(trim($requiredRole), $normalizedRoles, true)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
