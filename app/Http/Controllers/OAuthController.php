<?php

namespace App\Http\Controllers;

use App\Services\Mail\MailProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OAuthController extends Controller
{
    /**
     * Redirect the user to the provider's OAuth consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $mailProvider = MailProviderFactory::make($provider);

        return redirect()->away($mailProvider->getAuthUrl());
    }

    /**
     * Handle the OAuth callback from the provider.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('mail.search')
                ->with('error', 'OAuth authorization was denied: '.$request->input('error'));
        }

        $code = $request->input('code');

        if (! $code) {
            return redirect()->route('mail.search')
                ->with('error', 'No authorization code received from '.$provider.'.');
        }

        try {
            $mailProvider = MailProviderFactory::make($provider);
            $mailProvider->handleCallback($code);
        } catch (\Throwable $e) {
            return redirect()->route('mail.search')
                ->with('error', 'Could not connect to '.$provider.': '.$e->getMessage());
        }

        return redirect()->route('mail.search')
            ->with('success', ucfirst($provider).' connected successfully!');
    }

    /**
     * Revoke / delete the stored token for a provider.
     */
    public function disconnect(string $provider): RedirectResponse
    {
        \App\Models\OAuthToken::where('provider', $provider)->delete();

        return redirect()->route('mail.search')
            ->with('success', ucfirst($provider).' disconnected.');
    }
}
