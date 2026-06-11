<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Configuración de perfil')] class extends Component {
    use ProfileValidationRules;

    public string $nombre = '';
    public string $email = '';

    public function montar(): void
    {
        $this->nombre = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function actualizarInformacionPerfil(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill([
            'name' => $validated['nombre'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Perfil actualizado.'));
    }

    public function reenviarNotificacionVerificacion(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('panel', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function tieneEmailNoVerificado(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function mostrarEliminarUsuario(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Configuración de perfil') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Perfil')" :subheading="__('Actualiza tu nombre y correo electrónico')">
        <form wire:submit="actualizarInformacionPerfil" class="my-6 w-full space-y-6">
            <flux:input wire:model="nombre" :label="__('Nombre')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->tieneEmailNoVerificado)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Tu correo electrónico no está verificado.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="reenviarNotificacionVerificacion">
                                {{ __('Haz clic aquí para reenviar el correo de verificación.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('Se ha enviado un nuevo enlace de verificación a tu correo electrónico.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Guardar') }}
                    </flux:button>
                </div>

            </div>
        </form>

        @if ($this->mostrarEliminarUsuario)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
