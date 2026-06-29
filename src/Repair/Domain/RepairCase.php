<?php

declare(strict_types=1);

namespace Reborn\Repair\Domain;

final class RepairCase
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $category,
        public readonly string $status,
        public readonly ?string $recognizedProduct,
        public readonly ?string $recognizedComponent,
        public readonly float $confidenceScore,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['title'],
            (string) $row['description'],
            (string) $row['category'],
            (string) $row['status'],
            $row['recognized_product'] !== null ? (string) $row['recognized_product'] : null,
            $row['recognized_component'] !== null ? (string) $row['recognized_component'] : null,
            (float) $row['confidence_score'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'recognized_product' => $this->recognizedProduct,
            'recognized_component' => $this->recognizedComponent,
            'confidence_score' => $this->confidenceScore,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
