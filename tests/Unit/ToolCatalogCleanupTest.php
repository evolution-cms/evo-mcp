<?php

declare(strict_types=1);

namespace Illuminate\Cache {
    /** Same on-disk layout as the Laravel store. */
    final class FileStore
    {
        public function __construct(private readonly string $directory)
        {
        }

        public function path(string $key): string
        {
            $hash = sha1($key);

            return $this->directory . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        }

        public function put(string $key, mixed $value, int $seconds): bool
        {
            $path = $this->path($key);
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            return file_put_contents($path, (time() + $seconds) . serialize($value)) !== false;
        }

        public function getDirectory(): string
        {
            return $this->directory;
        }
    }
}

namespace Illuminate\Http {
    final class Request
    {
        public object $attributes;

        public function __construct()
        {
            $this->attributes = new class {
                public function get(string $key): ?string
                {
                    return ['emcp.context' => 'api'][$key] ?? null;
                }
            };
        }

        public function getHttpHost(): string
        {
            return 'example.test';
        }
    }
}

namespace Symfony\Component\HttpFoundation {
    class Response
    {
        public object $headers;

        public function __construct(string $session)
        {
            $this->headers = new class ($session) {
                public function __construct(private readonly string $session)
                {
                }

                public function get(string $name): string
                {
                    return $this->session;
                }
            };
        }

        public function isSuccessful(): bool
        {
            return true;
        }
    }
}

namespace EvolutionCMS\eMCP\Services {
    final class ToolRegistry
    {
    }
}

namespace {
    use EvolutionCMS\eMCP\Services\ToolCatalogCleanup;
    use EvolutionCMS\eMCP\Services\ToolListNotifications;
    use EvolutionCMS\eMCP\Services\ToolRegistry;
    use Illuminate\Cache\FileStore;
    use Illuminate\Http\Request;
    use Symfony\Component\HttpFoundation\Response;

    require_once __DIR__ . '/../../src/Services/ToolCatalogCleanup.php';
    require_once __DIR__ . '/../../src/Services/ToolListNotifications.php';

    function verify(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    /** @return list<string> */
    function stateFiles(string $directory): array
    {
        return array_values(array_filter(
            glob($directory . '/*/*/*') ?: [],
            static fn (string $path): bool => basename($path) !== 'README'
        ));
    }

    function removeTree(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
            $entry = $path . '/' . $name;
            is_dir($entry) ? removeTree($entry) : unlink($entry);
        }
        rmdir($path);
    }

    $directory = sys_get_temp_dir() . '/emcp-catalog-' . bin2hex(random_bytes(8));
    $store = new FileStore($directory);
    $notifications = new ToolListNotifications($store, new ToolRegistry());
    $initialize = static fn (string $session, string $version = '2025-11-25'): bool => $notifications->register(
        new Request(),
        'content',
        new Response($session),
        ['protocolVersion' => $version, 'capabilities' => ['tools' => []]]
    );

    try {
        verify(ToolCatalogCleanup::run($directory, 60, 10) === 0, 'A missing directory must be ignored');

        verify($initialize('abandoned'), 'The abandoned session must be registered');
        [$abandoned] = stateFiles($directory);
        verify($initialize('live'), 'The live session must be registered');
        [$live] = array_values(array_diff(stateFiles($directory), [$abandoned]));
        $content = file_get_contents($live);
        $note = dirname($live) . '/README';
        file_put_contents($note, 'not a store record');

        touch($abandoned, time() - 90000 - 60);
        touch($live, time() - 86400 + 60);
        touch($note, time() - 200000);

        verify(!$initialize('old-protocol', '2024-11-05'), 'Old protocol sessions must not be registered');
        verify(is_file($abandoned), 'Cleanup must only follow a new registration');

        verify($initialize('newcomer'), 'A new session must be registered');
        verify(!is_file($abandoned), 'A stale record must be removed by another session');
        verify(is_file($live) && file_get_contents($live) === $content, 'A live record must be preserved');
        verify(is_file($note), 'Files that are not store records must be preserved');
        verify(count(stateFiles($directory)) === 2, 'The new record must be preserved');

        removeTree($directory);
        for ($i = 0; $i < 60; $i++) {
            $store->put('stale-' . $i, [], 1);
            touch($store->path('stale-' . $i), time() - 100);
        }
        // Every directory on the way to a file counts as a visit.
        $removed = ToolCatalogCleanup::run($directory, 60, 9);
        verify($removed > 0 && $removed < 9, 'One run must stay within its limit');
        verify(count(stateFiles($directory)) === 60 - $removed, 'A bounded run must leave the rest');
        for ($i = 0; $i < 5000 && stateFiles($directory) !== []; $i++) {
            ToolCatalogCleanup::run($directory, 60, 9);
        }
        verify(stateFiles($directory) === [], 'Successive bounded runs must cover the whole store');
    } finally {
        if (is_dir($directory)) {
            removeTree($directory);
        }
    }

    echo "Tool catalog cleanup checks passed.\n";
}
