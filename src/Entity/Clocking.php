<?php

namespace App\Entity;

use App\Repository\ClockingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClockingRepository::class)]
class Clocking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $week_ref = null;

    #[ORM\Column(length: 255)]
    private ?string $day = null;

    #[ORM\Column(length: 255)]
    private ?string $partOfDay = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $clockingHour = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekRef(): ?int
    {
        return $this->week_ref;
    }

    public function setWeekRef(int $week_ref): self
    {
        $this->week_ref = $week_ref;

        return $this;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(string $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getPartOfDay(): ?string
    {
        return $this->partOfDay;
    }

    public function setPartOfDay(string $partOfDay): self
    {
        $this->partOfDay = $partOfDay;

        return $this;
    }

    public function getClockingHour(): ?\DateTimeInterface
    {
        return $this->clockingHour;
    }

    public function setClockingHour(\DateTimeInterface $clockingHour): self
    {
        $this->clockingHour = $clockingHour;

        return $this;
    }
}
