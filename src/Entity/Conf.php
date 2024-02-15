<?php

namespace App\Entity;

use App\Repository\ConfRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

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

    #[ORM\Column(length: 255)]
    private ?string $time_hoursToDoWeek = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?UserInterface $user;

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

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

    public function getTimeHoursToDoWeek(): ?string
    {
        return $this->time_hoursToDoWeek;
    }

    public function setTimeHoursToDoWeek(?string $time_hoursToDoWeek): self
    {
        $this->time_hoursToDoWeek = $time_hoursToDoWeek;

        return $this;
    }

}
