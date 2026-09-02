<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\WorkSchedule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Account lifecycle: every account gets a hashed password and a default work schedule.
 */
final class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function create(string $email, string $plainPassword, bool $admin = false): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($admin ? [User::ROLE_ADMIN] : []);
        $this->setPassword($user, $plainPassword);
        new WorkSchedule($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function setPassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }

    public function setAdmin(User $user, bool $admin): void
    {
        $roles = array_values(array_diff($user->getRoles(), [User::ROLE_ADMIN, User::ROLE_USER]));
        if ($admin) {
            $roles[] = User::ROLE_ADMIN;
        }
        $user->setRoles($roles);
    }

    /** The schedule of the user, created with the default values if missing (not flushed). */
    public function ensureSchedule(User $user): WorkSchedule
    {
        $schedule = $user->getSchedule();
        if (null === $schedule) {
            $schedule = new WorkSchedule($user);
            $this->entityManager->persist($schedule);
        }

        return $schedule;
    }
}
