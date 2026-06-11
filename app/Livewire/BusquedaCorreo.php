<?php

namespace App\Livewire;

use App\DTOs\AdjuntoDTO;
use App\DTOs\MensajeCorreoDTO;
use App\Services\Mail\FabricaProveedorCorreo;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Extractor de Correos')]
#[Layout('layouts.app')]
class BusquedaCorreo extends Component
{
    public string $proveedorSeleccionado = 'gmail';
    public string $entradaPalabrasClave  = '';
    public string $correoRemitente       = '';
    public int    $resultadosMaximos     = 20;

    public bool   $buscado               = false;
    public bool   $cargando              = false;

    /** @var array<int, array> Serialized MensajeCorreoDTO list */
    public array $mensajes = [];

    public ?string $idMensajeAbierto     = null;
    /** @var array<int, array> Serialized AdjuntoDTO list for the open message */
    public array $adjuntosAbiertos       = [];
    public bool  $cargandoAdjuntos       = false;
    public ?string $errorAdjunto         = null;

    #[Computed]
    public function proveedores(): array
    {
        return FabricaProveedorCorreo::opciones();
    }

    #[Computed]
    public function estaConectado(): bool
    {
        try {
            return FabricaProveedorCorreo::crear($this->proveedorSeleccionado)->estaConectado();
        } catch (\Throwable) {
            return false;
        }
    }

    #[Computed]
    public function urlConectar(): string
    {
        return route('oauth.redirigir', $this->proveedorSeleccionado);
    }

    #[Computed]
    public function urlDesconectar(): string
    {
        return route('oauth.desconectar', $this->proveedorSeleccionado);
    }

    public function buscar(): void
    {
        $this->validate([
            'proveedorSeleccionado' => ['required', 'string'],
            'entradaPalabrasClave'  => ['nullable', 'string', 'max:500'],
            'correoRemitente'       => ['nullable', 'email'],
            'resultadosMaximos'     => ['integer', 'min:1', 'max:100'],
        ]);

        $this->mensajes         = [];
        $this->idMensajeAbierto = null;
        $this->adjuntosAbiertos = [];
        $this->buscado          = true;

        $keywords = collect(explode(',', $this->entradaPalabrasClave))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->values()
            ->all();

        $filters = [
            'keywords'    => $keywords,
            'from'        => $this->correoRemitente,
            'max_results' => $this->resultadosMaximos,
        ];

        try {
            $provider = FabricaProveedorCorreo::crear($this->proveedorSeleccionado);

            /** @var Collection<int, MensajeCorreoDTO> $results */
            $results = $provider->buscar($filters);

            $this->mensajes = $results->map(fn (MensajeCorreoDTO $m) => [
                'id'          => $m->id,
                'remitente'   => $m->remitente,
                'nombreRemitente' => $m->nombreRemitente,
                'asunto'      => $m->asunto,
                'fecha'       => $m->fecha->toISOString(),
                'resumen'     => $m->resumen,
                'tieneJson'   => $m->tieneAdjuntoJson(),
                'tienePdf'    => $m->tieneAdjuntoPdf(),
                'adjuntos'    => $m->adjuntos->map(fn (AdjuntoDTO $a) => [
                    'nombreArchivo' => $a->nombreArchivo,
                    'tipoMime' => $a->tipoMime,
                    'tamaño'   => $a->tamaño,
                    'esJson'   => $a->esJson(),
                    'esPdf'    => $a->esPdf(),
                ])->all(),
            ])->all();

        } catch (\Throwable $e) {
            session()->flash('error', 'Búsqueda fallida: '.$e->getMessage());
        }
    }

    public function abrirMensaje(string $messageId): void
    {
        if ($this->idMensajeAbierto === $messageId) {
            $this->idMensajeAbierto = null;
            $this->adjuntosAbiertos = [];
            return;
        }

        $this->idMensajeAbierto   = $messageId;
        $this->adjuntosAbiertos   = [];
        $this->errorAdjunto       = null;
        $this->cargandoAdjuntos   = true;

        try {
            $provider    = FabricaProveedorCorreo::crear($this->proveedorSeleccionado);
            $attachments = $provider->obtenerAdjuntos($messageId);

            $this->adjuntosAbiertos = $attachments->map(fn (AdjuntoDTO $a) => [
                'nombreArchivo' => $a->nombreArchivo,
                'tipoMime'      => $a->tipoMime,
                'tamaño'        => $a->tamaño,
                'esJson'        => $a->esJson(),
                'esPdf'         => $a->esPdf(),
                'contenidoJson' => $a->esJson() ? $a->contenidoJsonDecodificado() : null,
            ])->all();

        } catch (\Throwable $e) {
            $this->errorAdjunto = 'No se pudieron cargar los adjuntos: '.$e->getMessage();
        } finally {
            $this->cargandoAdjuntos = false;
        }
    }

    public function cerrarMensaje(): void
    {
        $this->idMensajeAbierto = null;
        $this->adjuntosAbiertos = [];
        $this->errorAdjunto     = null;
    }

    public function render()
    {
        return view('livewire.mail-search.index')
            ->layout('layouts.app');
    }
}
