<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MensajeCorreoDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $remitente,
        public readonly string $nombreRemitente,
        public readonly string $asunto,
        public readonly CarbonImmutable $fecha,
        /** @var Collection<int, AdjuntoDTO> */
        public readonly Collection $adjuntos,
        public readonly string $resumen = '',
    ) {}

    public function tieneAdjuntoJson(): bool
    {
        return $this->adjuntos->contains(
            fn (AdjuntoDTO $a) => $a->esJson()
        );
    }

    public function tieneAdjuntoPdf(): bool
    {
        return $this->adjuntos->contains(
            fn (AdjuntoDTO $a) => $a->esPdf()
        );
    }
}
