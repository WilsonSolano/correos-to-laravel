<?php

namespace App\Services\Mail;

use App\Contracts\ProveedorCorreoInterface;
use InvalidArgumentException;

class FabricaProveedorCorreo
{
    private static array $proveedores = [
        'gmail'   => ProveedorGmail::class,
    ];

    public static function crear(string $provider): ProveedorCorreoInterface
    {
        if (! array_key_exists($provider, self::$proveedores)) {
            throw new InvalidArgumentException(
                "Proveedor de correo desconocido [{$provider}]. ".
                'Proveedores registrados: '.implode(', ', array_keys(self::$proveedores)).'.'
            );
        }

        return app(self::$proveedores[$provider]);
    }

    /**
     * @return array<string, ProveedorCorreoInterface>
     */
    public static function todos(): array
    {
        return collect(self::$proveedores)
            ->map(fn ($class) => app($class))
            ->all();
    }

    public static function opciones(): array
    {
        return collect(self::todos())
            ->mapWithKeys(fn (ProveedorCorreoInterface $p) => [$p->obtenerNombre() => $p->obtenerEtiqueta()])
            ->all();
    }
}
