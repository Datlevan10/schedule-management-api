<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeatureHighlightRequest;
use App\Http\Requests\UpdateFeatureHighlightRequest;
use App\Http\Resources\FeatureHighlightResource;
use App\Models\FeatureHighlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FeatureHighlightController extends Controller
{
    /**
     * Display a listing of active feature highlights (Public endpoint)
     */
    public function index(): JsonResponse
    {
        $features = Cache::remember('feature_highlights', 3600, function () {
            return FeatureHighlight::active()->ordered()->get();
        });

        return response()->json([
            'status' => 'success',
            'data' => FeatureHighlightResource::collection($features),
        ]);
    }

    /**
     * Store a newly created feature highlight (Admin only)
     */
    public function store(StoreFeatureHighlightRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle file upload
        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('feature-icons', 'public');
            $data['icon_url'] = '/storage/' . $path;
            // Remove icon_file from data as it's not a database field
            unset($data['icon_file']);
        }

        $feature = FeatureHighlight::create($data);

        // Clear cache when new feature is created
        Cache::forget('feature_highlights');

        return response()->json([
            'status' => 'success',
            'message' => 'Feature highlight created successfully.',
            'data' => new FeatureHighlightResource($feature),
        ], 201);
    }

    /**
     * Display the specified feature highlight
     */
    public function show(FeatureHighlight $featureHighlight): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new FeatureHighlightResource($featureHighlight),
        ]);
    }

    /**
     * Update the specified feature highlight (Admin only)
     */
    public function update(UpdateFeatureHighlightRequest $request, FeatureHighlight $featureHighlight): JsonResponse
    {
        $data = $request->validated();

        // Handle file upload
        if ($request->hasFile('icon_file')) {
            // Delete old file if it exists and is a local file
            if ($featureHighlight->icon_url && str_starts_with($featureHighlight->icon_url, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $featureHighlight->icon_url);
                Storage::disk('public')->delete($oldPath);
            }

            // Store new file
            $path = $request->file('icon_file')->store('feature-icons', 'public');
            $data['icon_url'] = '/storage/' . $path;
            // Remove icon_file from data as it's not a database field
            unset($data['icon_file']);
        }

        $featureHighlight->update($data);

        // Clear cache when feature is updated
        Cache::forget('feature_highlights');

        return response()->json([
            'status' => 'success',
            'message' => 'Feature highlight updated successfully.',
            'data' => new FeatureHighlightResource($featureHighlight->fresh()),
        ]);
    }

    /**
     * Remove the specified feature highlight (Admin only)
     */
    public function destroy(FeatureHighlight $featureHighlight): JsonResponse
    {
        // Delete associated file if it exists and is a local file
        if ($featureHighlight->icon_url && str_starts_with($featureHighlight->icon_url, '/storage/')) {
            $filePath = str_replace('/storage/', '', $featureHighlight->icon_url);
            Storage::disk('public')->delete($filePath);
        }

        $featureHighlight->delete();

        // Clear cache when feature is deleted
        Cache::forget('feature_highlights');

        return response()->json([
            'status' => 'success',
            'message' => 'Feature highlight deleted successfully.',
        ]);
    }
}
