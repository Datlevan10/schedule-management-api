<?php

namespace App\Http\Controllers;

use App\Models\WelcomeScreen;
use App\Http\Resources\WelcomeScreenResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class WelcomeScreenController extends Controller
{
    public function getActiveScreen(): JsonResponse
    {
        $screen = WelcomeScreen::getActiveScreen();
        
        if (!$screen) {
            return response()->json([
                'status' => 'success',
                'message' => 'No active welcome screen found',
                'data' => null
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => new WelcomeScreenResource($screen)
        ]);
    }

    public function index(): JsonResponse
    {
        $screens = WelcomeScreen::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => WelcomeScreenResource::collection($screens)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'background_type' => ['required', Rule::in(['color', 'image', 'video'])],
            'background_value' => 'required|string',
            'duration' => 'required|integer|min:1|max:60',
            'is_active' => 'boolean'
        ]);

        $screen = WelcomeScreen::create($validated);

        if ($screen->is_active) {
            $screen->activate();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Welcome screen created successfully',
            'data' => new WelcomeScreenResource($screen)
        ], 201);
    }

    public function show(WelcomeScreen $welcomeScreen): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new WelcomeScreenResource($welcomeScreen)
        ]);
    }

    public function update(Request $request, WelcomeScreen $welcomeScreen): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'background_type' => ['sometimes', 'required', Rule::in(['color', 'image', 'video'])],
            'background_value' => 'sometimes|required|string',
            'duration' => 'sometimes|required|integer|min:1|max:60',
            'is_active' => 'sometimes|boolean'
        ]);

        $welcomeScreen->update($validated);

        if (isset($validated['is_active']) && $validated['is_active']) {
            $welcomeScreen->activate();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Welcome screen updated successfully',
            'data' => new WelcomeScreenResource($welcomeScreen->fresh())
        ]);
    }

    public function destroy(WelcomeScreen $welcomeScreen): JsonResponse
    {
        $welcomeScreen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Welcome screen deleted successfully'
        ]);
    }

    public function activate(WelcomeScreen $welcomeScreen): JsonResponse
    {
        $welcomeScreen->activate();

        return response()->json([
            'status' => 'success',
            'message' => 'Welcome screen activated successfully',
            'data' => new WelcomeScreenResource($welcomeScreen->fresh())
        ]);
    }
}
