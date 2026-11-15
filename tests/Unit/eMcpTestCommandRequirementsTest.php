<?php

declare(strict_types=1);

namespace Illuminate\Console {
    if (!class_exists(Command::class, false)) {
        /**
         * Minimal console command surface required to load the smoke-test command.
         */
        abstract class Command
        {
            public const SUCCESS = 0;
            public const FAILURE = 1;
        }
    }
}

namespace {
    use EvolutionCMS\eMCP\Console\Commands\eMcpTestCommand;
    use EvolutionCMS\eMCP\Servers\ContentServer;

    /**
     * Fail the standalone regression test when a smoke-test requirement changes.
     */
    function assertRequirement(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    require_once __DIR__ . '/../../src/Console/Commands/eMcpTestCommand.php';

    $command = new eMcpTestCommand();
    $method = new ReflectionMethod($command, 'requiredToolsForServer');

    /** @var array<int, string> $coreRequirements */
    $coreRequirements = $method->invoke($command, ContentServer::class);
    /** @var array<int, string> $extensionRequirements */
    $extensionRequirements = $method->invoke($command, 'Vendor\\Project\\PingServer');

    assertRequirement(
        in_array('evo.content.search', $coreRequirements, true),
        'Canonical ContentServer must retain Evolution toolset assertions'
    );
    assertRequirement(
        in_array('evo.model.get', $coreRequirements, true),
        'Canonical ContentServer must retain model tool assertions'
    );
    assertRequirement(
        $extensionRequirements === [],
        'Third-party servers must only receive generic initialize/tools:list checks'
    );

    /** @var array<int, string> $expectedExtension */
    $expectedExtension = $method->invoke($command, 'Vendor\Project\PingServer', ['amount.mounts', ' ', 'amount.mounts', 7]);
    assertRequirement(
        $expectedExtension === ['amount.mounts'],
        'Third-party servers must require exactly the tools named by --expect-tool'
    );

    /** @var array<int, string> $expectedCore */
    $expectedCore = $method->invoke($command, ContentServer::class, ['amount.mounts', 'evo.content.get']);
    assertRequirement(
        in_array('amount.mounts', $expectedCore, true) && in_array('evo.content.search', $expectedCore, true),
        'ContentServer must require both its own toolset and the tools named by --expect-tool'
    );
    assertRequirement(
        count($expectedCore) === count(array_unique($expectedCore)),
        'Required tools must not repeat'
    );

    // extra-integration.yml passes these options; renaming one breaks every Extra's CI.
    $signature = (string)(new ReflectionProperty($command, 'signature'))->getValue($command);
    assertRequirement(
        str_contains($signature, '{--expect-tool=*') && str_contains($signature, '{--user='),
        'emcp:test must keep the --expect-tool and --user options used by the reusable Extra workflow'
    );

    echo "eMCP test command requirement checks passed.\n";
}
