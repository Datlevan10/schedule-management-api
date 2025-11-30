<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Carbon\Carbon;

class Admin extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'phone',
        'department',
        'is_active',
        'can_create_admins',
        'can_delete_users',
        'can_manage_templates',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'can_create_admins' => 'boolean',
        'can_delete_users' => 'boolean',
        'can_manage_templates' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'admin' => true,
            'role' => $this->role,
            'permissions' => $this->permissions ?? []
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Methods
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function canCreateAdmins()
    {
        return $this->can_create_admins || $this->isSuperAdmin();
    }

    public function canDeleteUsers()
    {
        return $this->can_delete_users || $this->isSuperAdmin();
    }

    public function canManageTemplates()
    {
        return $this->can_manage_templates || $this->isSuperAdmin();
    }

    public function hasPermission($permission)
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    public function updateLastLogin($ip = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip
        ]);
    }

    // Role-based permissions
    public static function getDefaultPermissions($role)
    {
        return match($role) {
            'super_admin' => [
                'manage_users',
                'manage_admins', 
                'manage_templates',
                'view_reports',
                'manage_system_settings',
                'delete_data'
            ],
            'admin' => [
                'manage_users',
                'manage_templates',
                'view_reports'
            ],
            default => []
        };
    }

    // Activity logging
    public function logActivity($action, $details = null)
    {
        // This could integrate with the existing admin_activities table
        \DB::table('admin_activities')->insert([
            'admin_id' => $this->id,
            'action' => $action,
            'target_type' => 'system',
            'target_id' => null,
            'details' => json_encode($details),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
