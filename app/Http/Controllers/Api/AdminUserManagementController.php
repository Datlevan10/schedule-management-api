<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Admin;
use App\Models\PasswordResetRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminUserManagementController extends Controller
{
    /**
     * Request Password Reset by Admin
     * POST /api/v1/admin/users/request-password-reset
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = JWTAuth::parseToken()->authenticate();
            $user = User::where('email', $request->email)->first();

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is inactive'
                ], 400);
            }

            // Create password reset request
            $resetRequest = PasswordResetRequest::create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'user_email' => $user->email,
                'reason' => $request->reason,
                'request_token' => Str::random(64),
                'status' => 'pending',
                'requested_at' => now()
            ]);

            // Log admin activity
            $admin->logActivity('password_reset_requested', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset request created successfully',
                'data' => [
                    'request_id' => $resetRequest->id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'status' => 'pending',
                    'requested_at' => $resetRequest->requested_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create password reset request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin Reset User Password
     * POST /api/v1/admin/users/{userId}/reset-password
     */
    public function resetUserPassword(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'send_email' => 'boolean',
            'custom_password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = JWTAuth::parseToken()->authenticate();
            $user = User::findOrFail($userId);

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reset password for inactive user'
                ], 400);
            }

            // Generate new password
            $newPassword = $request->custom_password ?? $this->generateSecurePassword();
            
            // Update user password
            $user->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now()
            ]);

            // Mark any pending reset requests as completed
            PasswordResetRequest::where('user_id', $userId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'completed_by' => $admin->id,
                    'completed_at' => now()
                ]);

            // Send email with new password if requested
            if ($request->boolean('send_email', true)) {
                $this->sendPasswordResetEmail($user, $newPassword, $admin);
            }

            // Log admin activity
            $admin->logActivity('user_password_reset', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'email_sent' => $request->boolean('send_email', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User password reset successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'new_password' => $newPassword, // Only return in response for admin
                    'email_sent' => $request->boolean('send_email', true),
                    'reset_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset user password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Password Reset Requests
     * GET /api/v1/admin/users/password-reset-requests
     */
    public function listPasswordResetRequests(Request $request): JsonResponse
    {
        try {
            $query = PasswordResetRequest::with(['user:id,name,email', 'admin:id,name,email']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('requested_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('requested_at', '<=', $request->to_date);
            }

            // Search by user email
            if ($request->has('search')) {
                $query->where('user_email', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'requested_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $requests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $requests->items(),
                'meta' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch password reset requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify User Exists for Password Reset
     * POST /api/v1/admin/users/verify-for-reset
     */
    public function verifyUserForReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy người dùng với địa chỉ email này.'
                ], 404);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is inactive'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'User found and eligible for password reset',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'profession' => $user->profession ? $user->profession->name : null,
                        'last_login_at' => $user->last_login_at,
                        'created_at' => $user->created_at
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate secure password
     */
    private function generateSecurePassword($length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*';
        
        $password = '';
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $symbols[rand(0, strlen($symbols) - 1)];
        
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(User $user, string $newPassword, Admin $admin): void
    {
        try {
            Mail::send('emails.admin-password-reset', [
                'user' => $user,
                'new_password' => $newPassword,
                'admin' => $admin,
                'reset_date' => now()
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                       ->subject('Your password has been reset by administrator');
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email: ' . $e->getMessage());
        }
    }
}