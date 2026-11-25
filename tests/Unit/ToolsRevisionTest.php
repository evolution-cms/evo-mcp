<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services {
    final class ToolRegistry
    {
        public function annotationsFor(string $name): array
        {
            return [];
        }
    }
}

namespace {
    use EvolutionCMS\eMCP\Contracts\HasDefinitionRevision;
    use EvolutionCMS\eMCP\Services\ToolRegistry;
    use EvolutionCMS\eMCP\Services\ToolsRevision;

    require_once __DIR__ . '/../../src/Contracts/HasDefinitionRevision.php';
    require_once __DIR__ . '/../../src/Services/ToolsRevision.php';

    function verify(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'emcp-revision-');
    verify(is_string($path), 'Unable to allocate temporary file');
    try {
        touch($path, 1700000000);
        $before = ToolsRevision::files([$path]);
        touch($path, 1700000001);
        $after = ToolsRevision::files([$path]);
        verify($before !== $after, 'A timestamp change must invalidate the file revision');
        verify($after === ToolsRevision::files([$path]), 'File revision must be stable when timestamps are unchanged');
        unlink($path);
        verify($after !== ToolsRevision::files([$path]), 'Removing a file must invalidate the file revision');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }

    $extra = new class {
        public string $type = 'string';

        public function name(): string
        {
            return 'fixture.catalog';
        }

        public function toArray(): array
        {
            return ['name' => $this->name(), 'inputSchema' => ['properties' => ['value' => ['type' => $this->type]]]];
        }
    };
    $registry = new ToolRegistry();
    $candidates = ToolsRevision::candidates($before, [$extra]);
    $catalog = ToolsRevision::catalog([$extra], $registry);
    verify($candidates !== ToolsRevision::candidates($before, []), 'Removing a visible package tool must change the candidate revision');
    $extra->type = 'integer';
    verify($candidates === ToolsRevision::candidates($before, [$extra]), 'A tool without a definition revision must not change the candidate revision');
    verify($catalog !== ToolsRevision::catalog([$extra], $registry), 'Changing an extra tool schema must change the catalog hash');

    $signalling = new class implements HasDefinitionRevision {
        public string $revision = '1';

        public function name(): string
        {
            return 'fixture.signalling';
        }

        public function definitionRevision(): string
        {
            return $this->revision;
        }
    };
    $candidates = ToolsRevision::candidates($before, [$signalling]);
    verify($candidates === ToolsRevision::candidates($before, [$signalling]), 'An unchanged definition revision must keep the candidate revision');
    $signalling->revision = '2';
    verify($candidates !== ToolsRevision::candidates($before, [$signalling]), 'A new definition revision must change the candidate revision');

    echo "Tool catalog revision checks passed.\n";
}
