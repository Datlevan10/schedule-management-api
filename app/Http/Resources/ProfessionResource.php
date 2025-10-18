<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionResource extends JsonResource
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
            'display_name' => $this->display_name,
            'description' => $this->description,
            'default_categories' => $this->default_categories,
            'default_priorities' => $this->default_priorities,
            'ai_keywords' => $this->ai_keywords,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}