<?php

declare(strict_types=1);

use EvolutionCMS\eMCP\Support\McpConnectionName;

require_once __DIR__ . '/../../src/Support/SiteDomains.php';
require_once __DIR__ . '/../../src/Support/McpConnectionName.php';

$cases = [
    ['https://pomocprofi.com/mcp/content', 'pomocprofi-com', 'POMOCPROFI_COM_MCP_TOKEN'],
    ['https://idodomu.com/mcp/content', 'idodomu-com', 'IDODOMU_COM_MCP_TOKEN'],
    ['https://Dev.Example.com:443/cms/mcp/content', 'dev-example-com', 'DEV_EXAMPLE_COM_MCP_TOKEN'],
    ['http://localhost:8381/mcp/content', 'localhost-8381', 'LOCALHOST_8381_MCP_TOKEN'],
    ['http://127.0.0.1:8381/mcp/content', '127-0-0-1-8381', '_127_0_0_1_8381_MCP_TOKEN'],
];

foreach ($cases as [$url, $expectedName, $expectedVariable]) {
    $name = McpConnectionName::fromEndpoint($url);
    if ($name !== $expectedName || McpConnectionName::tokenEnvVar($name) !== $expectedVariable) {
        throw new RuntimeException('Unexpected connection configuration for ' . $url);
    }
}

echo "MCP connection naming checks passed.\n";
