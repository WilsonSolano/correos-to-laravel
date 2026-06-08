<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    {{-- ── Page header ──────────────────────────────── --}}
    <div>
        <flux:heading size="xl">Mail Purchase Extractor</flux:heading>
        <flux:subheading>Connect your email, search for purchase orders and extract JSON data from attachments.</flux:subheading>
    </div>

    {{-- ── Flash messages ───────────────────────────── --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" heading="{{ session('error') }}" />
    @endif

    {{-- ═══════════════════════════════════════════════
         STEP 1 – Provider selector + connection status
    ═══════════════════════════════════════════════════ --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 space-y-4">
        <div class="flex items-center gap-2">
            <div class="flex items-center justify-center w-6 h-6 rounded-full bg-accent-content text-accent-foreground text-xs font-bold">1</div>
            <flux:heading level="3" size="lg">Choose email provider</flux:heading>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="flex-1">
                <flux:select wire:model.live="selectedProvider" label="Provider">
                    @foreach ($this->providers as $slug => $label)
                        <flux:select.option value="{{ $slug }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-3">
                @if ($this->isConnected)
                    <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <flux:icon.check-circle class="size-5" />
                        <flux:text class="font-medium">Connected</flux:text>
                    </div>
                    <a href="{{ $this->disconnectUrl }}"
                       onclick="return confirm('Disconnect {{ ucfirst($selectedProvider) }}?')"
                       class="text-sm text-red-500 hover:text-red-600 underline underline-offset-2">
                        Disconnect
                    </a>
                @else
                    <div class="flex items-center gap-2 text-zinc-400">
                        <flux:icon.x-circle class="size-5" />
                        <flux:text>Not connected</flux:text>
                    </div>
                    <flux:button
                        variant="primary"
                        size="sm"
                        href="{{ $this->connectUrl }}"
                        icon="arrow-right-circle"
                    >
                        Connect {{ ucfirst($selectedProvider) }}
                    </flux:button>
                @endif
            </div>
        </div>

        @if (!$this->isConnected)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Gmail not connected</flux:callout.heading>
                <flux:callout.text>
                    Click <strong>Connect Gmail</strong> above. You will be redirected to Google to grant read-only access to your inbox.
                    We store your token encrypted and never send or modify emails.
                </flux:callout.text>
            </flux:callout>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════
         STEP 2 – Search filters
    ═══════════════════════════════════════════════════ --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 space-y-4 @if(!$this->isConnected) opacity-50 pointer-events-none @endif">
        <div class="flex items-center gap-2">
            <div class="flex items-center justify-center w-6 h-6 rounded-full bg-accent-content text-accent-foreground text-xs font-bold">2</div>
            <flux:heading level="3" size="lg">Search filters</flux:heading>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:input
                wire:model="keywordsInput"
                label="Keywords"
                placeholder="invoice, order, purchase (comma-separated)"
                description="Leave empty to match any email with attachments."
            />
            <flux:input
                wire:model="fromEmail"
                label="Sender email"
                type="email"
                placeholder="supplier@example.com"
                description="Leave empty to search all senders."
            />
        </div>

        <div class="flex items-end gap-4">
            <div class="w-40">
                <flux:input
                    wire:model="maxResults"
                    label="Max results"
                    type="number"
                    min="1"
                    max="100"
                />
            </div>
            <flux:button
                wire:click="search"
                wire:loading.attr="disabled"
                variant="primary"
                icon="magnifying-glass"
                :disabled="!$this->isConnected"
            >
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search">Searching…</span>
            </flux:button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         STEP 3 – Results list
    ═══════════════════════════════════════════════════ --}}
    @if ($searched)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-accent-content text-accent-foreground text-xs font-bold">3</div>
                    <flux:heading level="3" size="lg">Results</flux:heading>
                </div>
                <flux:badge>{{ count($messages) }} {{ Str::plural('email', count($messages)) }} found</flux:badge>
            </div>

            @forelse ($messages as $msg)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
                    {{-- Message row --}}
                    <button
                        wire:click="openMessage('{{ $msg['id'] }}')"
                        class="w-full text-left p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition-colors flex items-start gap-4"
                    >
                        {{-- Sender avatar --}}
                        <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold text-sm uppercase">
                            {{ mb_substr($msg['fromName'], 0, 1) }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="font-medium truncate">{{ $msg['fromName'] }}</span>
                                <span class="text-xs text-zinc-400 shrink-0">
                                    {{ \Carbon\Carbon::parse($msg['date'])->format('d M Y, H:i') }}
                                </span>
                            </div>
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 truncate">{{ $msg['subject'] }}</div>
                            <div class="text-xs text-zinc-400 truncate mt-0.5">{{ $msg['snippet'] }}</div>

                            {{-- Attachment badges --}}
                            <div class="flex gap-2 mt-2 flex-wrap">
                                @foreach ($msg['attachments'] as $att)
                                    @if ($att['isJson'])
                                        <flux:badge color="blue" size="sm" icon="code-bracket">
                                            {{ $att['filename'] }}
                                        </flux:badge>
                                    @elseif ($att['isPdf'])
                                        <flux:badge color="red" size="sm" icon="document">
                                            {{ $att['filename'] }}
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm">{{ $att['filename'] }}</flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Expand chevron --}}
                        <flux:icon.chevron-down
                            class="shrink-0 mt-1 transition-transform {{ $openMessageId === $msg['id'] ? 'rotate-180' : '' }}"
                        />
                    </button>

                    {{-- ── Expanded detail panel ──────────────────── --}}
                    @if ($openMessageId === $msg['id'])
                        <div class="border-t border-zinc-200 dark:border-zinc-700 p-4 space-y-4 bg-zinc-50 dark:bg-zinc-800/40">

                            {{-- Sender detail --}}
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                <strong class="text-zinc-700 dark:text-zinc-200">From:</strong> {{ $msg['from'] }}
                            </div>

                            @if ($loadingAttachments)
                                <div class="flex items-center gap-2 text-zinc-400 py-4">
                                    <flux:icon.loading class="size-5 animate-spin" />
                                    <span class="text-sm">Loading attachments…</span>
                                </div>
                            @elseif ($attachmentError)
                                <flux:callout variant="danger" icon="x-circle" heading="{{ $attachmentError }}" />
                            @elseif (empty($openAttachments))
                                <flux:text>No downloadable attachments found.</flux:text>
                            @else
                                @foreach ($openAttachments as $att)
                                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-600 overflow-hidden">
                                        {{-- Attachment header --}}
                                        <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-zinc-900">
                                            @if ($att['isJson'])
                                                <flux:icon.code-bracket class="size-5 text-blue-500" />
                                            @elseif ($att['isPdf'])
                                                <flux:icon.document class="size-5 text-red-500" />
                                            @else
                                                <flux:icon.paper-clip class="size-5 text-zinc-400" />
                                            @endif

                                            <div class="flex-1 min-w-0">
                                                <span class="font-medium text-sm truncate block">{{ $att['filename'] }}</span>
                                                <span class="text-xs text-zinc-400">{{ $att['mimeType'] }} · {{ number_format($att['size'] / 1024, 1) }} KB</span>
                                            </div>
                                        </div>

                                        {{-- JSON viewer --}}
                                        @if ($att['isJson'] && $att['jsonContent'] !== null)
                                            <div class="border-t border-zinc-200 dark:border-zinc-600">
                                                <div class="px-4 py-2 bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-600 flex items-center justify-between">
                                                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">JSON Content</span>
                                                    <flux:badge color="blue" size="sm">Raw</flux:badge>
                                                </div>
                                                <div class="relative">
                                                    <pre class="overflow-auto p-4 text-xs font-mono text-zinc-800 dark:text-zinc-200 bg-zinc-50 dark:bg-zinc-900 max-h-96 leading-relaxed">{{ json_encode($att['jsonContent'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            </div>
                                        @elseif ($att['isJson'] && $att['jsonContent'] === null)
                                            <div class="border-t border-zinc-200 dark:border-zinc-600 px-4 py-3">
                                                <flux:text class="text-sm text-zinc-400">Could not decode JSON content.</flux:text>
                                            </div>
                                        @endif

                                        {{-- PDF note --}}
                                        @if ($att['isPdf'])
                                            <div class="border-t border-zinc-200 dark:border-zinc-600 px-4 py-3 bg-red-50 dark:bg-red-900/20">
                                                <flux:text class="text-sm text-red-600 dark:text-red-300">
                                                    PDF attachment detected. Download functionality can be added via a dedicated endpoint.
                                                </flux:text>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif

                            <flux:button wire:click="closeMessage" variant="ghost" size="sm" icon="x-mark">
                                Close
                            </flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-12 text-center">
                    <flux:icon.inbox class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                    <flux:heading level="3">No emails found</flux:heading>
                    <flux:subheading>Try adjusting your keywords or sender filter.</flux:subheading>
                </div>
            @endforelse
        </div>
    @endif

</div>
