<?php

declare(strict_types=1);

use EvolutionCMS\eMCP\Servers\ContentServer;
use EvolutionCMS\eMCP\Support\ToolContract;

$root = dirname(__DIR__, 2);
require (getenv('EMCP_TEST_AUTOLOAD') ?: $root . '/vendor/autoload.php');
require $root . '/scripts/governance_helpers.php';

function assertContract(bool $pass, string $message): void
{
    if (!$pass) {
        throw new RuntimeException($message);
    }
}

/** Recursively check Laravel MCP's published schema, including defaults and nested arguments. */
function checkSchema(array $schema, string $path, bool $input): void
{
    $kinds = ['array', 'object', 'string', 'integer', 'number', 'boolean', 'null'];
    $types = (array)($schema['type'] ?? []);
    assertContract($types !== [] && array_diff($types, $kinds) === [], "{$path}: invalid JSON Schema type");

    $properties = (array)($schema['properties'] ?? []);
    foreach ($schema['required'] ?? [] as $key) {
        assertContract(isset($properties[$key]), "{$path}: required property {$key} is undeclared");
    }

    if (array_key_exists('default', $schema)) {
        $default = $schema['default'];
        $actualType = match (gettype($default)) {
            'double' => 'number',
            'NULL' => 'null',
            default => gettype($default),
        };
        assertContract(in_array($actualType, $types, true) || ($actualType === 'integer' && in_array('number', $types, true)),
            "{$path}: default has the wrong type");
        if (isset($schema['enum'])) {
            assertContract(in_array($default, $schema['enum'], true), "{$path}: default is outside its enum");
        }
    }

    foreach ($properties as $key => $property) {
        if ($input) {
            assertContract(trim($property['description'] ?? '') !== '', "{$path}.{$key}: parameter has no description");
        }
        checkSchema($property, "{$path}.{$key}", $input);
    }
    if (isset($schema['items'])) {
        checkSchema($schema['items'], $path . '[]', $input);
    }
}

$classes = (new ReflectionClass(ContentServer::class))->getDefaultProperties()['tools'];
$toolNames = [];
foreach ($classes as $class) {
    $definition = (new $class())->toArray();
    $name = $definition['name'];
    assertContract(!in_array($name, $toolNames, true), "Duplicate tool name {$name}");
    $toolNames[] = $name;
    assertContract(trim($definition['description'] ?? '') !== '', "{$name}: no purpose description");
    assertContract(($definition['inputSchema']['type'] ?? null) === 'object', "{$name}: input schema must be an object");
    assertContract(($definition['outputSchema']['type'] ?? null) === 'object', "{$name}: missing structured output schema");
    checkSchema($definition['inputSchema'], $name . '.inputSchema', true);
    checkSchema($definition['outputSchema'], $name . '.outputSchema', false);
}

$contract = governance_parse_toolset($root . '/TOOLSET.md');
assertContract($contract['toolset_version'] === ToolContract::VERSION, 'Documented version differs from runtime');
assertContract(count($toolNames) === 14 && $contract['canonical_tools'] === $toolNames,
    'The registered Evo tools differ from the canonical toolset');

echo "All 14 Evo definitions serialize with documented input and output schemas.\n";
