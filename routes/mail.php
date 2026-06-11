<?php

use App\Http\Controllers\ControladorOAuth;
use App\Livewire\BusquedaCorreo;
use Illuminate\Support\Facades\Route;

Route::get('/correo', BusquedaCorreo::class)->name('correo.buscar');

Route::get('/oauth/{provider}/redirect', [ControladorOAuth::class, 'redirigir'])
    ->name('oauth.redirigir');

Route::get('/oauth/{provider}/callback', [ControladorOAuth::class, 'retorno'])
    ->name('oauth.retorno');

Route::get('/oauth/{provider}/disconnect', [ControladorOAuth::class, 'desconectar'])
    ->name('oauth.desconectar');
