<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class PaymentService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function paginate(array $filters, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = (int) ($filters['per_page'] ?? 15);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Payment::class, 'p')
            ->leftJoin('p.subscription', 's')
            ->leftJoin('s.member', 'm')
            ->addSelect('s', 'm');

        if (isset($filters['sub_id'])) {
            $qb->andWhere('s.id = :subId')->setParameter('subId', $filters['sub_id']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('p.status = :status')->setParameter('status', $filters['status']);
        }

        if (isset($filters['method'])) {
            $qb->andWhere('p.method = :method')->setParameter('method', $filters['method']);
        }

        if (isset($filters['amount_min'])) {
            $qb->andWhere('p.amount >= :amountMin')->setParameter('amountMin', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $qb->andWhere('p.amount <= :amountMax')->setParameter('amountMax', $filters['amount_max']);
        }

        if (isset($filters['payment_date_from'])) {
            $qb->andWhere('p.paymentDate >= :dateFrom')->setParameter('dateFrom', $filters['payment_date_from']);
        }

        if (isset($filters['payment_date_to'])) {
            $qb->andWhere('p.paymentDate <= :dateTo')->setParameter('dateTo', $filters['payment_date_to']);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $sortMap = [
            'payment_id' => 'p.id',
            'amount' => 'p.amount',
            'payment_date' => 'p.paymentDate',
            'status' => 'p.status',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'payment_id'] ?? 'p.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Payment> $payments */
        $payments = $qb->select('p', 's', 'm')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return Paginator::paginate(
            array_map(static fn (Payment $payment): array => $payment->toArray(withSubscription: true), $payments),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Payment
    {
        $payment = new Payment();
        $payment->setSubscription($this->entityManager->find(Subscription::class, $data['sub_id']));
        $payment->setAmount((string) $data['amount']);
        $payment->setPaymentDate($data['payment_date']);
        $payment->setMethod(isset($data['method']) ? PaymentMethod::from($data['method']) : PaymentMethod::Card);
        $payment->setStatus(isset($data['status']) ? PaymentStatus::from($data['status']) : PaymentStatus::Pending);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function update(Payment $payment, array $data): Payment
    {
        if (isset($data['sub_id'])) {
            $payment->setSubscription($this->entityManager->find(Subscription::class, $data['sub_id']));
        }

        if (isset($data['amount'])) {
            $payment->setAmount((string) $data['amount']);
        }

        if (isset($data['payment_date'])) {
            $payment->setPaymentDate($data['payment_date']);
        }

        if (isset($data['method'])) {
            $payment->setMethod(PaymentMethod::from($data['method']));
        }

        if (isset($data['status'])) {
            $payment->setStatus(PaymentStatus::from($data['status']));
        }

        $this->entityManager->flush();

        return $payment;
    }

    public function delete(Payment $payment): void
    {
        $this->entityManager->remove($payment);
        $this->entityManager->flush();
    }
}
