<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Admin;

class AdminAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Get the token from the request
            $token = JWTAuth::getToken();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            // Decode the token to get payload
            $payload = JWTAuth::getPayload($token);
            
            // Check if this token is for an admin
            $isAdmin = $payload->get('admin') === true;
            $adminId = $payload->get('sub');
            
            if (!$isAdmin || !$adminId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied - Admin token required'
                ], 403);
            }

            // Get the admin directly from the database
            $admin = Admin::find($adminId);
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin not found'
                ], 401);
            }

            // Set the admin in the auth guard
            Auth::guard('admin')->setUser($admin);
            
            return $next($request);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token error: ' . $e->getMessage()
            ], 401);
        }
    }
}