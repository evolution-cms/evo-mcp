<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use RuntimeException;

/**
 * The site's copy of the eMCP settings (core/custom/config/cms/settings/eMCP.php).
 *
 * Boolean switches are rewritten in place, token by token, so the comments and layout of
 * the published file survive edits made from the manager.
 */
final class SettingsFile
{
    public static function defaultSource(): string
    {
        return dirname(__DIR__, 2) . '/config/eMCPSettings.php';
    }

    public static function sitePath(): string
    {
        return CustomConfigPath::to('cms/settings/eMCP.php');
    }

    /**
     * Copy the package defaults to $target unless the site already has its own file.
     *
     * @return bool true when the file was created
     */
    public static function ensurePublished(string $source, string $target): bool
    {
        if (is_file($target)) {
            return false;
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create directory {$dir}");
        }

        if (!@copy($source, $target)) {
            throw new RuntimeException("Unable to copy {$source} to {$target}");
        }

        return true;
    }

    /**
     * Evo 3.5.9+ caches the whole configuration (core/storage/cache/env.php); drop it so the
     * next request reads the settings file again. Older versions read config on every request.
     */
    public static function forgetCachedConfig(): void
    {
        $loader = 'EvolutionCMS\\Bootstrap\\EnvCacheLoader';
        if (defined('EVO_BASE_PATH') && class_exists($loader) && method_exists($loader, 'invalidate')) {
            $loader::invalidate((string)EVO_BASE_PATH);
        }
    }

    /**
     * Set boolean settings (dot paths, e.g. `security.enable_write_tools`) in the file at $path.
     *
     * @param array<string, bool> $values
     */
    public static function writeBooleans(string $path, array $values): void
    {
        $code = @file_get_contents($path);
        if (!is_string($code)) {
            throw new RuntimeException("Unable to read {$path}");
        }

        $updated = self::applyBooleans($code, $values);
        if ($updated === $code) {
            return;
        }

        // Write next to the target, prove it still parses to an array, then swap it in.
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $updated) === false) {
            throw new RuntimeException("Unable to write {$path}");
        }

        try {
            $parsed = include $tmp;
        } catch (\ParseError $e) {
            @unlink($tmp);
            throw new RuntimeException("Refusing to save {$path}: " . $e->getMessage(), 0, $e);
        }

        if (!is_array($parsed) || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Unable to write {$path}");
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    /**
     * @param array<string, bool> $values
     */
    public static function applyBooleans(string $code, array $values): string
    {
        $tokens = token_get_all($code);
        [$scalars, $arrays] = self::index($tokens);

        /** @var array<int, string> $replace token index => new text */
        $replace = [];
        /** @var array<int, string> $insert token index => text inserted after that token */
        $insert = [];

        foreach ($values as $dotPath => $value) {
            $literal = $value ? 'true' : 'false';

            if (isset($scalars[$dotPath])) {
                $replace[$scalars[$dotPath]] = $literal;
                continue;
            }

            // Missing key: nest it into the closest array the file already has.
            $segments = explode('.', $dotPath);
            $existing = [];
            for ($i = count($segments) - 1; $i >= 0; $i--) {
                $parent = implode('.', array_slice($segments, 0, $i));
                if (isset($arrays[$parent])) {
                    $existing = array_slice($segments, $i);
                    break;
                }
            }

            if ($existing === [] || !isset($arrays[$parent])) {
                throw new RuntimeException("Cannot place setting [{$dotPath}]: the file has no returned array.");
            }

            $depth = $parent === '' ? 1 : substr_count($parent, '.') + 2;
            $insert[$arrays[$parent]] = ($insert[$arrays[$parent]] ?? '')
                . "\n" . str_repeat('    ', $depth) . self::nested($existing, $literal) . ',';
        }

        $out = '';
        foreach ($tokens as $i => $token) {
            $out .= $replace[$i] ?? (is_array($token) ? $token[1] : $token);
            $out .= $insert[$i] ?? '';
        }

        return $out;
    }

    /**
     * Map dot paths to token indexes: boolean values, and the `[` that opens each array.
     *
     * @param array<int, mixed> $tokens
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private static function index(array $tokens): array
    {
        $scalars = [];
        $arrays = [];
        $stack = [];
        $pendingKey = null;
        $inReturn = false;

        foreach ($tokens as $i => $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            if ($id === T_RETURN) {
                $inReturn = true;
                continue;
            }

            if (!$inReturn) {
                continue;
            }

            if ($text === '[') {
                $key = $stack === [] ? '' : $pendingKey;
                $path = $key === null ? null : self::join($stack, $key);
                if ($path !== null) {
                    $arrays[$path] = $i;
                }
                $stack[] = $path;
                $pendingKey = null;
                continue;
            }

            if ($text === ']') {
                array_pop($stack);
                $pendingKey = null;
                continue;
            }

            if ($id === T_CONSTANT_ENCAPSED_STRING && self::nextSignificant($tokens, $i) === T_DOUBLE_ARROW) {
                $pendingKey = substr($text, 1, -1);
                continue;
            }

            if ($id === T_STRING && $pendingKey !== null && in_array(strtolower($text), ['true', 'false'], true)) {
                $path = self::join($stack, $pendingKey);
                if ($path !== null) {
                    $scalars[$path] = $i;
                }
            }

            if ($text === ',') {
                $pendingKey = null;
            }
        }

        return [$scalars, $arrays];
    }

    /**
     * @param array<int, string|null> $stack
     */
    private static function join(array $stack, string $key): ?string
    {
        $parent = end($stack);
        if ($parent === null || $parent === false) {
            return $stack === [] ? $key : null;
        }

        return $parent === '' ? $key : $parent . '.' . $key;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private static function nextSignificant(array $tokens, int $i): int|string|null
    {
        $count = count($tokens);
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /**
     * @param array<int, string> $segments
     */
    private static function nested(array $segments, string $literal): string
    {
        $key = array_shift($segments);
        $value = $segments === [] ? $literal : '[' . self::nested($segments, $literal) . ']';

        return var_export($key, true) . ' => ' . $value;
    }
}
