<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatureHighlightResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'icon_url' => $this->getFullIconUrl(),
            'order' => $this->order,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * Get the full URL for the icon
     */
    private function getFullIconUrl(): ?string
    {
        if (!$this->icon_url) {
            return null;
        }

        // If it's already a full URL (external), return as is
        if (str_starts_with($this->icon_url, 'http://') || str_starts_with($this->icon_url, 'https://')) {
            return $this->icon_url;
        }

        // If it's a local storage path, convert to full URL
        if (str_starts_with($this->icon_url, '/storage/')) {
            // Use APP_URL from config if set, otherwise use url() helper
            $baseUrl = config('app.url');
            if ($baseUrl && $baseUrl !== 'http://localhost') {
                // Remove trailing slash from base URL if present
                $baseUrl = rtrim($baseUrl, '/');
                return $baseUrl . $this->icon_url;
            }
            return url($this->icon_url);
        }

        // Fallback for other cases
        return $this->icon_url;
    }
}
