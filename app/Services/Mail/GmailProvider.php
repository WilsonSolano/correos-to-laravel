<?php

namespace App\Services\Mail;

use App\Contracts\MailProviderInterface;
use App\DTOs\AttachmentDTO;
use App\DTOs\MailMessageDTO;
use App\Models\OAuthToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmailProvider implements MailProviderInterface
{
    private const AUTH_BASE   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    private const API_BASE    = 'https://gmail.googleapis.com/gmail/v1/users/me';
    private const SCOPES      = [
        'https://www.googleapis.com/auth/gmail.readonly',
    ];

    public function getName(): string
    {
        return 'gmail';
    }

    public function getLabel(): string
    {
        return 'Gmail';
    }

    // ──────────────────────────────────────────────────────────────
    // OAuth
    // ──────────────────────────────────────────────────────────────

    public function getAuthUrl(): string
    {
        return self::AUTH_BASE.'?'.http_build_query([
            'client_id'             => config('services.google.client_id'),
            'redirect_uri'          => config('services.google.redirect'),
            'response_type'         => 'code',
            'scope'                 => implode(' ', self::SCOPES),
            'access_type'           => 'offline',
            'prompt'                => 'consent',
        ]);
    }

    public function handleCallback(string $code): void
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => config('services.google.redirect'),
            'grant_type'    => 'authorization_code',
        ]);

        $response->throw();
        $data = $response->json();

        OAuthToken::updateOrCreate(
            ['provider' => 'gmail'],
            [
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_type'    => $data['token_type'] ?? 'Bearer',
                'expires_in'    => $data['expires_in'] ?? null,
                'expires_at'    => isset($data['expires_in'])
                    ? CarbonImmutable::now()->addSeconds($data['expires_in'])
                    : null,
            ]
        );
    }

    public function isConnected(): bool
    {
        $token = OAuthToken::forProvider('gmail');

        return $token !== null && $token->isValid();
    }

    // ──────────────────────────────────────────────────────────────
    // Token management
    // ──────────────────────────────────────────────────────────────

    private function freshAccessToken(): string
    {
        $token = OAuthToken::forProvider('gmail');

        if (! $token) {
            throw new \RuntimeException('Gmail not connected. Please authenticate first.');
        }

        if ($token->isExpired() && $token->refresh_token) {
            $token = $this->refreshToken($token);
        }

        return $token->access_token;
    }

    private function refreshToken(OAuthToken $token): OAuthToken
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $token->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        $response->throw();
        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'expires_in'   => $data['expires_in'] ?? null,
            'expires_at'   => isset($data['expires_in'])
                ? CarbonImmutable::now()->addSeconds($data['expires_in'])
                : null,
        ]);

        return $token->fresh();
    }

    // ──────────────────────────────────────────────────────────────
    // Search
    // ──────────────────────────────────────────────────────────────

    public function search(array $filters): Collection
    {
        $accessToken = $this->freshAccessToken();
        $query       = $this->buildGmailQuery($filters);
        $maxResults  = $filters['max_results'] ?? 20;

        $listResponse = Http::withToken($accessToken)
            ->get(self::API_BASE.'/messages', [
                'q'          => $query,
                'maxResults' => $maxResults,
            ]);

        $listResponse->throw();
        $messageIds = collect($listResponse->json('messages') ?? []);

        if ($messageIds->isEmpty()) {
            return collect();
        }

        return $messageIds->map(function (array $item) use ($accessToken): ?MailMessageDTO {
            try {
                return $this->fetchMessage($item['id'], $accessToken);
            } catch (\Throwable $e) {
                Log::warning("GmailProvider: failed to fetch message {$item['id']}", [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        })->filter()->values();
    }

    private function buildGmailQuery(array $filters): string
    {
        $parts = [];

        // Keywords (searched in subject + body)
        foreach ($filters['keywords'] ?? [] as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $parts[] = '"'.$keyword.'"';
            }
        }

        // From filter
        if (! empty($filters['from'])) {
            $parts[] = 'from:'.trim($filters['from']);
        }

        // Only emails that have attachments
        $parts[] = 'has:attachment';

        return implode(' ', $parts);
    }

    // ──────────────────────────────────────────────────────────────
    // Fetch a single message (metadata + attachment list, no bodies)
    // ──────────────────────────────────────────────────────────────

    private function fetchMessage(string $messageId, string $accessToken): MailMessageDTO
    {
        $response = Http::withToken($accessToken)
            ->get(self::API_BASE."/messages/{$messageId}", [
                'format' => 'full',
            ]);

        $response->throw();
        $data = $response->json();

        $headers = collect($data['payload']['headers'] ?? [])
            ->pluck('value', 'name')
            ->mapWithKeys(fn ($v, $k) => [strtolower($k) => $v]);

        $from     = $headers['from'] ?? 'Unknown';
        $fromName = $this->parseFromName($from);
        $subject  = $headers['subject'] ?? '(no subject)';
        $dateStr  = $headers['date'] ?? null;
        $date     = $dateStr
            ? CarbonImmutable::parse($dateStr)
            : CarbonImmutable::createFromTimestampMs($data['internalDate'] ?? 0);

        $attachments = $this->extractAttachmentMeta($data['payload'] ?? []);

        return new MailMessageDTO(
            id: $messageId,
            from: $from,
            fromName: $fromName,
            subject: $subject,
            date: $date,
            attachments: $attachments,
            snippet: $data['snippet'] ?? '',
        );
    }

    private function parseFromName(string $from): string
    {
        // "Name Surname <email@example.com>" → "Name Surname"
        if (preg_match('/^(.+?)\s*<.+>$/', $from, $matches)) {
            return trim($matches[1], '"');
        }

        return $from;
    }

    // ──────────────────────────────────────────────────────────────
    // Attachment metadata (recursive over MIME parts)
    // ──────────────────────────────────────────────────────────────

    private function extractAttachmentMeta(array $payload): Collection
    {
        $attachments = collect();

        $this->walkParts($payload, $attachments);

        return $attachments;
    }

    private function walkParts(array $part, Collection &$attachments): void
    {
        $body         = $part['body'] ?? [];
        $attachmentId = $body['attachmentId'] ?? null;
        $filename     = $part['filename'] ?? '';
        $mimeType     = $part['mimeType'] ?? '';
        $size         = $body['size'] ?? 0;

        if ($attachmentId && $filename !== '') {
            $attachments->push(new AttachmentDTO(
                attachmentId: $attachmentId,
                filename: $filename,
                mimeType: $mimeType,
                size: $size,
                rawData: null, // fetched lazily via getAttachments()
            ));
        }

        foreach ($part['parts'] ?? [] as $subPart) {
            $this->walkParts($subPart, $attachments);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Download full attachment content
    // ──────────────────────────────────────────────────────────────

    public function getAttachments(string $messageId): Collection
    {
        $accessToken = $this->freshAccessToken();

        // First get the message to find attachment IDs and filenames
        $response = Http::withToken($accessToken)
            ->get(self::API_BASE."/messages/{$messageId}", ['format' => 'full']);

        $response->throw();
        $payload = $response->json('payload') ?? [];

        $attachmentMeta = collect();
        $this->walkParts($payload, $attachmentMeta);

        return $attachmentMeta->map(function (AttachmentDTO $meta) use ($messageId, $accessToken): AttachmentDTO {
            $attResponse = Http::withToken($accessToken)
                ->get(self::API_BASE."/messages/{$messageId}/attachments/{$meta->attachmentId}");

            if ($attResponse->failed()) {
                return $meta;
            }

            return new AttachmentDTO(
                attachmentId: $meta->attachmentId,
                filename: $meta->filename,
                mimeType: $meta->mimeType,
                size: $meta->size,
                rawData: $attResponse->json('data'),
            );
        });
    }
}
