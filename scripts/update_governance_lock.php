<?php

declare(strict_types=1);

require_once __DIR__ . '/governance_helpers.php';

$root = dirname(__DIR__);
$lockPath = $root . '/.ci/governance-lock.json';

$toolset = governance_parse_toolset($root . '/TOOLSET.md');
$spec = governance_parse_spec($root . '/SPEC.md');
$modelHash = governance_model_allowlists_hash($root . '/src/Support/ModelFieldPolicy.php');
$fixturesHash = governance_golden_fixtures_hash($root . '/tests/Fixtures/golden');

$payload = [
    'toolset_version' => $toolset['toolset_version'],
    'canonical_tools' => $toolset['canonical_tools'],
    'spec_version' => $spec['spec_version'],
    'runtime_status' => $spec['runtime_status'],
    'spec_public_contract_hash' => $spec['public_contract_hash'],
    'model_allowlists_hash' => $modelHash,
    'golden_fixtures_hash' => $fixturesHash,
];

if (governance_write_lock($lockPath, $payload)) {
    echo "Governance lock updated: {$lockPath}\n";
} else {
    echo "Governance lock unchanged: {$lockPath}\n";
}
