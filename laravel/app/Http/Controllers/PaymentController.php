<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private const METHODS = ['cash', 'card', 'online', 'transfer'];

    private const STATUSES = ['pending', 'completed', 'failed', 'overdue'];

    public function __construct(private readonly PaymentService $payments)
    {
        $this->authorizeResource(Payment::class, 'payment');
    }

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'sub_id' => 'nullable|integer|exists:subscriptions,sub_id',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'method' => ['nullable', Rule::in(self::METHODS)],
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'payment_date_from' => 'nullable|date',
            'payment_date_to' => ['nullable', 'date', Rule::when(
                $request->filled('payment_date_from'),
                'after_or_equal:payment_date_from'
            )],
            'sort' => 'nullable|in:payment_id,amount,payment_date,status',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->payments->paginate($filters));
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_id' => 'required|integer|exists:subscriptions,sub_id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'method' => ['nullable', Rule::in(self::METHODS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $payment = $this->payments->create($validated);

        return response()->json($payment->load('subscription'), 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load('subscription.member');

        return response()->json($payment);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'sub_id' => 'sometimes|required|integer|exists:subscriptions,sub_id',
            'amount' => 'sometimes|required|numeric|min:0',
            'payment_date' => 'sometimes|required|date',
            'method' => ['nullable', Rule::in(self::METHODS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $this->payments->update($payment, $validated);

        return response()->json($payment->load('subscription'));
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment): JsonResponse
    {
        $this->payments->delete($payment);

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
