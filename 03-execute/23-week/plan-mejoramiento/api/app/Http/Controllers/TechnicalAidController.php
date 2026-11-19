<?php

namespace App\Http\Controllers;

use App\Models\TechnicalAid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicalAidController extends Controller
{
    /**
     * Display active technical aids with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'nullable',
                'uuid',
                'exists:technical_aid_categories,id',
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $validated['page'] ?? 1;
        $size = $validated['size'] ?? 20;

        $query = TechnicalAid::query()
            ->with('category')
            ->where('is_active', true);

        if (! empty($validated['q'])) {
            $query->where(
                'reference',
                'like',
                '%' . $validated['q'] . '%'
            );
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $results = $query
            ->orderBy('reference')
            ->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'items' => $results->items(),
            'page' => $results->currentPage(),
            'size' => $results->perPage(),
            'total' => $results->total(),
        ]);
    }

    /**
     * Display a specific active technical aid.
     */
    public function show(TechnicalAid $technicalAid): JsonResponse
    {
        if (! $technicalAid->is_active) {
            return response()->json([
                'message' => 'La ayuda técnica no existe o está inactiva.',
            ], 404);
        }

        return response()->json([
            'technical_aid' => $technicalAid->load('category'),
        ]);
    }

    /**
     * Store a new technical aid.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'No tienes permisos para registrar ayudas técnicas.',
            ], 403);
        }

        $validated = $request->validate([
            'category_id' => [
                'required',
                'uuid',
                'exists:technical_aid_categories,id',
            ],
            'reference' => [
                'required',
                'string',
                'max:255',
            ],
            'total_units' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $referenceExists = TechnicalAid::query()
            ->where('reference', $validated['reference'])
            ->where('is_active', true)
            ->exists();

        if ($referenceExists) {
            return response()->json([
                'message' => 'Ya existe una ayuda técnica activa con esta referencia.',
            ], 409);
        }

        $technicalAid = TechnicalAid::create([
            'category_id' => $validated['category_id'],
            'reference' => $validated['reference'],
            'total_units' => $validated['total_units'],
            'available_units' => $validated['total_units'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Ayuda técnica registrada correctamente.',
            'technical_aid' => $technicalAid->load('category'),
        ], 201);
    }

    /**
     * Update an existing technical aid.
     */
    public function update(
        Request $request,
        TechnicalAid $technicalAid
    ): JsonResponse {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'No tienes permisos para actualizar ayudas técnicas.',
            ], 403);
        }

        if (! $technicalAid->is_active) {
            return response()->json([
                'message' => 'No se puede actualizar una ayuda técnica inactiva.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:technical_aid_categories,id',
            ],
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'total_units' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
        ]);

        if ($validated === []) {
            return response()->json([
                'message' => 'Debes proporcionar al menos un campo para actualizar.',
            ], 422);
        }

        if (isset($validated['reference'])) {
            $referenceExists = TechnicalAid::query()
                ->where('reference', $validated['reference'])
                ->where('is_active', true)
                ->where('id', '!=', $technicalAid->id)
                ->exists();

            if ($referenceExists) {
                return response()->json([
                    'message' => 'Ya existe una ayuda técnica activa con esta referencia.',
                ], 409);
            }
        }

        $updatedAid = DB::transaction(function () use (
            $validated,
            $technicalAid
        ) {
            $aid = TechnicalAid::query()
                ->lockForUpdate()
                ->findOrFail($technicalAid->id);

            if (isset($validated['total_units'])) {
                $difference = $validated['total_units'] - $aid->total_units;

                $newAvailableUnits = $aid->available_units + $difference;

                if ($newAvailableUnits < 0) {
                    throw ValidationException::withMessages([
                        'total_units' => [
                            'El nuevo total no puede ser inferior a las unidades actualmente prestadas.',
                        ],
                    ]);
                }

                $aid->total_units = $validated['total_units'];
                $aid->available_units = $newAvailableUnits;
            }

            if (isset($validated['category_id'])) {
                $aid->category_id = $validated['category_id'];
            }

            if (isset($validated['reference'])) {
                $aid->reference = $validated['reference'];
            }

            $aid->save();

            return $aid->load('category');
        });

        return response()->json([
            'message' => 'Ayuda técnica actualizada correctamente.',
            'technical_aid' => $updatedAid,
        ]);
    }

    /**
     * Logically deactivate a technical aid.
     */
    public function destroy(
        Request $request,
        TechnicalAid $technicalAid
    ): JsonResponse {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'No tienes permisos para desactivar ayudas técnicas.',
            ], 403);
        }

        $deactivated = DB::transaction(function () use ($technicalAid) {
            $aid = TechnicalAid::query()
                ->lockForUpdate()
                ->findOrFail($technicalAid->id);

            $hasOutstandingLoans = DB::table('loan_lines')
                ->where('technical_aid_id', $aid->id)
                ->whereNull('returned_at')
                ->exists();

            if ($hasOutstandingLoans) {
                return false;
            }

            $aid->is_active = false;
            $aid->save();

            return true;
        });

        if (! $deactivated) {
            return response()->json([
                'message' => 'No se puede desactivar una ayuda técnica con préstamos pendientes.',
            ], 409);
        }

        return response()->json([
            'message' => 'Ayuda técnica desactivada correctamente.',
        ]);
    }
}
