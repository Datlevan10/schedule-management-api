<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfessionRequest;
use App\Http\Requests\UpdateProfessionRequest;
use App\Http\Resources\ProfessionResource;
use App\Models\Profession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $professions = Profession::all();

        return response()->json([
            'success' => true,
            'data' => ProfessionResource::collection($professions)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProfessionRequest $request): JsonResponse
    {
        $profession = Profession::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profession created successfully',
            'data' => new ProfessionResource($profession)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $profession = Profession::find($id);

        if (!$profession) {
            return response()->json([
                'success' => false,
                'message' => 'Profession not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ProfessionResource($profession)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfessionRequest $request, string $id): JsonResponse
    {
        $profession = Profession::find($id);

        if (!$profession) {
            return response()->json([
                'success' => false,
                'message' => 'Profession not found'
            ], 404);
        }

        $profession->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profession updated successfully',
            'data' => new ProfessionResource($profession)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $profession = Profession::find($id);

        if (!$profession) {
            return response()->json([
                'success' => false,
                'message' => 'Profession not found'
            ], 404);
        }

        $profession->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profession deleted successfully'
        ]);
    }
}