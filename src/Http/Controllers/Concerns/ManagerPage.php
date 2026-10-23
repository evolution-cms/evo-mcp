<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Http\Controllers\Concerns;

use Illuminate\Http\Response;

/**
 * Shared bits of the eMCP manager pages. Classes using it define FLASH_KEY.
 */
trait ManagerPage
{
    /**
     * Evo has no abort() helper: refuse with a plain 403 response, like EnsureMcpPermission.
     */
    private function forbidden(): Response
    {
        return new Response((string)__('eMCP::global.errors.forbidden'), 403);
    }

    /**
     * One-shot messages ride on Evo's native session: the manager's Laravel session store is not
     * reliably shared across a redirect, the PHP session always is.
     */
    private function flash(string $key, string $value): void
    {
        $_SESSION[static::FLASH_KEY][$key] = $value;
    }

    private function pullFlash(string $key): string
    {
        $value = (string)($_SESSION[static::FLASH_KEY][$key] ?? '');
        unset($_SESSION[static::FLASH_KEY][$key]);

        return $value;
    }

    /**
     * Manager routes skip ManagerTheme, which is what sets the locale on regular manager pages.
     */
    private function applyManagerLocale(): void
    {
        $lang = (string)evo()->getConfig('manager_language');
        if ($lang !== '' && is_dir(dirname(__DIR__, 4) . '/lang/' . basename($lang))) {
            app()->setLocale($lang);
        }
    }

    private function isDarkTheme(): bool
    {
        $modes = ['', 'lightness', 'light', 'dark', 'darkness'];
        $index = isset($_COOKIE['MODX_themeMode']) ? (int)$_COOKIE['MODX_themeMode'] : (int)evo()->getConfig('manager_theme_mode');

        return in_array($modes[$index] ?? '', ['dark', 'darkness'], true);
    }

    private function logManagerAction(string $message): void
    {
        try {
            evo()->logEvent(0, 1, $message, 'eMCP');
        } catch (\Throwable) {
            // The event log must never break the page.
        }
    }
}
