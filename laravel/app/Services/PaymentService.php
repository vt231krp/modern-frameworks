<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Payment::with('subscription.member')
            ->when(isset($filters['sub_id']), fn ($q) => $q->where('sub_id', $filters['sub_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['method']), fn ($q) => $q->where('method', $filters['method']))
            ->when(isset($filters['amount_min']), fn ($q) => $q->where('amount', '>=', $filters['amount_min']))
            ->when(isset($filters['amount_max']), fn ($q) => $q->where('amount', '<=', $filters['amount_max']))
            ->when(isset($filters['payment_date_from']), fn ($q) => $q->whereDate('payment_date', '>=', $filters['payment_date_from']))
            ->when(isset($filters['payment_date_to']), fn ($q) => $q->whereDate('payment_date', '<=', $filters['payment_date_to']))
            ->orderBy($filters['sort'] ?? 'payment_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment;
    }

    public function delete(Payment $payment): void
    {
        $payment->delete();
    }
}
