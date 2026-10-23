<?php

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/governance_helpers.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emcp-governance-' . bin2hex(random_bytes(4));
mkdir($dir);
$lock = $dir . DIRECTORY_SEPARATOR . 'governance-lock.json';

try {
    $payload = ['toolset_version' => '1.0', 'canonical_tools' => ['evo.content.get'], 'model_allowlists_hash' => 'aaa'];

    assertTrue(governance_write_lock($lock, $payload, '2026-01-01T00:00:00+00:00'), 'a missing lock is written');
    $written = json_decode((string)file_get_contents($lock), true);
    assertTrue($written === $payload + ['updated_at' => '2026-01-01T00:00:00+00:00'], 'payload and updated_at are stored');
    assertTrue(str_ends_with((string)file_get_contents($lock), "}\n"), 'the file ends with a newline');

    $before = file_get_contents($lock);
    assertTrue(!governance_write_lock($lock, $payload, '2026-02-02T00:00:00+00:00'), 'same hashes: nothing is written');
    assertTrue(file_get_contents($lock) === $before, 'updated_at is not bumped when nothing else changed');

    assertTrue(
        !governance_write_lock($lock, $payload + ['updated_at' => 'ignored'], '2026-02-02T00:00:00+00:00'),
        'an updated_at in the payload does not count as a change'
    );

    $changed = ['model_allowlists_hash' => 'bbb'] + $payload;
    assertTrue(governance_write_lock($lock, $changed, '2026-03-03T00:00:00+00:00'), 'a changed hash is written');
    $written = json_decode((string)file_get_contents($lock), true);
    assertTrue($written['model_allowlists_hash'] === 'bbb', 'the new hash is stored');
    assertTrue($written['updated_at'] === '2026-03-03T00:00:00+00:00', 'updated_at moves with a real change');

    file_put_contents($lock, 'not json');
    assertTrue(governance_write_lock($lock, $payload, '2026-04-04T00:00:00+00:00'), 'a broken lock is rewritten');
    assertTrue(json_decode((string)file_get_contents($lock), true)['model_allowlists_hash'] === 'aaa', 'and holds the payload');
} finally {
    @unlink($lock);
    @rmdir($dir);
}

echo "Governance lock write checks passed.\n";
