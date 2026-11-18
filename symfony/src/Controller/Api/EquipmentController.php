<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Equipment;
use App\Enum\EquipmentCondition;
use App\Service\EquipmentService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/equipment')]
final class EquipmentController extends AbstractController
{
    public function __construct(private readonly EquipmentService $equipment)
    {
    }

    /**
     * Display a listing of equipment.
     */
    #[Route('', name: 'api_equipment_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Equipment::class);

        $filters = $validator->validate($request, [
            'name' => 'nullable|string|max:100',
            'condition' => 'nullable|in:'.implode(',', EquipmentCondition::values()),
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'quantity_min' => 'nullable|integer|min:0',
            'sort' => 'nullable|in:equipment_id,name,quantity,condition',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->equipment->paginate($filters, $request));
    }

    /**
     * Store a newly created equipment item.
     */
    #[Route('', name: 'api_equipment_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Equipment::class);

        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100',
            'quantity' => 'nullable|integer|min:0',
            'condition' => 'nullable|in:'.implode(',', EquipmentCondition::values()),
            'room_id' => 'nullable|integer|exists:rooms,room_id',
        ]);

        $equipment = $this->equipment->create($validated);

        return $this->json($equipment->toArray(withRoom: true), 201);
    }

    /**
     * Display the specified equipment item.
     */
    #[Route('/{equipment}', name: 'api_equipment_show', methods: ['GET'], requirements: ['equipment' => '\d+'])]
    public function show(#[MapEntity(id: 'equipment')] Equipment $equipment): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $equipment);

        return $this->json($equipment->toArray(withRoom: true));
    }

    /**
     * Update the specified equipment item.
     */
    #[Route('/{equipment}', name: 'api_equipment_update', methods: ['PUT', 'PATCH'], requirements: ['equipment' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'equipment')] Equipment $equipment, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $equipment);

        $validated = $validator->validate($request, [
            'name' => 'sometimes|required|string|max:100',
            'quantity' => 'nullable|integer|min:0',
            'condition' => 'nullable|in:'.implode(',', EquipmentCondition::values()),
            'room_id' => 'nullable|integer|exists:rooms,room_id',
        ]);

        $this->equipment->update($equipment, $validated);

        return $this->json($equipment->toArray(withRoom: true));
    }

    /**
     * Remove the specified equipment item.
     */
    #[Route('/{equipment}', name: 'api_equipment_destroy', methods: ['DELETE'], requirements: ['equipment' => '\d+'])]
    public function destroy(#[MapEntity(id: 'equipment')] Equipment $equipment): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $equipment);

        $this->equipment->delete($equipment);

        return $this->json(['message' => 'Equipment deleted successfully']);
    }
}
