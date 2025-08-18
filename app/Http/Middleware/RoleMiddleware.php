<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\UserRole;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login')
                ->with('error', 'You must be logged in to access this page.');
        }

        $user = auth()->user();

        // Check if user is active
        if (!$user->is_active) {
            auth()->logout();
            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact administrator.');
        }

        // Convert string roles to UserRole enums for comparison
        $allowedRoles = array_map(function ($role) {
            return match (strtolower($role)) {
                'superadmin', 'super_admin' => UserRole::SUPERADMIN,
                'admin' => UserRole::ADMIN,
                'user' => UserRole::USER,
                default => null,
            };
        }, $roles);

        // Remove null values
        $allowedRoles = array_filter($allowedRoles);

        // Check if user has any of the required roles using our custom method
        $userHasRole = in_array($user->role, $allowedRoles);

        if (!$userHasRole) {
            // Log the unauthorized access attempt
            \Log::warning('Unauthorized access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role->value,
                'required_roles' => $roles,
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to access this resource.',
                    'required_roles' => $roles,
                    'user_role' => $user->role->value,
                ], 403);
            }

            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}