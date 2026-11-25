<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

final class McpConnectionName
{
    public static function fromEndpoint(string $url): string
    {
        $host = SiteDomains::normalise($url);

        return trim((string)preg_replace('/[^a-z0-9-]+/', '-', $host), '-') ?: 'localhost';
    }

    public static function tokenEnvVar(string $name): string
    {
        $prefix = strtoupper(str_replace('-', '_', $name));
        if ($prefix !== '' && ctype_digit($prefix[0])) {
            $prefix = '_' . $prefix;
        }

        return $prefix . '_MCP_TOKEN';
    }
}
