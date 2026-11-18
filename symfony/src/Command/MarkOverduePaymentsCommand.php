<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'payments:mark-overdue',
    description: 'Mark pending payments with a past payment date as overdue',
)]
class MarkOverduePaymentsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updated = $this->entityManager
            ->createQuery(
                'UPDATE App\Entity\Payment p
                 SET p.status = :overdue
                 WHERE p.status = :pending AND p.paymentDate < :today',
            )
            ->setParameter('overdue', PaymentStatus::Overdue->value)
            ->setParameter('pending', PaymentStatus::Pending->value)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->execute();

        $output->writeln(sprintf('Marked %d payment(s) as overdue.', $updated));

        return Command::SUCCESS;
    }
}
