<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-user working rules: weekly hours to do, standard lunch break and the
 * shorter lunch break allowed on some week days ("exception" days).
 *
 * Every duration is stored as a number of minutes.
 */
#[ORM\Entity(repositoryClass: WorkScheduleRepository::class)]
class WorkSchedule
{
    public const WORKING_DAYS_PER_WEEK = 5;

    public const DEFAULT_WEEKLY_MINUTES = 38 * 60 + 30;   // 38h30
    public const DEFAULT_LUNCH_BREAK_MINUTES = 60;        // 1h00
    public const DEFAULT_SHORT_LUNCH_BREAK_MINUTES = 45;  // 0h45
    public const DEFAULT_SHORT_LUNCH_BREAK_DAYS = [5];    // Friday

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'schedule', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 24 * 60 * self::WORKING_DAYS_PER_WEEK)]
    private int $weeklyMinutes = self::DEFAULT_WEEKLY_MINUTES;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 24 * 60 - 1)]
    private int $lunchBreakMinutes = self::DEFAULT_LUNCH_BREAK_MINUTES;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 24 * 60 - 1)]
    private int $shortLunchBreakMinutes = self::DEFAULT_SHORT_LUNCH_BREAK_MINUTES;

    /**
     * ISO week day numbers (1 = Monday … 5 = Friday) on which the short lunch break applies.
     *
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Range(min: 1, max: 7)])]
    private array $shortLunchBreakDays = self::DEFAULT_SHORT_LUNCH_BREAK_DAYS;

    public function __construct(User $user)
    {
        $this->user = $user;
        $user->setSchedule($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getWeeklyMinutes(): int
    {
        return $this->weeklyMinutes;
    }

    public function setWeeklyMinutes(int $weeklyMinutes): static
    {
        $this->weeklyMinutes = $weeklyMinutes;

        return $this;
    }

    /** Minutes to work on a regular day (weekly hours spread over five days). */
    public function getDailyMinutes(): float
    {
        return $this->weeklyMinutes / self::WORKING_DAYS_PER_WEEK;
    }

    public function getLunchBreakMinutes(): int
    {
        return $this->lunchBreakMinutes;
    }

    public function setLunchBreakMinutes(int $lunchBreakMinutes): static
    {
        $this->lunchBreakMinutes = $lunchBreakMinutes;

        return $this;
    }

    public function getShortLunchBreakMinutes(): int
    {
        return $this->shortLunchBreakMinutes;
    }

    public function setShortLunchBreakMinutes(int $shortLunchBreakMinutes): static
    {
        $this->shortLunchBreakMinutes = $shortLunchBreakMinutes;

        return $this;
    }

    /** @return list<int> */
    public function getShortLunchBreakDays(): array
    {
        return $this->shortLunchBreakDays;
    }

    /** @param list<int> $days */
    public function setShortLunchBreakDays(array $days): static
    {
        $days = array_map(intval(...), $days);
        sort($days);
        $this->shortLunchBreakDays = array_values(array_unique($days));

        return $this;
    }

    public function isShortLunchBreakDay(int $isoWeekDay): bool
    {
        return \in_array($isoWeekDay, $this->shortLunchBreakDays, true);
    }

    /** Minimum lunch break that has to be taken on the given ISO week day. */
    public function getRequiredBreakMinutes(int $isoWeekDay): int
    {
        return $this->isShortLunchBreakDay($isoWeekDay)
            ? $this->shortLunchBreakMinutes
            : $this->lunchBreakMinutes;
    }
}
