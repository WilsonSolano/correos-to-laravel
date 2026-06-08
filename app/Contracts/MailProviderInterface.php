<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface MailProviderInterface
{
    /**
     * Return the provider slug identifier (e.g. 'gmail', 'outlook').
     */
    public function getName(): string;

    /**
     * Return the human-readable label.
     */
    public function getLabel(): string;

    /**
     * Build the OAuth redirect URL for this provider.
     */
    public function getAuthUrl(): string;

    /**
     * Exchange the authorization code for tokens and persist them.
     */
    public function handleCallback(string $code): void;

    /**
     * Whether a valid (non-expired) token exists for this provider.
     */
    public function isConnected(): bool;

    /**
     * Search messages by keywords and/or sender.
     *
     * @param  array{keywords?: string[], from?: string, max_results?: int}  $filters
     * @return Collection<int, \App\DTOs\MailMessageDTO>
     */
    public function search(array $filters): Collection;

    /**
     * Retrieve all attachments for a given message ID.
     *
     * @return Collection<int, \App\DTOs\AttachmentDTO>
     */
    public function getAttachments(string $messageId): Collection;
}
