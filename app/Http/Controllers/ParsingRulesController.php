<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParsingRuleRequest;
use App\Http\Resources\ParsingRuleResource;
use App\Models\ParsingRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ParsingRulesController extends Controller
{
    /**
     * Display a listing of parsing rules
     * GET /api/v1/parsing-rules
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $query = ParsingRule::with(['profession', 'creator']);

            // Admin can see all, users see only their profession + global
            if (!$user->isAdmin()) {
                if ($user->profession_id) {
                    $query->applicableFor($user->profession_id);
                } else {
                    $query->global();
                }
            }

            // Filter by profession
            if ($request->has('profession_id')) {
                $query->where('profession_id', $request->profession_id);
            }

            // Filter by rule type
            if ($request->has('rule_type')) {
                $query->ofType($request->rule_type);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('rule_name', 'LIKE', "%{$search}%")
                      ->orWhere('rule_pattern', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'priority_order');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $rules = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ParsingRuleResource::collection($rules),
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch parsing rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created parsing rule
     * POST /api/v1/parsing-rules
     */
    public function store(ParsingRuleRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $rule = ParsingRule::create([
                'rule_name' => $request->rule_name,
                'profession_id' => $request->profession_id,
                'rule_type' => $request->rule_type,
                'rule_pattern' => $request->rule_pattern,
                'rule_action' => $request->rule_action,
                'conditions' => $request->conditions,
                'priority_order' => $request->priority_order ?? 100,
                'positive_examples' => $request->positive_examples,
                'negative_examples' => $request->negative_examples,
                'is_active' => $request->is_active ?? true,
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parsing rule created successfully',
                'data' => new ParsingRuleResource($rule->load(['profession', 'creator']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create parsing rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified parsing rule
     * GET /api/v1/parsing-rules/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $query = ParsingRule::with(['profession', 'creator']);

            // Apply access control
            if (!$user->isAdmin()) {
                if ($user->profession_id) {
                    $query->applicableFor($user->profession_id);
                } else {
                    $query->global();
                }
            }

            $rule = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new ParsingRuleResource($rule)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parsing rule not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified parsing rule
     * PUT /api/v1/parsing-rules/{id}
     */
    public function update(ParsingRuleRequest $request, $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $rule = ParsingRule::findOrFail($id);

            // Check permissions
            if (!$user->isAdmin() && $rule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this rule'
                ], 403);
            }

            $rule->update([
                'rule_name' => $request->rule_name,
                'profession_id' => $request->profession_id,
                'rule_type' => $request->rule_type,
                'rule_pattern' => $request->rule_pattern,
                'rule_action' => $request->rule_action,
                'conditions' => $request->conditions,
                'priority_order' => $request->priority_order,
                'positive_examples' => $request->positive_examples,
                'negative_examples' => $request->negative_examples,
                'is_active' => $request->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parsing rule updated successfully',
                'data' => new ParsingRuleResource($rule->fresh(['profession', 'creator']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update parsing rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified parsing rule
     * DELETE /api/v1/parsing-rules/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $rule = ParsingRule::findOrFail($id);

            // Check permissions
            if (!$user->isAdmin() && $rule->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this rule'
                ], 403);
            }

            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Parsing rule deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete parsing rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test a parsing rule with examples
     * POST /api/v1/parsing-rules/{id}/test
     */
    public function testRule($id, Request $request): JsonResponse
    {
        $request->validate([
            'test_text' => 'required|string',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $query = ParsingRule::query();

            // Apply access control
            if (!$user->isAdmin()) {
                if ($user->profession_id) {
                    $query->applicableFor($user->profession_id);
                } else {
                    $query->global();
                }
            }

            $rule = $query->findOrFail($id);
            $testText = $request->test_text;

            // Test the rule
            $matches = $rule->matchesPattern($testText);
            $actionResult = $matches ? $rule->applyToText($testText) : null;

            // Run tests with existing examples
            $exampleResults = $rule->testWithExamples();

            return response()->json([
                'success' => true,
                'data' => [
                    'rule_info' => [
                        'id' => $rule->id,
                        'name' => $rule->rule_name,
                        'type' => $rule->rule_type,
                        'pattern' => $rule->rule_pattern,
                    ],
                    'test_input' => $testText,
                    'test_results' => [
                        'matches' => $matches,
                        'action_result' => $actionResult,
                    ],
                    'example_results' => $exampleResults,
                    'performance' => [
                        'accuracy_rate' => $rule->accuracy_rate,
                        'usage_count' => $rule->usage_count,
                        'success_count' => $rule->success_count,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test parsing rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate a regex pattern
     * POST /api/v1/parsing-rules/validate-pattern
     */
    public function validatePattern(Request $request): JsonResponse
    {
        $request->validate([
            'pattern' => 'required|string',
            'test_strings' => 'nullable|array',
            'test_strings.*' => 'string',
        ]);

        try {
            $pattern = $request->pattern;
            $testStrings = $request->test_strings ?? [];

            $errors = [];
            $warnings = [];
            $results = [];

            // Test pattern validity
            $isValid = true;
            try {
                preg_match($pattern, 'test');
            } catch (\Exception $e) {
                $isValid = false;
                $errors[] = "Invalid regex pattern: " . $e->getMessage();
            }

            // Test with provided strings
            if ($isValid && !empty($testStrings)) {
                foreach ($testStrings as $index => $testString) {
                    try {
                        $matches = preg_match($pattern, $testString);
                        $results[] = [
                            'input' => $testString,
                            'matches' => $matches === 1,
                            'index' => $index,
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'input' => $testString,
                            'error' => $e->getMessage(),
                            'index' => $index,
                        ];
                    }
                }
            }

            // Pattern complexity warnings
            if (strlen($pattern) > 200) {
                $warnings[] = "Pattern is very long and may be complex to maintain";
            }

            if (substr_count($pattern, '.*') > 3) {
                $warnings[] = "Pattern contains many .* expressions which may impact performance";
            }

            return response()->json([
                'success' => $isValid,
                'valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings,
                'test_results' => $results,
                'pattern' => $pattern,
                'message' => $isValid ? 'Pattern is valid' : 'Pattern has errors'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pattern validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rules by profession
     * GET /api/v1/parsing-rules/by-profession/{professionId}
     */
    public function byProfession($professionId): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            // Check access permissions
            if (!$user->isAdmin() && $user->profession_id != $professionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view rules for this profession'
                ], 403);
            }

            $rules = ParsingRule::active()
                ->forProfession($professionId)
                ->with(['profession', 'creator'])
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'data' => ParsingRuleResource::collection($rules)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rules by profession',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rules by type
     * GET /api/v1/parsing-rules/by-type/{type}
     */
    public function byType($type): JsonResponse
    {
        $validTypes = ['keyword_detection', 'pattern_matching', 'priority_calculation', 'category_assignment'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid rule type'
            ], 400);
        }

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $query = ParsingRule::active()
                ->ofType($type)
                ->with(['profession', 'creator']);

            // Apply access control
            if (!$user->isAdmin()) {
                if ($user->profession_id) {
                    $query->applicableFor($user->profession_id);
                } else {
                    $query->global();
                }
            }

            $rules = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => ParsingRuleResource::collection($rules)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rules by type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate rules
     * POST /api/v1/parsing-rules/bulk-activate
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $request->validate([
            'rule_ids' => 'required|array',
            'rule_ids.*' => 'integer|exists:parsing_rules,id',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $ruleIds = $request->rule_ids;

            $query = ParsingRule::whereIn('id', $ruleIds);
            
            // Apply access control for non-admin users
            if (!$user->isAdmin()) {
                $query->where('created_by', $user->id);
            }

            $updated = $query->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => "Activated {$updated} parsing rules",
                'data' => [
                    'updated_count' => $updated,
                    'rule_ids' => $ruleIds,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate rules
     * POST /api/v1/parsing-rules/bulk-deactivate
     */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $request->validate([
            'rule_ids' => 'required|array',
            'rule_ids.*' => 'integer|exists:parsing_rules,id',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $ruleIds = $request->rule_ids;

            $query = ParsingRule::whereIn('id', $ruleIds);
            
            // Apply access control for non-admin users
            if (!$user->isAdmin()) {
                $query->where('created_by', $user->id);
            }

            $updated = $query->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => "Deactivated {$updated} parsing rules",
                'data' => [
                    'updated_count' => $updated,
                    'rule_ids' => $ruleIds,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}