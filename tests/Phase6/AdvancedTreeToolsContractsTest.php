<?php

declare(strict_types=1);

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertContainsText(string $haystack, string $needle, string $message): void
{
    assertTrue(str_contains($haystack, $needle), $message . " [missing: {$needle}]");
}

$root = dirname(__DIR__, 2);

$contract = file_get_contents($root . '/TOOLSET.md');
foreach (['root_tree', 'children', 'descendants', 'children_range', 'prev_siblings', 'next_siblings', 'neighbors', 'siblings_range', 'ancestors'] as $legacy) {
    assertContainsText($contract, "| `{$legacy}(", "Missing v1 migration for {$legacy}");
}
echo "Advanced tree tool migration contract checks passed.\n";
