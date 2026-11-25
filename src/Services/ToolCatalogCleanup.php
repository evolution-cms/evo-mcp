<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

/** Removes abandoned files from a Laravel FileStore directory, which never does so on its own. */
final class ToolCatalogCleanup
{
    /**
     * Visits at most $limit directories and files, in random order so that successive calls cover a large store.
     *
     * Age is the modification time: every write to the store renews it together with the TTL.
     */
    public static function run(string $directory, int $maxAge, int $limit): int
    {
        $cutoff = time() - $maxAge;
        $removed = 0;

        foreach (self::entries($directory, '/\A[0-9a-f]{2}\z/') as $bucket) {
            if (--$limit < 0) {
                return $removed;
            }
            foreach (self::entries($bucket, '/\A[0-9a-f]{2}\z/') as $leaf) {
                if (--$limit < 0) {
                    return $removed;
                }
                foreach (self::entries($leaf, '/\A[0-9a-f]{40}\z/') as $file) {
                    if (--$limit < 0) {
                        return $removed;
                    }
                    clearstatcache(true, $file);
                    $modified = @filemtime($file);
                    if ($modified !== false && $modified < $cutoff && @unlink($file)) {
                        $removed++;
                    }
                }
            }
        }

        return $removed;
    }

    /** @return list<string> */
    private static function entries(string $directory, string $pattern): array
    {
        $names = preg_grep($pattern, @scandir($directory) ?: []) ?: [];
        shuffle($names);

        return array_map(static fn (string $name): string => $directory . '/' . $name, $names);
    }
}
