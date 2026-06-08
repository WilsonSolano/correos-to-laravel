<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MailMessageDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $from,
        public readonly string $fromName,
        public readonly string $subject,
        public readonly CarbonImmutable $date,
        /** @var Collection<int, AttachmentDTO> */
        public readonly Collection $attachments,
        public readonly string $snippet = '',
    ) {}

    public function hasJsonAttachment(): bool
    {
        return $this->attachments->contains(
            fn (AttachmentDTO $a) => $a->isJson()
        );
    }

    public function hasPdfAttachment(): bool
    {
        return $this->attachments->contains(
            fn (AttachmentDTO $a) => $a->isPdf()
        );
    }
}
