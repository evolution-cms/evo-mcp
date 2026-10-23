<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Http\Controllers;

use EvolutionCMS\eMCP\Http\Controllers\Concerns\ManagerPage;
use EvolutionCMS\eMCP\Support\ManagerUrl;
use EvolutionCMS\eMCP\Support\SettingsCatalog;
use EvolutionCMS\eMCP\Support\SettingsFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "MCP settings" page: site-wide eMCP switches, saved to core/custom/config/cms/settings/eMCP.php.
 *
 * Needs Evo's `settings` permission on top of the eMCP access permission: these switches
 * (write tools above all) change what every token on the site can do.
 */
class SettingsPageController
{
    use ManagerPage;

    private const FLASH_KEY = 'emcp_settings_flash';

    public function index(Request $request)
    {
        if (!$this->allowed()) {
            return $this->forbidden();
        }
        $this->applyManagerLocale();

        $fields = [];
        foreach (SettingsCatalog::fields() as $path => $key) {
            $fields[] = [
                'key' => $key,
                'checked' => (bool)config('cms.settings.eMCP.' . $path, false),
                'label' => __('eMCP::global.settings.fields.' . $key . '.label'),
                'hint' => __('eMCP::global.settings.fields.' . $key . '.hint'),
            ];
        }

        return view('eMCP::manager.settings', [
            'fields' => $fields,
            'filePath' => $this->displayPath(SettingsFile::sitePath()),
            'saveUrl' => ManagerUrl::to('emcp/settings'),
            'tokensUrl' => ManagerUrl::to('emcp/tokens'),
            'message' => $this->pullFlash('message'),
            'error' => $this->pullFlash('error'),
            'darkTheme' => $this->isDarkTheme(),
        ]);
    }

    public function update(Request $request): Response
    {
        if (!$this->allowed()) {
            return $this->forbidden();
        }
        $this->applyManagerLocale();

        $path = SettingsFile::sitePath();
        $values = SettingsCatalog::valuesFromInput((array)$request->input('settings', []));

        try {
            SettingsFile::ensurePublished(SettingsFile::defaultSource(), $path);
            SettingsFile::writeBooleans($path, $values);
            SettingsFile::forgetCachedConfig();
        } catch (\RuntimeException $e) {
            return $this->back('error', __('eMCP::global.settings.error_write', ['path' => $this->displayPath($path)]));
        }

        $changes = [];
        foreach ($values as $setting => $value) {
            $changes[] = $setting . '=' . ($value ? 'true' : 'false');
        }
        $this->logManagerAction('Saved MCP settings: ' . implode(', ', $changes));

        return $this->back('message', __('eMCP::global.settings.saved'));
    }

    private function allowed(): bool
    {
        if (!function_exists('evo') || !evo()->isLoggedIn('mgr')) {
            return false;
        }

        $permission = (string)config('cms.settings.eMCP.acl.permission', 'emcp');

        return evo()->hasPermission($permission, 'mgr') && evo()->hasPermission('settings', 'mgr');
    }

    private function displayPath(string $path): string
    {
        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        $pos = strpos($normalized, '/core/custom/');

        return $pos === false ? $normalized : ltrim(substr($normalized, $pos), '/');
    }

    private function back(string $flashKey, string $flashValue): RedirectResponse
    {
        $this->flash($flashKey, $flashValue);

        return redirect()->to(ManagerUrl::to('emcp/settings'));
    }
}
