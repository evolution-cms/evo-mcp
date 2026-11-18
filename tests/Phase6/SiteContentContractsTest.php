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

/** @var array<string, string> $expectedTools */
$expectedTools = [
    'ContentSearchTool.php' => 'evo.content.search',
    'ContentGetTool.php' => 'evo.content.get',
    'ContentTreeTool.php' => 'evo.content.tree',
    'ContentAncestorsTool.php' => 'evo.content.ancestors',
    'ContentSiblingsTool.php' => 'evo.content.siblings',
];

foreach ($expectedTools as $fileName => $toolName) {
    $path = $root . '/src/Tools/Content/' . $fileName;
    assertTrue(is_file($path), "Missing tool file: {$fileName}");

    $content = file_get_contents($path);
    assertTrue(is_string($content), "Unable to read tool file: {$fileName}");

    assertTrue(
        str_contains($content, "#[Name('{$toolName}')]") || str_contains($content, "#[Name(\"{$toolName}\")]"),
        "Missing canonical #[Name] for {$toolName} in {$fileName}"
    );
    assertContainsText($content, 'extends BaseContentTool', "{$fileName} must extend BaseContentTool");
}

$searchPath = $root . '/src/Tools/Content/ContentSearchTool.php';
$searchContent = file_get_contents($searchPath);
assertTrue(is_string($searchContent), 'Unable to read ContentSearchTool.php');
assertContainsText($searchContent, "'tv_filters' => ['nullable', 'array']", 'search must accept structured tv_filters array');
assertContainsText($searchContent, "'tv_order' => ['nullable', 'array']", 'search must accept structured tv_order array');
assertContainsText($searchContent, 'buildTvFilterString', 'search must build tv filter string from structured payload');
assertContainsText($searchContent, 'buildTvOrderString', 'search must build tv order string from structured payload');
assertContainsText($searchContent, 'resolveLimitOffset', 'search must enforce limit/offset guardrails');
assertContainsText($searchContent, 'resolveDepth', 'search must enforce depth guardrails');

foreach (['ContentTreeTool.php', 'ContentAncestorsTool.php', 'ContentSiblingsTool.php'] as $fileName) {
    $source = file_get_contents($root . '/src/Tools/Content/' . $fileName);
    assertContainsText($source, 'findAnchor', "{$fileName} must check anchor access");
    assertContainsText($source, 'tvSchema', "{$fileName} must advertise TV projection");
    assertContainsText($source, 'respondList(', "{$fileName} must return the list envelope");
}

echo "SiteContent tree/TV contract checks passed.\n";
