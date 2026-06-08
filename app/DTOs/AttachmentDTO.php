<?php

namespace App\DTOs;

final class AttachmentDTO
{
    public function __construct(
        public readonly string $attachmentId,
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly int $size,
        // Raw content (base64url encoded from Gmail API) – decoded on demand
        public readonly ?string $rawData = null,
    ) {}

    public function isJson(): bool
    {
        return str_contains($this->mimeType, 'json')
            || str_ends_with(strtolower($this->filename), '.json');
    }

    public function isPdf(): bool
    {
        return str_contains($this->mimeType, 'pdf')
            || str_ends_with(strtolower($this->filename), '.pdf');
    }

    /**
     * Decode the base64url payload Gmail returns and parse it as JSON.
     * Returns null when the content is not valid JSON or unavailable.
     */
    public function decodedJsonContent(): ?array
    {
        if (! $this->rawData) {
            return null;
        }

        // Gmail uses base64url encoding (- and _ instead of + and /)
        $decoded = base64_decode(strtr($this->rawData, '-_', '+/'));

        if ($decoded === false) {
            return null;
        }

        try {
            $parsed = json_decode($decoded, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Decode the PDF bytes so they can be streamed or embedded.
     */
    public function decodedPdfBytes(): ?string
    {
        if (! $this->rawData) {
            return null;
        }

        $bytes = base64_decode(strtr($this->rawData, '-_', '+/'));

        return $bytes !== false ? $bytes : null;
    }
}
