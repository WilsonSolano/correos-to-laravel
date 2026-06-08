<?php

namespace App\Livewire;

use App\DTOs\AttachmentDTO;
use App\DTOs\MailMessageDTO;
use App\Services\Mail\MailProviderFactory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mail Extractor')]
#[Layout('layouts.app')]
class MailSearch extends Component
{
    // ── Form state ────────────────────────────────
    public string $selectedProvider = 'gmail';
    public string $keywordsInput    = '';   // comma-separated
    public string $fromEmail        = '';
    public int    $maxResults       = 20;

    // ── UI state ──────────────────────────────────
    public bool   $searched         = false;
    public bool   $loading          = false;

    // ── Results ───────────────────────────────────
    /** @var array<int, array> Serialized MailMessageDTO list */
    public array $messages = [];

    // ── Detail view ───────────────────────────────
    public ?string $openMessageId   = null;
    /** @var array<int, array> Serialized AttachmentDTO list for the open message */
    public array $openAttachments   = [];
    public bool  $loadingAttachments = false;
    public ?string $attachmentError  = null;

    // ─────────────────────────────────────────────
    // Computed
    // ─────────────────────────────────────────────

    #[Computed]
    public function providers(): array
    {
        return MailProviderFactory::options(); // ['gmail' => 'Gmail', ...]
    }

    #[Computed]
    public function isConnected(): bool
    {
        try {
            return MailProviderFactory::make($this->selectedProvider)->isConnected();
        } catch (\Throwable) {
            return false;
        }
    }

    #[Computed]
    public function connectUrl(): string
    {
        return route('oauth.redirect', $this->selectedProvider);
    }

    #[Computed]
    public function disconnectUrl(): string
    {
        return route('oauth.disconnect', $this->selectedProvider);
    }

    // ─────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────

    public function search(): void
    {
        $this->validate([
            'selectedProvider' => ['required', 'string'],
            'keywordsInput'    => ['nullable', 'string', 'max:500'],
            'fromEmail'        => ['nullable', 'email'],
            'maxResults'       => ['integer', 'min:1', 'max:100'],
        ]);

        $this->messages       = [];
        $this->openMessageId  = null;
        $this->openAttachments = [];
        $this->searched       = true;

        $keywords = collect(explode(',', $this->keywordsInput))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->values()
            ->all();

        $filters = [
            'keywords'    => $keywords,
            'from'        => $this->fromEmail,
            'max_results' => $this->maxResults,
        ];

        try {
            $provider = MailProviderFactory::make($this->selectedProvider);

            /** @var Collection<int, MailMessageDTO> $results */
            $results = $provider->search($filters);

            $this->messages = $results->map(fn (MailMessageDTO $m) => [
                'id'          => $m->id,
                'from'        => $m->from,
                'fromName'    => $m->fromName,
                'subject'     => $m->subject,
                'date'        => $m->date->toISOString(),
                'snippet'     => $m->snippet,
                'hasJson'     => $m->hasJsonAttachment(),
                'hasPdf'      => $m->hasPdfAttachment(),
                'attachments' => $m->attachments->map(fn (AttachmentDTO $a) => [
                    'filename' => $a->filename,
                    'mimeType' => $a->mimeType,
                    'size'     => $a->size,
                    'isJson'   => $a->isJson(),
                    'isPdf'    => $a->isPdf(),
                ])->all(),
            ])->all();

        } catch (\Throwable $e) {
            session()->flash('error', 'Search failed: '.$e->getMessage());
        }
    }

    /**
     * Open a message and download its attachment content.
     */
    public function openMessage(string $messageId): void
    {
        if ($this->openMessageId === $messageId) {
            // Toggle: close if already open
            $this->openMessageId   = null;
            $this->openAttachments = [];
            return;
        }

        $this->openMessageId      = $messageId;
        $this->openAttachments    = [];
        $this->attachmentError    = null;
        $this->loadingAttachments = true;

        try {
            $provider    = MailProviderFactory::make($this->selectedProvider);
            $attachments = $provider->getAttachments($messageId);

            $this->openAttachments = $attachments->map(fn (AttachmentDTO $a) => [
                'filename'    => $a->filename,
                'mimeType'    => $a->mimeType,
                'size'        => $a->size,
                'isJson'      => $a->isJson(),
                'isPdf'       => $a->isPdf(),
                'jsonContent' => $a->isJson() ? $a->decodedJsonContent() : null,
            ])->all();

        } catch (\Throwable $e) {
            $this->attachmentError = 'Could not load attachments: '.$e->getMessage();
        } finally {
            $this->loadingAttachments = false;
        }
    }

    public function closeMessage(): void
    {
        $this->openMessageId   = null;
        $this->openAttachments = [];
        $this->attachmentError = null;
    }

    // ─────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────

    public function render()
    {
        return view('livewire.mail-search.index')
            ->layout('layouts.app');
    }
}
