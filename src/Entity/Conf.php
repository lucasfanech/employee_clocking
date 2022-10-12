<?php

namespace App\Entity;

use App\Repository\ConfRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfRepository::class)]
class Conf
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $time_lunchBreak = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $time_exception = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $days_exception = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $time_hoursToDoWeek = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimeLunchBreak(): ?\DateTimeInterface
    {
        return $this->time_lunchBreak;
    }

    public function setTimeLunchBreak(\DateTimeInterface $time_lunchBreak): self
    {
        $this->time_lunchBreak = $time_lunchBreak;

        return $this;
    }

    public function getTimeException(): ?\DateTimeInterface
    {
        return $this->time_exception;
    }

    public function setTimeException(\DateTimeInterface $time_exception): self
    {
        $this->time_exception = $time_exception;

        return $this;
    }

    public function getDaysException(): ?string
    {
        return $this->days_exception;
    }

    public function setDaysException(?string $days_exception): self
    {
        $this->days_exception = $days_exception;

        return $this;
    }

    public function getTimeHoursToDoWeek(): ?\DateTimeInterface
    {
        return $this->time_hoursToDoWeek;
    }

    public function setTimeHoursToDoWeek(\DateTimeInterface $time_hoursToDoWeek): self
    {
        $this->time_hoursToDoWeek = $time_hoursToDoWeek;

        return $this;
    }

}
