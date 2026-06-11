<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface ProveedorCorreoInterface
{
    public function obtenerNombre(): string;

    public function obtenerEtiqueta(): string;

    public function obtenerUrlAuth(): string;

    public function manejarCallback(string $code): void;

    public function estaConectado(): bool;

    /**
     * @param  array{keywords?: string[], from?: string, max_results?: int}  $filters
     * @return Collection<int, \App\DTOs\MensajeCorreoDTO>
     */
    public function buscar(array $filters): Collection;

    /**
     * @return Collection<int, \App\DTOs\AdjuntoDTO>
     */
    public function obtenerAdjuntos(string $messageId): Collection;
}
