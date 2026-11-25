<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use EvolutionCMS\eMCP\Contracts\HasDefinitionRevision;
use EvolutionCMS\eMCP\Support\CustomConfigPath;
use EvolutionCMS\eMCP\Support\SettingsFile;
use Laravel\Mcp\Server\Tool;

final class ToolsRevision
{
    public static function current(): string
    {
        $core = defined('EVO_CORE_PATH') ? rtrim((string)EVO_CORE_PATH, '/\\') : base_path('core');

        return self::files([
            $core . '/composer.lock',
            $core . '/vendor/composer/installed.php',
            CustomConfigPath::to('mcp.php'),
            SettingsFile::sitePath(),
        ]);
    }

    /** @param list<string> $paths */
    public static function files(array $paths): string
    {
        $times = [];
        foreach ($paths as $path) {
            clearstatcache(true, $path);
            $times[] = @filemtime($path);
        }

        return hash('sha256', json_encode($times, JSON_THROW_ON_ERROR));
    }

    /** @param iterable<Tool> $tools */
    public static function candidates(string $files, iterable $tools): string
    {
        $names = [];
        foreach ($tools as $tool) {
            $names[] = [
                $tool::class,
                $tool->name(),
                $tool instanceof HasDefinitionRevision ? $tool->definitionRevision() : null,
            ];
        }

        return hash('sha256', json_encode([$files, $names], JSON_THROW_ON_ERROR));
    }

    /** @param iterable<Tool> $tools */
    public static function catalog(iterable $tools, ToolRegistry $registry): string
    {
        $hash = hash_init('sha256');
        foreach ($tools as $tool) {
            $entry = $tool->toArray();
            $entry['annotations'] = (object)((array)($entry['annotations'] ?? []) + $registry->annotationsFor($entry['name']));
            hash_update($hash, json_encode($entry, JSON_THROW_ON_ERROR) . "\n");
        }

        return hash_final($hash);
    }
}
