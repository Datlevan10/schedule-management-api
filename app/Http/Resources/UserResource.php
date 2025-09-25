<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => !is_null($this->email_verified_at),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            
            // Profession Information
            'profession' => [
                'id' => $this->profession_id,
                'name' => $this->profession?->name,
                'display_name' => $this->profession?->display_name,
                'level' => $this->profession_level,
            ],
            
            // Work Information
            'workplace' => $this->workplace,
            'department' => $this->department,
            'work_schedule' => $this->work_schedule ?? [],
            'work_habits' => $this->work_habits ?? [],
            'notification_preferences' => $this->notification_preferences ?? [],
            
            // Status
            'is_active' => $this->is_active,
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'member_since' => $this->created_at->diffForHumans(),
        ];
    }
}
