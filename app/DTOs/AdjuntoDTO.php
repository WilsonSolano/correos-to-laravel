<?php

namespace App\DTOs;

final class AdjuntoDTO
{
    public function __construct(
        public readonly string $idAdjunto,
        public readonly string $nombreArchivo,
        public readonly string $tipoMime,
        public readonly int $tamaño,
        public readonly ?string $datosCrudos = null,
    ) {}

    public function esJson(): bool
    {
        return str_contains($this->tipoMime, 'json')
            || str_ends_with(strtolower($this->nombreArchivo), '.json');
    }

    public function esPdf(): bool
    {
        return str_contains($this->tipoMime, 'pdf')
            || str_ends_with(strtolower($this->nombreArchivo), '.pdf');
    }

    public function contenidoJsonDecodificado(): ?array
    {
        if (! $this->datosCrudos) {
            return null;
        }

        $decoded = base64_decode(strtr($this->datosCrudos, '-_', '+/'));

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

    public function bytesPdfDecodificados(): ?string
    {
        if (! $this->datosCrudos) {
            return null;
        }

        $bytes = base64_decode(strtr($this->datosCrudos, '-_', '+/'));

        return $bytes !== false ? $bytes : null;
    }
}
