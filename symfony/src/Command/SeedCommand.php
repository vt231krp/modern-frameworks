<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Attendance;
use App\Entity\Coach;
use App\Entity\Equipment;
use App\Entity\GymClass;
use App\Entity\Member;
use App\Entity\MembershipPlan;
use App\Entity\Payment;
use App\Entity\Registration;
use App\Entity\Room;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\AttendanceStatus;
use App\Enum\EquipmentCondition;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Idempotent demo data, mirroring database/seeders/DatabaseSeeder.php.
 */
#[AsCommand(name: 'app:seed', description: 'Populate the database with demo data')]
class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $admin = $this->upsertUser('Admin User', 'admin@gym.test', Role::Admin);
        $this->upsertUser('Manager User', 'manager@gym.test', Role::Manager);
        $client = $this->upsertUser('Client User', 'client@gym.test', Role::Client);

        $member = $this->entityManager->getRepository(Member::class)->findOneBy(['user' => $client]);

        if (!$member instanceof Member) {
            $member = new Member();
            $member->setUser($client);
            $member->setName('Client User');
            $member->setPhone('+380991234567');
            $member->setJoinDate(new \DateTimeImmutable('today'));
            $client->setMember($member);
            $this->entityManager->persist($member);
        }

        $monthly = $this->upsertPlan('Місячний', 30, '900.00', 'Повний доступ на 30 днів');
        $this->upsertPlan('Квартальний', 90, '2400.00', 'Повний доступ на 90 днів');
        $this->upsertPlan('Річний', 365, '8000.00', 'Повний доступ на рік');

        $mainRoom = $this->upsertRoom('Головна зала', 30, 'Тренажери та вільні ваги');
        $yogaRoom = $this->upsertRoom('Зала йоги', 15, 'Групові заняття та розтяжка');

        $this->upsertEquipment('Гантелі', 20, EquipmentCondition::Good, $mainRoom);
        $this->upsertEquipment('Килимки для йоги', 15, EquipmentCondition::Excellent, $yogaRoom);
        $this->upsertEquipment('Боксерська груша', 2, EquipmentCondition::Fair, $mainRoom);

        $coach = $this->upsertCoach('Олександр Коваленко', '+380671112233');
        $yogaCoach = $this->upsertCoach('Ірина Петренко', '+380672223344');

        $boxing = $this->upsertClass('Бокс', $coach, $mainRoom, 20, (new \DateTimeImmutable('+2 days'))->setTime(18, 0));
        $this->upsertClass('Йога', $yogaCoach, $yogaRoom, 12, (new \DateTimeImmutable('+3 days'))->setTime(10, 0));

        $subscription = $this->entityManager->getRepository(Subscription::class)->findOneBy(['member' => $member]);

        if (!$subscription instanceof Subscription) {
            $start = new \DateTimeImmutable('today');
            $subscription = new Subscription();
            $subscription->setMember($member);
            $subscription->setPlan($monthly);
            $subscription->setStartDate($start);
            $subscription->setEndDate($start->modify(sprintf('+%d days', $monthly->getDurationDays())));
            $subscription->setPrice($monthly->getPrice());
            $this->entityManager->persist($subscription);
        }

        $payment = $this->entityManager->getRepository(Payment::class)->findOneBy(['subscription' => $subscription]);

        if (!$payment instanceof Payment) {
            $payment = new Payment();
            $payment->setSubscription($subscription);
            $payment->setAmount($monthly->getPrice());
            $payment->setPaymentDate(new \DateTimeImmutable('today'));
            $payment->setMethod(PaymentMethod::Card);
            $payment->setStatus(PaymentStatus::Completed);
            $this->entityManager->persist($payment);
        }

        $registration = $this->entityManager->getRepository(Registration::class)
            ->findOneBy(['member' => $member, 'gymClass' => $boxing]);

        if (!$registration instanceof Registration) {
            $registration = new Registration();
            $registration->setMember($member);
            $registration->setGymClass($boxing);
            $this->entityManager->persist($registration);
        }

        $attendance = $this->entityManager->getRepository(Attendance::class)
            ->findOneBy(['member' => $member, 'gymClass' => $boxing]);

        if (!$attendance instanceof Attendance) {
            $attendance = new Attendance();
            $attendance->setMember($member);
            $attendance->setGymClass($boxing);
            $attendance->setStatus(AttendanceStatus::Present);
            $attendance->setCheckedInAt(new \DateTimeImmutable());
            $this->entityManager->persist($attendance);
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('Demo admin: %s (password: password)', $admin->getEmail()));

        return Command::SUCCESS;
    }

    private function upsertUser(string $name, string $email, Role $role): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $this->entityManager->persist($user);
        }

        $user->setName($name);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setRole($role);

        return $user;
    }

    private function upsertPlan(string $name, int $durationDays, string $price, string $description): MembershipPlan
    {
        $plan = $this->entityManager->getRepository(MembershipPlan::class)->findOneBy(['name' => $name]);

        if (!$plan instanceof MembershipPlan) {
            $plan = new MembershipPlan();
            $plan->setName($name);
            $this->entityManager->persist($plan);
        }

        $plan->setDurationDays($durationDays);
        $plan->setPrice($price);
        $plan->setDescription($description);
        $plan->setIsActive(true);

        return $plan;
    }

    private function upsertRoom(string $name, int $capacity, string $description): Room
    {
        $room = $this->entityManager->getRepository(Room::class)->findOneBy(['name' => $name]);

        if (!$room instanceof Room) {
            $room = new Room();
            $room->setName($name);
            $this->entityManager->persist($room);
        }

        $room->setCapacity($capacity);
        $room->setDescription($description);

        return $room;
    }

    private function upsertEquipment(string $name, int $quantity, EquipmentCondition $condition, Room $room): Equipment
    {
        $equipment = $this->entityManager->getRepository(Equipment::class)
            ->findOneBy(['name' => $name, 'room' => $room]);

        if (!$equipment instanceof Equipment) {
            $equipment = new Equipment();
            $equipment->setName($name);
            $this->entityManager->persist($equipment);
        }

        $equipment->setQuantity($quantity);
        $equipment->setCondition($condition);
        $equipment->setRoom($room);

        return $equipment;
    }

    private function upsertCoach(string $name, string $phone): Coach
    {
        $coach = $this->entityManager->getRepository(Coach::class)->findOneBy(['name' => $name]);

        if (!$coach instanceof Coach) {
            $coach = new Coach();
            $coach->setName($name);
            $this->entityManager->persist($coach);
        }

        $coach->setPhone($phone);

        return $coach;
    }

    private function upsertClass(
        string $title,
        Coach $coach,
        Room $room,
        int $capacity,
        \DateTimeImmutable $classTime,
    ): GymClass {
        $class = $this->entityManager->getRepository(GymClass::class)->findOneBy(['title' => $title]);

        if (!$class instanceof GymClass) {
            $class = new GymClass();
            $class->setTitle($title);
            $this->entityManager->persist($class);
        }

        $class->setCoach($coach);
        $class->setRoom($room);
        $class->setCapacity($capacity);
        $class->setClassTime($classTime);

        return $class;
    }
}
