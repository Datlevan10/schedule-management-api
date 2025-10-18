<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Profession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * User Registration
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create user with profession information
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'profession_id' => $request->profession_id,
                'profession_level' => $request->profession_level,
                'workplace' => $request->workplace,
                'department' => $request->department,
                'work_schedule' => $request->work_schedule ?? [],
                'work_habits' => $request->work_habits ?? [],
                'notification_preferences' => $request->notification_preferences ?? [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'reminder_advance_minutes' => [15, 60, 1440], // 15min, 1h, 1day
                ],
                'is_active' => true,
            ]);

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Load profession relationship
            $user->load('profession');

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60, // seconds
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Login
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password');

            // Check if user exists and is active
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated. Please contact support.'
                ], 403);
            }

            // Attempt authentication
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Update last login
            $user->update(['remember_token' => $token]);

            // Load relationships
            $user->load('profession');

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => new UserResource($user),
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
     * User Logout
     * POST /api/auth/logout
     */
    public function logout(): JsonResponse
    {
        try {
            // Invalidate token
            JWTAuth::invalidate(JWTAuth::getToken());

            // Clear remember token
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user) {
                $user->update(['remember_token' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
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
     * Refresh Token
     * POST /api/auth/refresh
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
     * Get Current User
     * GET /api/auth/me
     */
    public function me(): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $user->load('profession');

            return response()->json([
                'success' => true,
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update User Profile
     * PUT /api/auth/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $updateData = $request->only([
                'name', 'profession_id', 'profession_level', 
                'workplace', 'department', 'work_schedule', 
                'work_habits', 'notification_preferences'
            ]);

            $user->update($updateData);
            $user->load('profession');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change Password
     * POST /api/auth/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Forgot Password
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset Password
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Password reset failed'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate Account
     * POST /api/auth/deactivate
     */
    public function deactivateAccount(): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $user->update(['is_active' => false]);

            // Invalidate all tokens
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Account deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account deactivation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate Account
     * POST /api/auth/reactivate
     */
    public function reactivateAccount(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required'
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Account reactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account reactivation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Email Verification
     * POST /api/auth/email/verify
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'verification_code' => 'required|string'
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            // Implement your verification logic here
            // This is a simplified version
            
            $user->update([
                'email_verified_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend Email Verification
     * POST /api/auth/email/resend
     */
    public function resendVerificationEmail(): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if ($user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is already verified'
                ], 400);
            }

            // Implement your email sending logic here
            // Mail::to($user)->send(new VerificationEmail($user));

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Account (Soft Delete)
     * DELETE /api/auth/delete
     */
    public function deleteAccount(): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            // Soft delete - deactivate instead of hard delete
            $user->update([
                'is_active' => false,
                'email' => $user->email . '_deleted_' . time()
            ]);

            // Invalidate token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}