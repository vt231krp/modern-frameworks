<?php

declare(strict_types=1);

namespace App\Security;

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
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A single voter replicating the Laravel policies in app/Policies. Admins pass
 * every check, exactly like Gate::before in AppServiceProvider.
 */
class GymVoter extends Voter
{
    private const ATTRIBUTES = ['viewAny', 'view', 'create', 'update', 'delete'];

    /**
     * public        - any authenticated user
     * authenticated - any authenticated user (registration creation)
     * staff         - manager or admin
     * admin         - admin only
     * ownerOrStaff  - manager/admin or the owner of the linked member profile
     */
    private const RULES = [
        Coach::class => ['viewAny' => 'public', 'view' => 'public', 'create' => 'staff', 'update' => 'staff', 'delete' => 'admin'],
        Room::class => ['viewAny' => 'public', 'view' => 'public', 'create' => 'staff', 'update' => 'staff', 'delete' => 'admin'],
        Equipment::class => ['viewAny' => 'public', 'view' => 'public', 'create' => 'staff', 'update' => 'staff', 'delete' => 'admin'],
        MembershipPlan::class => ['viewAny' => 'public', 'view' => 'public', 'create' => 'staff', 'update' => 'staff', 'delete' => 'admin'],
        GymClass::class => ['viewAny' => 'public', 'view' => 'public', 'create' => 'staff', 'update' => 'staff', 'delete' => 'staff'],
        Member::class => ['viewAny' => 'staff', 'view' => 'ownerOrStaff', 'create' => 'staff', 'update' => 'ownerOrStaff', 'delete' => 'admin'],
        Subscription::class => ['viewAny' => 'staff', 'view' => 'ownerOrStaff', 'create' => 'staff', 'update' => 'staff', 'delete' => 'staff'],
        Payment::class => ['viewAny' => 'staff', 'view' => 'ownerOrStaff', 'create' => 'staff', 'update' => 'staff', 'delete' => 'admin'],
        Registration::class => ['viewAny' => 'staff', 'view' => 'ownerOrStaff', 'create' => 'authenticated', 'update' => 'ownerOrStaff', 'delete' => 'ownerOrStaff'],
        Attendance::class => ['viewAny' => 'staff', 'view' => 'ownerOrStaff', 'create' => 'staff', 'update' => 'staff', 'delete' => 'admin'],
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        $class = \is_string($subject) ? $subject : (\is_object($subject) ? $subject::class : null);

        return null !== $class && isset(self::RULES[$class]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Gate::before equivalent: admins bypass every policy check.
        if (Role::Admin === $user->getRole()) {
            return true;
        }

        $class = \is_string($subject) ? $subject : $subject::class;
        $rule = self::RULES[$class][$attribute] ?? 'deny';

        return match ($rule) {
            'public', 'authenticated' => true,
            'admin' => $user->getRole()->isAdmin(),
            'staff' => $user->getRole()->isStaff(),
            'ownerOrStaff' => $user->getRole()->isStaff() || $this->owns($user, $subject),
            default => false,
        };
    }

    private function owns(User $user, object $subject): bool
    {
        $member = match (true) {
            $subject instanceof Member => $subject,
            $subject instanceof Subscription => $subject->getMember(),
            $subject instanceof Registration => $subject->getMember(),
            $subject instanceof Attendance => $subject->getMember(),
            $subject instanceof Payment => $subject->getSubscription()?->getMember(),
            default => null,
        };

        return null !== $member && $member->getUser()?->getId() === $user->getId();
    }
}
