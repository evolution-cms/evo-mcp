<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

/**
 * Recognises calls of eMCP's own write tools that store backend code, and the permissions that
 * allow it.
 *
 * Follows Evolution CMS core (EvolutionCMS\Support\BackendCodePermissions, TvBindingGuard,
 * Evo 3.5.x) when it is installed, so the bar is the same as in the manager; on older cores the
 * same rules are applied from the copies below.
 */
final class BackendCode
{
    /** Element types whose code field is PHP the server runs. */
    private const CODE_ELEMENT_TYPES = ['snippet', 'plugin'];

    /** TV definition columns a binding can sit in. */
    private const TV_DEFINITION_FIELDS = ['elements', 'default_text', 'display_params'];

    /**
     * Manager permissions that already grant backend code: holding any one is enough.
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        $core = 'EvolutionCMS\\Support\\BackendCodePermissions';

        return class_exists($core) ? $core::PERMISSIONS : ['save_snippet', 'save_plugin', 'save_module'];
    }

    /**
     * Whether a call of a built-in tool stores backend code, judged from its arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function inArguments(string $toolName, array $arguments): bool
    {
        $fields = is_array($arguments['fields'] ?? null) ? $arguments['fields'] : [];

        switch (trim($toolName)) {
            case 'evo.write.elements.save':
                $type = strtolower(trim((string)($arguments['type'] ?? '')));
                if (in_array($type, self::CODE_ELEMENT_TYPES, true)) {
                    return true;
                }
                if ($type !== 'tv') {
                    return false;
                }
                foreach (self::TV_DEFINITION_FIELDS as $column) {
                    if (is_scalar($fields[$column] ?? null) && self::definitionRunsCode((string)$fields[$column], $column)) {
                        return true;
                    }
                }

                return false;

            case 'evo.write.content.update':
            case 'evo.write.content.create':
                $tvs = is_array($arguments['tvs'] ?? null) ? $arguments['tvs'] : [];
                foreach (array_merge(array_values($fields), array_values($tvs)) as $value) {
                    foreach (is_array($value) ? $value : [$value] as $part) {
                        if (is_scalar($part) && self::valueRunsCode((string)$part)) {
                            return true;
                        }
                    }
                }

                return false;
        }

        return false;
    }

    /**
     * A TV value or document field that reaches @EVAL / @SELECT (also behind @INHERIT).
     */
    public static function valueRunsCode(string $value): bool
    {
        $core = 'EvolutionCMS\\Support\\TvBindingGuard';
        if (class_exists($core)) {
            return $core::hasCodeBinding($value);
        }

        for ($depth = 0; $depth < 20; $depth++) {
            $value = ltrim($value, " \t\n\r\0\x0B");
            if (preg_match('/^@{1,2}(?:EVAL|SELECT)/i', $value)) {
                return true;
            }
            if (!preg_match('/^@INHERIT/i', $value)) {
                return false;
            }
            $value = substr($value, 8);
        }

        return true;
    }

    /**
     * A TV definition column holding a binding that runs code; @INCLUDE counts there, and each
     * value of display_params is a binding of its own.
     */
    public static function definitionRunsCode(string $value, string $column = ''): bool
    {
        $core = 'EvolutionCMS\\Support\\TvBindingGuard';
        if (class_exists($core)) {
            return $core::definitionHasCodeBinding($value, $column);
        }

        $candidates = [$value];
        if ($column === 'display_params') {
            foreach (explode('&', $value) as $pair) {
                $pos = strpos($pair, '=');
                if ($pos !== false) {
                    $candidates[] = str_replace(['%3D', '%26'], ['=', '&'], substr($pair, $pos + 1));
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (self::valueRunsCode($candidate) || preg_match('/^@{1,2}INCLUDE/i', ltrim($candidate, " \t\n\r\0\x0B"))) {
                return true;
            }
        }

        return false;
    }
}
