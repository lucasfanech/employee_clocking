<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkDayRepository;
use App\Time\DayTimes;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The four clocking times of one user on one calendar day, or a day off.
 */
#[ORM\Entity(repositoryClass: WorkDayRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_WORK_DAY_USER_DATE', fields: ['user', 'date'])]
class WorkDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'workDays')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $morningIn = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lunchOut = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $afternoonIn = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eveningOut = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $dayOff = false;

    public function __construct(User $user, \DateTimeImmutable $date)
    {
        $this->user = $user;
        $this->date = $date->setTime(0, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getMorningIn(): ?\DateTimeImmutable
    {
        return $this->morningIn;
    }

    public function setMorningIn(?\DateTimeImmutable $morningIn): static
    {
        $this->morningIn = $morningIn;

        return $this;
    }

    public function getLunchOut(): ?\DateTimeImmutable
    {
        return $this->lunchOut;
    }

    public function setLunchOut(?\DateTimeImmutable $lunchOut): static
    {
        $this->lunchOut = $lunchOut;

        return $this;
    }

    public function getAfternoonIn(): ?\DateTimeImmutable
    {
        return $this->afternoonIn;
    }

    public function setAfternoonIn(?\DateTimeImmutable $afternoonIn): static
    {
        $this->afternoonIn = $afternoonIn;

        return $this;
    }

    public function getEveningOut(): ?\DateTimeImmutable
    {
        return $this->eveningOut;
    }

    public function setEveningOut(?\DateTimeImmutable $eveningOut): static
    {
        $this->eveningOut = $eveningOut;

        return $this;
    }

    public function isDayOff(): bool
    {
        return $this->dayOff;
    }

    public function setDayOff(bool $dayOff): static
    {
        $this->dayOff = $dayOff;
        if ($dayOff) {
            $this->clearTimes();
        }

        return $this;
    }

    public function clearTimes(): static
    {
        $this->morningIn = $this->lunchOut = $this->afternoonIn = $this->eveningOut = null;

        return $this;
    }

    public function hasAnyTime(): bool
    {
        return null !== $this->morningIn || null !== $this->lunchOut
            || null !== $this->afternoonIn || null !== $this->eveningOut;
    }

    /** True when the row carries no information and can be deleted. */
    public function isEmpty(): bool
    {
        return !$this->dayOff && !$this->hasAnyTime();
    }

    public function toDayTimes(): DayTimes
    {
        return DayTimes::fromDateTimes($this->morningIn, $this->lunchOut, $this->afternoonIn, $this->eveningOut);
    }

    /** Filled times must follow the order of the day. */
    #[Assert\Callback]
    public function validateChronology(ExecutionContextInterface $context): void
    {
        $fields = ['morningIn', 'lunchOut', 'afternoonIn', 'eveningOut'];
        $previous = null;
        foreach ($fields as $field) {
            $value = $this->{$field};
            if (null === $value) {
                continue;
            }
            if (null !== $previous && $value->format('H:i') < $previous->format('H:i')) {
                $context->buildViolation('Times must be in chronological order.')
                    ->atPath($field)
                    ->addViolation();

                return;
            }
            $previous = $value;
        }
    }
}
