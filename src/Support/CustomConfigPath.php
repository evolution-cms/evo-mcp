<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

final class CustomConfigPath
{
    /**
     * Absolute path of a file under the site's core/custom/config directory.
     */
    public static function to(string $path): string
    {
        $path = ltrim($path, '/\\');

        if (function_exists('config_path')) {
            $candidate = config_path($path, true);
            if (is_string($candidate) && $candidate !== '') {
                $normalized = str_replace('\\', '/', $candidate);
                if (str_contains($normalized, '/custom/config/')) {
                    return $candidate;
                }
            }
        }

        if (defined('EVO_CORE_PATH')) {
            return rtrim((string)EVO_CORE_PATH, '/\\') . '/custom/config/' . $path;
        }

        return base_path('core/custom/config/' . $path);
    }
}
