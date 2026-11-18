<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Payment;
use App\Service\PaymentService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payments')]
final class PaymentController extends AbstractController
{
    private const METHODS = 'in:cash,card,online,transfer';

    private const STATUSES = 'in:pending,completed,failed,overdue';

    public function __construct(private readonly PaymentService $payments)
    {
    }

    /**
     * Display a listing of payments.
     */
    #[Route('', name: 'api_payments_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Payment::class);

        $filters = $validator->validate($request, [
            'sub_id' => 'nullable|integer|exists:subscriptions,sub_id',
            'status' => 'nullable|'.self::STATUSES,
            'method' => 'nullable|'.self::METHODS,
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'payment_date_from' => 'nullable|date',
            'payment_date_to' => 'nullable|date|after_or_equal:payment_date_from',
            'sort' => 'nullable|in:payment_id,amount,payment_date,status',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->payments->paginate($filters, $request));
    }

    /**
     * Store a newly created payment.
     */
    #[Route('', name: 'api_payments_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Payment::class);

        $validated = $validator->validate($request, [
            'sub_id' => 'required|integer|exists:subscriptions,sub_id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'method' => 'nullable|'.self::METHODS,
            'status' => 'nullable|'.self::STATUSES,
        ]);

        return $this->json($this->payments->create($validated)->toArray(withSubscription: true), 201);
    }

    /**
     * Display the specified payment.
     */
    #[Route('/{payment}', name: 'api_payments_show', methods: ['GET'], requirements: ['payment' => '\d+'])]
    public function show(#[MapEntity(id: 'payment')] Payment $payment): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $payment);

        return $this->json($payment->toArray(withSubscription: true));
    }

    /**
     * Update the specified payment.
     */
    #[Route('/{payment}', name: 'api_payments_update', methods: ['PUT', 'PATCH'], requirements: ['payment' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'payment')] Payment $payment, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $payment);

        $validated = $validator->validate($request, [
            'sub_id' => 'sometimes|required|integer|exists:subscriptions,sub_id',
            'amount' => 'sometimes|required|numeric|min:0',
            'payment_date' => 'sometimes|required|date',
            'method' => 'nullable|'.self::METHODS,
            'status' => 'nullable|'.self::STATUSES,
        ]);

        $this->payments->update($payment, $validated);

        return $this->json($payment->toArray(withSubscription: true));
    }

    /**
     * Remove the specified payment.
     */
    #[Route('/{payment}', name: 'api_payments_destroy', methods: ['DELETE'], requirements: ['payment' => '\d+'])]
    public function destroy(#[MapEntity(id: 'payment')] Payment $payment): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $payment);

        $this->payments->delete($payment);

        return $this->json(['message' => 'Payment deleted successfully']);
    }
}
