<div class="max-w-3xl mx-auto px-4 py-10 space-y-3">

    {{-- ── Encabezado ──────────────────────────────────────────── --}}
    <div class="pb-4">
        <flux:heading size="xl" class="tracking-tight">Extractor de correos</flux:heading>
        <flux:subheading>Busca órdenes de compra y extrae datos JSON de tus adjuntos.</flux:subheading>
    </div>

    {{-- ── Flash messages ──────────────────────────────────────── --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" heading="{{ session('error') }}" />
    @endif

    {{-- ═══════════════════════════════════════
         PASO 1 – Proveedor
    ════════════════════════════════════════ --}}
    <flux:card>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:badge color="zinc" size="sm">1</flux:badge>
                    <flux:heading level="3" size="base">Proveedor de correo</flux:heading>
                </div>

                @if ($this->estaConectado)
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5 text-green-500 dark:text-green-400">
                            <flux:icon.check-circle variant="solid" class="size-4" />
                            <flux:text size="sm" class="font-medium">Conectado</flux:text>
                        </div>
                        <flux:separator vertical />
                        <a href="{{ $this->urlDesconectar }}"
                           onclick="return confirm('¿Desconectar {{ ucfirst($proveedorSeleccionado) }}?')"
                           class="text-xs text-zinc-400 hover:text-red-500 transition-colors">
                            Desconectar
                        </a>
                    </div>
                @else
                    <flux:button variant="primary" size="sm" href="{{ $this->urlConectar }}" icon="arrow-right-circle">
                        Conectar {{ ucfirst($proveedorSeleccionado) }}
                    </flux:button>
                @endif
            </div>

            <flux:select wire:model.live="proveedorSeleccionado">
                @foreach ($this->proveedores as $slug => $label)
                    <flux:select.option value="{{ $slug }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if (!$this->estaConectado)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Sin conexión</flux:callout.heading>
                    <flux:callout.text>
                        Conecta tu cuenta para buscar correos. Solo pedimos acceso de lectura — nunca enviamos ni modificamos nada.
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </flux:card>

    {{-- ═══════════════════════════════════════
         PASO 2 – Filtros
    ════════════════════════════════════════ --}}
    <flux:card class="{{ !$this->estaConectado ? 'opacity-40 pointer-events-none select-none' : '' }}">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <flux:badge color="zinc" size="sm">2</flux:badge>
                <flux:heading level="3" size="base">Filtros de búsqueda</flux:heading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input
                    wire:model="entradaPalabrasClave"
                    label="Palabras clave"
                    placeholder="factura, pedido, compra…"
                    description="Separadas por comas. Vacío = todos los adjuntos."
                />
                <flux:input
                    wire:model="correoRemitente"
                    label="Remitente"
                    type="email"
                    placeholder="proveedor@ejemplo.com"
                    description="Vacío = cualquier remitente."
                />
            </div>

            {{-- Número + Buscar en la misma línea base --}}
            <div class="flex justify-between items-end w-full">
                <flux:input
                    wire:model="resultadosMaximos"
                    label="Máx. resultados"
                    type="number"
                    min="1"
                    max="100"
                    class="w-28"
                />

                <div class="self-end">
                    <flux:button
                        wire:click="buscar"
                        variant="primary"
                        icon="magnifying-glass"
                    >
                        Buscar
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:card>

    {{-- ═══════════════════════════════════════
         PASO 3 – Resultados
    ════════════════════════════════════════ --}}
    @if ($buscado)
        <div class="space-y-2 pt-2">
            <div class="flex items-center justify-between px-1">
                <div class="flex items-center gap-3">
                    <flux:badge color="zinc" size="sm">3</flux:badge>
                    <flux:heading level="3" size="base">Resultados</flux:heading>
                </div>
                <flux:badge color="zinc">{{ count($mensajes) }} {{ Str::plural('correo', count($mensajes)) }}</flux:badge>
            </div>

            @forelse ($mensajes as $msg)
                <div class="rounded-xl border border-zinc-700 overflow-hidden bg-zinc-900">

                    {{-- ── Fila del correo ────────────────────────────── --}}
                    <button
                        wire:click="abrirMensaje('{{ $msg['id'] }}')"
                        class="w-full text-left px-5 py-4
                               hover:bg-zinc-800 transition-colors duration-150
                               {{ $idMensajeAbierto === $msg['id'] ? 'bg-zinc-800' : '' }}"
                    >
                        <div class="flex items-start gap-4">
                            <flux:avatar name="{{ $msg['nombreRemitente'] }}" size="sm" class="mt-0.5 shrink-0" />

                            <div class="flex-1 min-w-0 space-y-0.5">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-sm font-semibold text-zinc-100 truncate">{{ $msg['nombreRemitente'] }}</span>
                                    <flux:text size="xs" class="text-zinc-500 shrink-0 tabular-nums">
                                        {{ \Carbon\Carbon::parse($msg['fecha'])->format('d M, H:i') }}
                                    </flux:text>
                                </div>
                                <flux:text size="sm" class="truncate font-medium text-zinc-300">{{ $msg['asunto'] }}</flux:text>
                                <flux:text size="xs" class="truncate text-zinc-500">{{ $msg['resumen'] }}</flux:text>

                                {{-- Badges adjuntos: solo extensión --}}
                                @if (count($msg['adjuntos']))
                                    <div class="flex gap-1.5 mt-2.5 flex-wrap">
                                        @foreach ($msg['adjuntos'] as $att)
                                            @php $ext = strtoupper(pathinfo($att['nombreArchivo'], PATHINFO_EXTENSION)) ?: '—'; @endphp
                                            @if ($att['esJson'])
                                                <flux:badge color="blue" size="sm" icon="code-bracket">{{ $ext }}</flux:badge>
                                            @elseif ($att['esPdf'])
                                                <flux:badge color="red" size="sm" icon="document">{{ $ext }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm" icon="paper-clip">{{ $ext }}</flux:badge>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Spinner / chevron --}}
                            <div class="shrink-0 mt-1">
                                <span wire:loading wire:target="abrirMensaje('{{ $msg['id'] }}')">
                                    <flux:icon.loading class="size-4 text-zinc-400 animate-spin" />
                                </span>
                                <span wire:loading.remove wire:target="abrirMensaje('{{ $msg['id'] }}')">
                                    <flux:icon.chevron-down
                                        class="size-4 text-zinc-500 transition-transform duration-200
                                               {{ $idMensajeAbierto === $msg['id'] ? 'rotate-180' : '' }}"
                                    />
                                </span>
                            </div>
                        </div>
                    </button>

                    {{-- ── Panel expandido ────────────────────────────── --}}
                    @if ($idMensajeAbierto === $msg['id'])
                        <div class="border-t border-zinc-700 px-5 py-4 space-y-4 bg-zinc-900">

                            <flux:text size="xs" class="text-zinc-500">
                                <span class="font-semibold text-zinc-400">De:</span>
                                {{ $msg['remitente'] }}
                            </flux:text>

                            @if ($cargandoAdjuntos)
                                <div class="flex items-center gap-2 text-zinc-500 py-3">
                                    <flux:icon.loading class="size-4 animate-spin" />
                                    <flux:text size="sm">Cargando adjuntos…</flux:text>
                                </div>

                            @elseif ($errorAdjunto)
                                <flux:callout variant="danger" icon="x-circle" heading="{{ $errorAdjunto }}" />

                            @elseif (empty($adjuntosAbiertos))
                                <flux:text size="sm" class="text-zinc-500">Sin adjuntos descargables.</flux:text>

                            @else
                                <div class="space-y-3">
                                    @foreach ($adjuntosAbiertos as $att)
                                        <div class="rounded-lg border border-zinc-700 overflow-hidden bg-zinc-800">

                                            {{-- Cabecera adjunto --}}
                                            <div class="flex items-center gap-3 px-4 py-3">
                                                @if ($att['esJson'])
                                                    <flux:icon.code-bracket class="size-4 text-blue-400 shrink-0" />
                                                @elseif ($att['esPdf'])
                                                    <flux:icon.document class="size-4 text-red-400 shrink-0" />
                                                @else
                                                    <flux:icon.paper-clip class="size-4 text-zinc-400 shrink-0" />
                                                @endif

                                                <div class="flex-1 min-w-0">
                                                    <flux:text size="sm" class="font-medium text-zinc-200 truncate">{{ $att['nombreArchivo'] }}</flux:text>
                                                    <flux:text size="xs" class="text-zinc-500">
                                                        {{ $att['tipoMime'] }} · {{ number_format($att['tamaño'] / 1024, 1) }} KB
                                                    </flux:text>
                                                </div>

                                                @if ($att['esJson'])
                                                    <flux:badge color="blue" size="sm">JSON</flux:badge>
                                                @elseif ($att['esPdf'])
                                                    <flux:badge color="red" size="sm">PDF</flux:badge>
                                                @endif
                                            </div>

                                            {{-- Visor JSON --}}
                                            @if ($att['esJson'] && $att['contenidoJson'] !== null)
                                                <div class="border-t border-zinc-700">
                                                    <pre class="overflow-auto px-4 py-4 text-xs font-mono leading-relaxed text-zinc-300 bg-zinc-950 max-h-80">{{ json_encode($att['contenidoJson'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            @elseif ($att['esJson'])
                                                <div class="border-t border-zinc-700 px-4 py-3">
                                                    <flux:text size="sm" class="text-zinc-500">No se pudo decodificar el JSON.</flux:text>
                                                </div>
                                            @endif

                                            {{-- Nota PDF --}}
                                            @if ($att['esPdf'])
                                                <div class="border-t border-zinc-700 px-4 py-2.5">
                                                    <flux:text size="xs" class="text-zinc-500">
                                                        La descarga de PDFs puede habilitarse mediante un endpoint dedicado.
                                                    </flux:text>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <flux:button wire:click="cerrarMensaje" variant="ghost" size="sm" icon="x-mark">
                                Cerrar
                            </flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-zinc-700 bg-zinc-900 py-14 text-center space-y-2">
                    <flux:icon.inbox class="size-10 mx-auto text-zinc-600" />
                    <flux:heading level="3" size="base">Sin resultados</flux:heading>
                    <flux:subheading>Ajusta las palabras clave o el filtro de remitente.</flux:subheading>
                </div>
            @endforelse
        </div>
    @endif

</div>
