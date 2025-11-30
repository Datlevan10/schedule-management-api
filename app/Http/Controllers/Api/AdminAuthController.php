<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminAuthController extends Controller
{
    /**
     * Admin Login
     * POST /api/v1/admin/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            
            // Find admin by email
            $admin = Admin::where('email', $request->email)->first();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin not found'
                ], 404);
            }

            if (!$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin account is deactivated'
                ], 403);
            }

            // Verify password
            if (!Hash::check($request->password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Generate token for admin
            $token = JWTAuth::fromUser($admin);

            // Update last login
            $admin->updateLastLogin($request->ip());

            // Log admin login activity
            $admin->logActivity('admin_login', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Admin login successful',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                        'role' => $admin->role,
                        'permissions' => $admin->permissions ?? [],
                        'department' => $admin->department,
                        'can_create_admins' => $admin->canCreateAdmins(),
                        'can_delete_users' => $admin->canDeleteUsers(),
                        'can_manage_templates' => $admin->canManageTemplates(),
                        'last_login_at' => $admin->last_login_at,
                    ],
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Current Admin
     * GET /api/v1/admin/auth/me
     */
    public function me(): JsonResponse
    {
        try {
            // Get admin from the auth guard (set by AdminAuth middleware)
            $admin = Auth::guard('admin')->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'permissions' => $admin->permissions ?? [],
                    'department' => $admin->department,
                    'phone' => $admin->phone,
                    'can_create_admins' => $admin->canCreateAdmins(),
                    'can_delete_users' => $admin->canDeleteUsers(),
                    'can_manage_templates' => $admin->canManageTemplates(),
                    'last_login_at' => $admin->last_login_at,
                    'created_at' => $admin->created_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get admin profile',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Admin Logout
     * POST /api/v1/admin/auth/logout
     */
    public function logout(): JsonResponse
    {
        try {
            $admin = JWTAuth::parseToken()->authenticate();
            
            if ($admin instanceof Admin) {
                $admin->logActivity('admin_logout');
            }

            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Admin logout successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh Admin Token
     * POST /api/v1/admin/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Create Admin (Super Admin only)
     * POST /api/v1/admin/auth/create
     */
    public function createAdmin(Request $request): JsonResponse
    {
        try {
            $currentAdmin = JWTAuth::parseToken()->authenticate();
            
            if (!$currentAdmin instanceof Admin || !$currentAdmin->canCreateAdmins()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to create admin'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:admins,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:admin,super_admin',
                'department' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'can_create_admins' => 'boolean',
                'can_delete_users' => 'boolean',
                'can_manage_templates' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'department' => $request->department,
                'phone' => $request->phone,
                'permissions' => Admin::getDefaultPermissions($request->role),
                'can_create_admins' => $request->boolean('can_create_admins'),
                'can_delete_users' => $request->boolean('can_delete_users'),
                'can_manage_templates' => $request->boolean('can_manage_templates', true),
                'is_active' => true,
            ]);

            // Log activity
            $currentAdmin->logActivity('admin_created', [
                'created_admin_id' => $admin->id,
                'created_admin_email' => $admin->email,
                'role' => $admin->role
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Admin created successfully',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'department' => $admin->department,
                    'permissions' => $admin->permissions,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Admins (Super Admin only)
     * GET /api/v1/admin/auth/admins
     */
    public function listAdmins(Request $request): JsonResponse
    {
        try {
            $currentAdmin = JWTAuth::parseToken()->authenticate();
            
            if (!$currentAdmin instanceof Admin || !$currentAdmin->canCreateAdmins()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to list admins'
                ], 403);
            }

            $query = Admin::select(['id', 'name', 'email', 'role', 'department', 'is_active', 'last_login_at', 'created_at']);

            // Filters
            if ($request->has('role')) {
                $query->byRole($request->role);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $admins = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $admins->items(),
                'meta' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list admins',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}