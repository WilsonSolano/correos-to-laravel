<?php

namespace App\Services\Mail;

use App\Contracts\MailProviderInterface;
use InvalidArgumentException;

/**
 * Resolves a registered mail provider by its slug.
 *
 * Adding a new provider:
 *   1. Implement MailProviderInterface.
 *   2. Add its slug → FQCN entry to the $providers array below.
 *   3. Register its OAuth credentials in config/services.php + .env.
 *   That's it – no other files need changing.
 */
class MailProviderFactory
{
    /**
     * Map of provider slug → concrete class.
     * Add new providers here.
     */
    private static array $providers = [
        'gmail'   => GmailProvider::class,
        // 'outlook' => OutlookProvider::class,
    ];

    public static function make(string $provider): MailProviderInterface
    {
        if (! array_key_exists($provider, self::$providers)) {
            throw new InvalidArgumentException(
                "Unknown mail provider [{$provider}]. ".
                'Registered providers: '.implode(', ', array_keys(self::$providers)).'.'
            );
        }

        return app(self::$providers[$provider]);
    }

    /**
     * Return all registered provider instances (used to build the selector UI).
     *
     * @return array<string, MailProviderInterface>
     */
    public static function all(): array
    {
        return collect(self::$providers)
            ->map(fn ($class) => app($class))
            ->all();
    }

    /**
     * Return a list of [slug => label] suitable for a select element.
     */
    public static function options(): array
    {
        return collect(self::all())
            ->mapWithKeys(fn (MailProviderInterface $p) => [$p->getName() => $p->getLabel()])
            ->all();
    }
}
