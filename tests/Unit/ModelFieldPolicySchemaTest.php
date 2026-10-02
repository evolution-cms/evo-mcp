<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Support/ModelFieldPolicy.php';

use EvolutionCMS\eMCP\Support\ModelFieldPolicy;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Column listings of a stock Evolution CMS 3.5 install. A projected column missing from the
// table makes evo.model.list|get fail with a SQL "Unknown column" error.
$schemaPath = __DIR__ . '/../Fixtures/schema/evo-3.5-columns.json';
$schema = json_decode((string)file_get_contents($schemaPath), true);
assertTrue(is_array($schema), 'Schema fixture is not valid JSON.');

$allowlists = ModelFieldPolicy::fieldAllowlists();

foreach ($allowlists as $model => $fields) {
    assertTrue(isset($schema[$model]), "Schema fixture has no columns for model [{$model}]");

    $columns = array_fill_keys($schema[$model], true);
    foreach ($fields as $field) {
        assertTrue(
            isset($columns[$field]),
            "Allowlisted field [{$field}] does not exist in Evo 3.5 table for model [{$model}]"
        );
    }
}

echo "Model field schema checks passed.\n";
