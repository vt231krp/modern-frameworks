<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Room;
use App\Service\RoomService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/rooms')]
final class RoomController extends AbstractController
{
    public function __construct(private readonly RoomService $rooms)
    {
    }

    /**
     * Display a listing of rooms.
     */
    #[Route('', name: 'api_rooms_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Room::class);

        $filters = $validator->validate($request, [
            'name' => 'nullable|string|max:100',
            'capacity_min' => 'nullable|integer|min:0',
            'capacity_max' => 'nullable|integer|min:0',
            'with_equipment' => 'nullable|boolean',
            'with_classes' => 'nullable|boolean',
            'sort' => 'nullable|in:room_id,name,capacity',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->rooms->paginate($filters, $request));
    }

    /**
     * Store a newly created room.
     */
    #[Route('', name: 'api_rooms_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Room::class);

        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100|unique:rooms,name',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $room = $this->rooms->create($validated);

        return $this->json($room->toArray(), 201);
    }

    /**
     * Display the specified room.
     */
    #[Route('/{room}', name: 'api_rooms_show', methods: ['GET'], requirements: ['room' => '\d+'])]
    public function show(#[MapEntity(id: 'room')] Room $room): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $room);

        return $this->json($room->toArray(withEquipment: true, withClasses: true));
    }

    /**
     * Update the specified room.
     */
    #[Route('/{room}', name: 'api_rooms_update', methods: ['PUT', 'PATCH'], requirements: ['room' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'room')] Room $room, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $room);

        $validated = $validator->validate($request, [
            'name' => 'sometimes|required|string|max:100|unique:rooms,name,'.$room->getId().',room_id',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $this->rooms->update($room, $validated);

        return $this->json($room->toArray());
    }

    /**
     * Remove the specified room.
     */
    #[Route('/{room}', name: 'api_rooms_destroy', methods: ['DELETE'], requirements: ['room' => '\d+'])]
    public function destroy(#[MapEntity(id: 'room')] Room $room): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $room);

        $this->rooms->delete($room);

        return $this->json(['message' => 'Room deleted successfully']);
    }
}
