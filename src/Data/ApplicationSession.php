<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Data;

final class ApplicationSession
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $applicationId,
        public string $company,
        public string $position,
        public string $status,
        public string $busDir,
        public string $resultDir,
        public ?string $parentId = null,
        public ?string $writerId = null,
        public ?string $criticId = null,
        public ?string $completedAt = null,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->extra, [
            'application_id' => $this->applicationId,
            'company' => $this->company,
            'position' => $this->position,
            'status' => $this->status,
            'bus_dir' => $this->busDir,
            'result_dir' => $this->resultDir,
            'parent_id' => $this->parentId,
            'writer_id' => $this->writerId,
            'critic_id' => $this->criticId,
            'completed_at' => $this->completedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $applicationId = (string) ($data['application_id'] ?? '');
        $company = (string) ($data['company'] ?? '');
        $position = (string) ($data['position'] ?? '');
        $status = (string) ($data['status'] ?? 'IN_PROGRESS');
        $busDir = (string) ($data['bus_dir'] ?? 'bus');
        $resultDir = (string) ($data['result_dir'] ?? 'result');
        $parentId = isset($data['parent_id']) && is_string($data['parent_id']) ? $data['parent_id'] : null;
        $writerId = isset($data['writer_id']) && is_string($data['writer_id']) ? $data['writer_id'] : null;
        $criticId = isset($data['critic_id']) && is_string($data['critic_id']) ? $data['critic_id'] : null;
        $completedAt = isset($data['completed_at']) && is_string($data['completed_at']) ? $data['completed_at'] : null;

        $known = [
            'application_id', 'company', 'position', 'status',
            'bus_dir', 'result_dir', 'parent_id', 'writer_id', 'critic_id', 'completed_at',
        ];
        $extra = array_diff_key($data, array_flip($known));

        return new self(
            applicationId: $applicationId,
            company: $company,
            position: $position,
            status: $status,
            busDir: $busDir,
            resultDir: $resultDir,
            parentId: $parentId,
            writerId: $writerId,
            criticId: $criticId,
            completedAt: $completedAt,
            extra: $extra,
        );
    }
}
