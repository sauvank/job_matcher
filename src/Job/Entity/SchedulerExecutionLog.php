<?php

declare(strict_types=1);

namespace App\Job\Entity;

use App\Job\Enum\SchedulerExecutionStatus;
use App\Job\Repository\SchedulerExecutionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchedulerExecutionLogRepository::class)]
#[ORM\Table(name: 'scheduler_execution_log')]
#[ORM\Index(name: 'idx_scheduler_exec_started_at', columns: ['started_at'])]
#[ORM\Index(name: 'idx_scheduler_exec_schedule_name', columns: ['schedule_name'])]
#[ORM\Index(name: 'idx_scheduler_exec_status', columns: ['status'])]
final class SchedulerExecutionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $scheduleName;

    #[ORM\Column(length: 255)]
    private string $commandOrMessage;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(enumType: SchedulerExecutionStatus::class)]
    private SchedulerExecutionStatus $status;

    #[ORM\Column]
    private int $processedCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 100)]
    private string $triggeredBy;

    public function __construct(
        string $scheduleName,
        string $commandOrMessage,
        string $triggeredBy = 'scheduler',
    ) {
        $this->scheduleName = $scheduleName;
        $this->commandOrMessage = $commandOrMessage;
        $this->triggeredBy = $triggeredBy;
        $this->startedAt = new \DateTimeImmutable();
        $this->status = SchedulerExecutionStatus::RUNNING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScheduleName(): string
    {
        return $this->scheduleName;
    }

    public function getCommandOrMessage(): string
    {
        return $this->commandOrMessage;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getDurationFormatted(): string
    {
        if ($this->durationMs === null) {
            return '-';
        }

        if ($this->durationMs < 1000) {
            return sprintf('%d ms', $this->durationMs);
        }

        $seconds = $this->durationMs / 1000;
        if ($seconds < 60) {
            return sprintf('%.2f s', $seconds);
        }

        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = (int) ($seconds % 60);

        return sprintf('%d m %d s', $minutes, $remainingSeconds);
    }

    public function getStatus(): SchedulerExecutionStatus
    {
        return $this->status;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getTriggeredBy(): string
    {
        return $this->triggeredBy;
    }

    public function complete(int $processedCount = 0, ?string $details = null): void
    {
        $this->finishedAt = new \DateTimeImmutable();
        $this->status = SchedulerExecutionStatus::SUCCESS;
        $this->processedCount = $processedCount;
        $this->details = $details;
        $this->calculateDuration();
    }

    public function fail(string $errorMessage, int $processedCount = 0, ?string $details = null): void
    {
        $this->finishedAt = new \DateTimeImmutable();
        $this->status = SchedulerExecutionStatus::FAILED;
        $this->errorMessage = mb_substr($errorMessage, 0, 5000);
        $this->processedCount = $processedCount;
        $this->details = $details;
        $this->calculateDuration();
    }

    private function calculateDuration(): void
    {
        if ($this->finishedAt !== null) {
            $startMicro = (float) $this->startedAt->format('U.u');
            $finishMicro = (float) $this->finishedAt->format('U.u');
            $this->durationMs = (int) round(($finishMicro - $startMicro) * 1000);
            if ($this->durationMs < 0) {
                $this->durationMs = 0;
            }
        }
    }
}
