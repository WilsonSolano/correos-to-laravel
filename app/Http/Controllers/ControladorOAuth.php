<?php

namespace App\Http\Controllers;

use App\Services\Mail\FabricaProveedorCorreo;
use App\Models\OAuthToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ControladorOAuth extends Controller
{
    public function redirigir(string $provider): RedirectResponse
    {
        $mailProvider = FabricaProveedorCorreo::crear($provider);

        return redirect()->away($mailProvider->obtenerUrlAuth());
    }

    public function retorno(Request $request, string $provider): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('correo.buscar')
                ->with('error', 'Autorización OAuth denegada: '.$request->input('error'));
        }

        $code = $request->input('code');

        if (! $code) {
            return redirect()->route('correo.buscar')
                ->with('error', 'No se recibió código de autorización de '.$provider.'.');
        }

        try {
            $mailProvider = FabricaProveedorCorreo::crear($provider);
            $mailProvider->manejarCallback($code);
        } catch (\Throwable $e) {
            return redirect()->route('correo.buscar')
                ->with('error', 'No se pudo conectar con '.$provider.': '.$e->getMessage());
        }

        return redirect()->route('correo.buscar')
            ->with('success', ucfirst($provider).' conectado exitosamente.');
    }

    public function desconectar(string $provider): RedirectResponse
    {
        OAuthToken::where('provider', $provider)->delete();

        return redirect()->route('correo.buscar')
            ->with('success', ucfirst($provider).' desconectado.');
    }
}
