<?php

namespace App\Services\Mail;

use App\Contracts\ProveedorCorreoInterface;
use App\DTOs\AdjuntoDTO;
use App\DTOs\MensajeCorreoDTO;
use App\Models\OAuthToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProveedorGmail implements ProveedorCorreoInterface
{
    private const AUTH_BASE   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    private const API_BASE    = 'https://gmail.googleapis.com/gmail/v1/users/me';
    private const SCOPES      = [
        'https://www.googleapis.com/auth/gmail.readonly',
    ];

    public function obtenerNombre(): string
    {
        return 'gmail';
    }

    public function obtenerEtiqueta(): string
    {
        return 'Gmail';
    }

    public function obtenerUrlAuth(): string
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

    public function manejarCallback(string $code): void
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

    public function estaConectado(): bool
    {
        $token = OAuthToken::paraProveedor('gmail');

        return $token !== null && $token->esValido();
    }

    private function tokenAccesoFresco(): string
    {
        $token = OAuthToken::paraProveedor('gmail');

        if (! $token) {
            throw new \RuntimeException('Gmail no conectado. Por favor autentícate primero.');
        }

        if ($token->estaExpirado() && $token->refresh_token) {
            $token = $this->refrescarToken($token);
        }

        return $token->access_token;
    }

    private function refrescarToken(OAuthToken $token): OAuthToken
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

    public function buscar(array $filters): Collection
    {
        $accessToken = $this->tokenAccesoFresco();
        $query       = $this->construirConsultaGmail($filters);
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

        return $messageIds->map(function (array $item) use ($accessToken): ?MensajeCorreoDTO {
            try {
                return $this->obtenerMensaje($item['id'], $accessToken);
            } catch (\Throwable $e) {
                Log::warning("ProveedorGmail: fallo al obtener mensaje {$item['id']}", [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        })->filter()->values();
    }

    private function construirConsultaGmail(array $filters): string
    {
        $parts = [];

        foreach ($filters['keywords'] ?? [] as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $parts[] = '"'.$keyword.'"';
            }
        }

        if (! empty($filters['from'])) {
            $parts[] = 'from:'.trim($filters['from']);
        }

        $parts[] = 'has:attachment';

        return implode(' ', $parts);
    }

    private function obtenerMensaje(string $messageId, string $accessToken): MensajeCorreoDTO
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
        $fromName = $this->parsearNombreRemitente($from);
        $subject  = $headers['subject'] ?? '(sin asunto)';
        $dateStr  = $headers['date'] ?? null;
        $date     = $dateStr
            ? CarbonImmutable::parse($dateStr)
            : CarbonImmutable::createFromTimestampMs($data['internalDate'] ?? 0);

        $attachments = $this->extraerMetadatosAdjuntos($data['payload'] ?? []);

        return new MensajeCorreoDTO(
            id: $messageId,
            remitente: $from,
            nombreRemitente: $fromName,
            asunto: $subject,
            fecha: $date,
            adjuntos: $attachments,
            resumen: $data['snippet'] ?? '',
        );
    }

    private function parsearNombreRemitente(string $from): string
    {
        if (preg_match('/^(.+?)\s*<.+>$/', $from, $matches)) {
            return trim($matches[1], '"');
        }

        return $from;
    }

    private function extraerMetadatosAdjuntos(array $payload): Collection
    {
        $attachments = collect();

        $this->recorrerPartes($payload, $attachments);

        return $attachments;
    }

    private function recorrerPartes(array $part, Collection &$attachments): void
    {
        $body         = $part['body'] ?? [];
        $attachmentId = $body['attachmentId'] ?? null;
        $filename     = $part['filename'] ?? '';
        $mimeType     = $part['mimeType'] ?? '';
        $size         = $body['size'] ?? 0;

        if ($attachmentId && $filename !== '') {
            $attachments->push(new AdjuntoDTO(
                idAdjunto: $attachmentId,
                nombreArchivo: $filename,
                tipoMime: $mimeType,
                tamaño: $size,
                datosCrudos: null,
            ));
        }

        foreach ($part['parts'] ?? [] as $subPart) {
            $this->recorrerPartes($subPart, $attachments);
        }
    }

    public function obtenerAdjuntos(string $messageId): Collection
    {
        $accessToken = $this->tokenAccesoFresco();

        $response = Http::withToken($accessToken)
            ->get(self::API_BASE."/messages/{$messageId}", ['format' => 'full']);

        $response->throw();
        $payload = $response->json('payload') ?? [];

        $attachmentMeta = collect();
        $this->recorrerPartes($payload, $attachmentMeta);

        return $attachmentMeta->map(function (AdjuntoDTO $meta) use ($messageId, $accessToken): AdjuntoDTO {
            $attResponse = Http::withToken($accessToken)
                ->get(self::API_BASE."/messages/{$messageId}/attachments/{$meta->idAdjunto}");

            if ($attResponse->failed()) {
                return $meta;
            }

            return new AdjuntoDTO(
                idAdjunto: $meta->idAdjunto,
                nombreArchivo: $meta->nombreArchivo,
                tipoMime: $meta->tipoMime,
                tamaño: $meta->tamaño,
                datosCrudos: $attResponse->json('data'),
            );
        });
    }
}
