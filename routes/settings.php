<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('configuracion', 'configuracion/perfil');

    Route::livewire('configuracion/perfil', 'pages::settings.profile')->name('perfil.editar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('configuracion/apariencia', 'pages::settings.appearance')->name('apariencia.editar');

    Route::livewire('configuracion/seguridad', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('seguridad.editar');
});
