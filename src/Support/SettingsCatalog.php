<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

/**
 * Boolean settings the manager settings page exposes as checkboxes.
 * Labels and hints live in lang/<locale>/global.php under `settings.fields.<key>`.
 */
final class SettingsCatalog
{
    /**
     * @var array<string, string> dot path in cms.settings.eMCP => lang key
     */
    private const FIELDS = [
        'enable' => 'enable',
        'mode.internal' => 'mode_internal',
        'mode.api' => 'mode_api',
        'auth.require_scopes' => 'require_scopes',
        'security.enable_write_tools' => 'enable_write_tools',
        'tokens.self_service' => 'self_service',
        'rate_limit.enabled' => 'rate_limit',
        'logging.audit_enabled' => 'audit',
        'stream.enabled' => 'stream',
    ];

    /**
     * @return array<string, string>
     */
    public static function fields(): array
    {
        return self::FIELDS;
    }

    /**
     * Read checkbox input: every catalog setting is set, unchecked boxes mean false.
     *
     * @param array<string, mixed> $input field lang key => submitted value
     * @return array<string, bool>
     */
    public static function valuesFromInput(array $input): array
    {
        $values = [];
        foreach (self::FIELDS as $path => $key) {
            $values[$path] = in_array($input[$key] ?? null, ['1', 1, true, 'on'], true);
        }

        return $values;
    }
}
