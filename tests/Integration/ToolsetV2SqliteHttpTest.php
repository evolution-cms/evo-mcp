<?php

declare(strict_types=1);

use EvolutionCMS\eMCP\Services\TokenService;
use JsonSchema\Validator;

/**
 * Exercise actual HTTP tool calls against an explicitly selected disposable Evo SQLite install.
 *
 * The web process and this PHP process must use that same database.
 */
if (getenv('EMCP_LOCAL_MUTATION_TEST') !== '1') {
    echo "Toolset v2 SQLite integration skipped (enable only on a disposable install).\n";
    exit(0);
}

$url = (string)getenv('EMCP_LOCAL_MCP_URL');
$database = (string)getenv('EMCP_LOCAL_SQLITE');
$core = (string)getenv('EMCP_LOCAL_CORE');
$admin = (string)getenv('EMCP_LOCAL_ADMIN_TOKEN');
if (!in_array(parse_url($url, PHP_URL_HOST), ['127.0.0.1', 'localhost', '[::1]'], true)
    || !is_file($database) || !is_file($core . '/vendor/autoload.php') || $admin === ''
) {
    throw new RuntimeException('Provide a local MCP URL, a disposable SQLite file, the Evo core and an admin PAT.');
}

require $core . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/scripts/governance_helpers.php';

$db = new PDO('sqlite:' . $database, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/** Run a bound statement on the fixture database. */
function run(PDO $db, string $sql, array $parameters = []): PDOStatement
{
    $statement = $db->prepare($sql);
    $statement->execute($parameters);

    return $statement;
}

/** Fail with the name of the missing behavior. */
function check(bool $pass, string $message): void
{
    if (!$pass) {
        throw new RuntimeException($message);
    }
}

/** Send one actual JSON-RPC request to the local eMCP PAT endpoint. */
function rpc(string $url, string $token, string $method, array|object $params): object
{
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
            'MCP-Protocol-Version: 2025-11-25',
            'Authorization: Bearer ' . $token,
        ],
        'content' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR),
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $body = file_get_contents($url, false, $context);

    return json_decode($body, false, flags: JSON_THROW_ON_ERROR);
}

/** Call a tool, optionally expecting an error, and validate every success against discovery. */
function call(string $url, string $token, array $tools, string $name, array $arguments, ?string $error = null): ?object
{
    $response = rpc($url, $token, 'tools/call', ['name' => $name, 'arguments' => (object)$arguments]);
    if ($error !== null) {
        $errorEnvelope = isset($response->error) || ($response->result->isError ?? false);
        check($errorEnvelope && ($error === '*' || str_contains(json_encode($response, JSON_THROW_ON_ERROR), $error)),
            "{$name}: expected error {$error}, received " . json_encode($response, JSON_THROW_ON_ERROR));

        return null;
    }

    $result = $response->result->structuredContent ?? null;
    check(is_object($result) && !($response->result->isError ?? false),
        "{$name}: missing success result: " . json_encode($response, JSON_THROW_ON_ERROR));
    $validator = new Validator();
    $validator->validate($result, $tools[$name]->outputSchema);
    check($validator->isValid(), "{$name}: outputSchema rejected its actual response: " . json_encode($validator->getErrors()));

    return $result;
}

/** Check the complete ordered ID sequence of a page or path. */
function ids(object $result, array $expected, string $case): void
{
    $actual = array_map(static fn(object $item): int => (int)$item->id, $result->items);
    check($actual === $expected && $result->meta->count === count($expected),
        "{$case}: expected " . json_encode($expected) . ', got ' . json_encode($actual));
}

$init = rpc($url, $admin, 'initialize', [
    'protocolVersion' => '2025-11-25',
    'capabilities' => (object)[],
    'clientInfo' => ['name' => 'toolset-v2-integration', 'version' => '1.0'],
]);
$contract = governance_parse_toolset(dirname(__DIR__, 2) . '/TOOLSET.md');
check(($init->result->capabilities->evo->toolsetVersion ?? null) === $contract['toolset_version'], 'initialize toolset version');
$tools = [];
$cursor = null;
do {
    $params = $cursor === null ? (object)[] : ['cursor' => $cursor];
    $page = rpc($url, $admin, 'tools/list', $params)->result ?? throw new RuntimeException('tools/list failed');
    foreach ($page->tools as $entry) {
        check(!isset($tools[$entry->name]), "Duplicate tool name: {$entry->name}");
        $tools[$entry->name] = $entry;
    }
    $cursor = $page->nextCursor ?? null;
} while ($cursor !== null);
$coreTools = array_filter(array_keys($tools), static fn($name) => str_starts_with($name, 'evo.'));
check(count($coreTools) === 14 && array_diff($contract['canonical_tools'], $coreTools) === [], 'Exact canonical 14 tools');
foreach ($coreTools as $name) {
    $tool = $tools[$name];
    $properties = (array)$tool->inputSchema->properties;
    foreach ($properties as $key => $property) {
        check(trim($property->description ?? '') !== '', "{$name}: missing description of {$key}");
    }
    foreach ($tool->inputSchema->required ?? [] as $key) {
        check(isset($properties[$key]), "{$name}: unknown required parameter {$key}");
    }
    check(($tool->outputSchema->type ?? null) === 'object', "{$name}: missing outputSchema");
    check(($tool->annotations->readOnlyHint ?? null) === !str_starts_with($name, 'evo.write.'), "{$name}: readOnlyHint mismatch");
}
check(in_array('limit', $tools['evo.content.search']->inputSchema->required, true), 'search advertises required limit');

$base = (int)$db->query('SELECT MAX(id) FROM evo_site_content')->fetchColumn() + 100;
[$root, $other, $before, $anchor, $tie, $after, $grandchild, $private, $hiddenChild, $trashed, $siteStart] =
    range($base, $base + 10);
$nodes = [
    [$root, 0, 60, 'Fixture root', 1, 0],
    [$other, 0, 70, 'Other branch', 1, 0],
    [$before, $root, 1, 'Before gap', 1, 0],
    [$anchor, $root, 5, 'Original title', 1, 0],
    [$tie, $root, 5, 'Equal position', 1, 0],
    [$after, $root, 12, 'After gap', 1, 0],
    [$grandchild, $anchor, 0, 'Grandchild', 1, 0],
    [$private, $root, 30, 'Unpublished anchor', 0, 0],
    [$hiddenChild, $private, 0, 'Visible child under hidden anchor', 1, 0],
    [$trashed, $root, 40, 'Trashed anchor', 1, 1],
    [$siteStart, $other, 0, 'Unrelated public document', 1, 0],
];
$parents = [];
$template = (int)$db->query('SELECT MIN(id) FROM evo_site_templates')->fetchColumn();
foreach ($nodes as [$id, $parent, $position, $title, $published, $deleted]) {
    run($db, 'INSERT INTO evo_site_content (id,parent,menuindex,pagetitle,alias,template,published,deleted,isfolder) VALUES (?,?,?,?,?,?,?,?,1)',
        [$id, $parent, $position, $title, 'emcp-fixture-' . $id, $template, $published, $deleted]);
    $parents[$id] = $parent;
    $ancestor = $id;
    for ($depth = 0; $ancestor !== 0; $depth++) {
        run($db, 'INSERT INTO evo_site_content_closure (ancestor,descendant,depth) VALUES (?,?,?)', [$ancestor, $id, $depth]);
        $ancestor = $parents[$ancestor];
    }
}

$read = static fn(string $name, array $args): object => call($url, $admin, $tools, 'evo.content.' . $name, $args);
$rootIds = array_map('intval', $db->query('SELECT id FROM evo_site_content WHERE parent = 0 AND deleted = 0 ORDER BY menuindex, id')->fetchAll(PDO::FETCH_COLUMN));
ids($read('tree', ['id' => 0, 'limit' => 50]), $rootIds, 'tree default depth 1 from site root');
$rootTree = $read('tree', ['id' => 0, 'depth' => 2, 'limit' => 50]);
$rootTreeIds = array_map(static fn($item) => (int)$item->id, $rootTree->items);
check(in_array($anchor, $rootTreeIds, true) && !in_array($grandchild, $rootTreeIds, true), 'root_tree(1) migrates to tree(0, 2)');
check(count($rootTreeIds) === count(array_unique($rootTreeIds)), 'Root tree returned duplicate IDs');
ids($read('tree', ['id' => $root, 'limit' => 50]), [$before, $anchor, $tie, $after, $private], 'children and default descendants migration');
ids($read('tree', ['id' => $root, 'depth' => 2, 'limit' => 50]),
    [$before, $anchor, $tie, $after, $private, $grandchild, $hiddenChild], 'descendants depth migration');
ids($read('tree', ['id' => $root, 'depth' => 1, 'menuindex_from' => 5, 'menuindex_to' => 5, 'limit' => 50]), [$anchor, $tie], 'children_range migration');
ids($read('siblings', ['id' => $anchor, 'limit' => 50]), [$before, $tie, $after, $private], 'siblings migration');
ids($read('siblings', ['id' => $anchor, 'direction' => 'before', 'limit' => 50]), [$before], 'prev_siblings migration');
ids($read('siblings', ['id' => $anchor, 'direction' => 'after', 'limit' => 50]), [$tie, $after, $private], 'next_siblings migration and tie');
ids($read('siblings', ['id' => $anchor, 'direction' => 'adjacent']), [$before, $tie], 'neighbors migration across a gap and a tie');
ids($read('siblings', ['id' => $anchor, 'menuindex_from' => 5, 'menuindex_to' => 5, 'limit' => 50]), [$tie], 'siblings_range migration');
ids($read('ancestors', ['id' => $grandchild]), [$root, $anchor], 'ancestors migration without paging');
check($read('ancestors', ['id' => $grandchild])->meta->truncated === false, 'Path should not be truncated');

$pathLimit = $read('ancestors', ['id' => $grandchild])->meta->limit;
$chainStart = $base + 20;
$chainEnd = $chainStart + $pathLimit;
$db->beginTransaction();
try {
    for ($id = $chainStart; $id <= $chainEnd; $id++) {
        run($db, 'INSERT INTO evo_site_content (id,parent,menuindex,pagetitle,alias,template,published,deleted,isfolder) VALUES (?,?,0,?,?,?,1,0,1)',
            [$id, $id === $chainStart ? $root : $id - 1, 'Deep path fixture', 'emcp-fixture-' . $id, $template]);
        run($db, 'INSERT INTO evo_site_content_closure (ancestor,descendant,depth) VALUES (?,?,?)',
            [$root, $id, $id - $chainStart + 1]);
        for ($ancestor = $chainStart; $ancestor <= $id; $ancestor++) {
            run($db, 'INSERT INTO evo_site_content_closure (ancestor,descendant,depth) VALUES (?,?,?)', [$ancestor, $id, $id - $ancestor]);
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
$boundedPath = $read('ancestors', ['id' => $chainEnd]);
ids($boundedPath, range($chainStart, $chainEnd - 1), 'Server-limited path keeps the nearest ancestors');
check($boundedPath->meta->truncated === true, 'Server-limited ancestor path reports truncation');

$role = (int)$db->query('SELECT MAX(id) FROM evo_user_roles')->fetchColumn() + 1;
$user = (int)$db->query('SELECT MAX(id) FROM evo_users')->fetchColumn() + 1;
$editor = 'emcp_' . bin2hex(random_bytes(20));
run($db, 'INSERT INTO evo_user_roles (id,name) VALUES (?,?)', [$role, 'eMCP fixture ' . $role]);
run($db, 'INSERT INTO evo_users (id,username,password) VALUES (?,?,?)', [$user, 'emcp-fixture-' . $user, '']);
run($db, 'INSERT INTO evo_user_attributes (internalKey,role) VALUES (?,?)', [$user, $role]);
foreach (['emcp', 'save_document'] as $permission) {
    run($db, 'INSERT INTO evo_role_permissions (role_id,permission) VALUES (?,?)', [$role, $permission]);
}
run($db, 'INSERT INTO evo_emcp_tokens (user_id,name,token_prefix,token_hash,scopes) VALUES (?,?,?,?,?)', [
    $user, 'test fixture', substr($editor, 0, 12), TokenService::hash($editor), '["mcp:read","mcp:call","mcp:write"]',
]);
try {
    foreach ([$chainEnd + 99, $trashed, $private] as $missing) {
        foreach (['all', 'before', 'after', 'adjacent'] as $direction) {
            $args = ['id' => $missing, 'direction' => $direction] + ($direction === 'adjacent' ? [] : ['limit' => 50]);
            ids(call($url, $editor, $tools, 'evo.content.siblings', $args), [], "Empty sibling {$direction} for unavailable anchor {$missing}");
        }
        ids(call($url, $editor, $tools, 'evo.content.tree', ['id' => $missing, 'limit' => 50]), [], 'Empty tree for unavailable anchor');
        ids(call($url, $editor, $tools, 'evo.content.ancestors', ['id' => $missing]), [], 'Empty path for unavailable anchor');
    }

    $tvValues = [];
    foreach (['one', 'two'] as $suffix) {
        $name = 'emcp_' . $anchor . '_' . $suffix;
        run($db, 'INSERT INTO evo_site_tmplvars (name,type) VALUES (?,?)', [$name, 'text']);
        $tv = (int)$db->lastInsertId();
        run($db, 'INSERT INTO evo_site_tmplvar_templates (tmplvarid,templateid) VALUES (?,?)', [$tv, $template]);
        run($db, 'INSERT INTO evo_site_tmplvar_contentvalues (tmplvarid,contentid,value) VALUES (?,?,?)', [$tv, $anchor, 'original']);
        $tvValues[$name] = $tv;
    }
    [$tv1, $tv2] = array_values($tvValues);
    [$name1, $name2] = array_keys($tvValues);
    $write = static fn(array $fields, array $tvs, ?string $error = null): ?object =>
        call($url, $editor, $tools, 'evo.write.content.update', ['id' => $anchor, 'fields' => $fields, 'tvs' => $tvs], $error);
    $row = static fn(): array => run($db, 'SELECT pagetitle,published,pub_date,unpub_date FROM evo_site_content WHERE id=?', [$anchor])->fetch(PDO::FETCH_ASSOC);
    $tvRows = static fn(): array => run($db, 'SELECT value FROM evo_site_tmplvar_contentvalues WHERE contentid=? ORDER BY tmplvarid', [$anchor])->fetchAll(PDO::FETCH_COLUMN);
    $original = $row();

    foreach (['published' => false, 'pub_date' => time() + 3600, 'unpub_date' => time() + 7200] as $field => $value) {
        $write([$field => $value, 'pagetitle' => 'Denied'], [$name1 => 'Denied'], 'publish_document');
        check($row() === $original && $tvRows() === ['original', 'original'], "Unauthorized {$field} left fields and TVs unchanged");
    }
    $write(['pagetitle' => 'Unknown TV'], ['unassigned_tv' => 'value'], 'not assigned');
    check($row() === $original, 'Unknown TV failed before document write');

    // Fail the second TV only when both the document and the first TV have already been changed.
    $trigger = 'emcp_abort_tv_' . $anchor;
    $db->exec("CREATE TRIGGER {$trigger} BEFORE UPDATE ON evo_site_tmplvar_contentvalues
        WHEN NEW.tmplvarid = {$tv2} AND NEW.contentid = {$anchor}
        AND (SELECT pagetitle FROM evo_site_content WHERE id = {$anchor}) = 'Rollback title'
        AND (SELECT value FROM evo_site_tmplvar_contentvalues WHERE contentid = {$anchor} AND tmplvarid = {$tv1}) = 'written first'
        BEGIN SELECT RAISE(ABORT, 'Second TV fixture failure'); END");
    try {
        $write(['pagetitle' => 'Rollback title'], [$name1 => 'written first', $name2 => 'fails second'], '*');
        check($row() === $original && $tvRows() === ['original', 'original'], 'Second TV failure rolled back both the document and the first TV');
    } finally {
        $db->exec("DROP TRIGGER {$trigger}");
    }

    $saved = $write(['pagetitle' => 'Saved title', 'published' => true], [$name1 => 'saved', $name2 => 'saved']);
    check($saved->item->updated_fields === ['pagetitle'] && $saved->item->updated_tvs === [$name1, $name2],
        'Response reports the requested fields and TVs that changed');
    check($row()['pagetitle'] === 'Saved title' && $tvRows() === ['saved', 'saved'], 'Fields and TVs committed together');

    run($db, 'INSERT INTO evo_role_permissions (role_id,permission) VALUES (?,?)', [$role, 'publish_document']);
    $publishDate = time() + 86400;
    $unpublishDate = $publishDate + 86400;
    $write(['pub_date' => $publishDate, 'unpub_date' => $unpublishDate], []);
    check((int)$row()['pub_date'] === $publishDate && (int)$row()['unpub_date'] === $unpublishDate, 'Publisher can change dates alone');
    $write(['published' => false, 'pub_date' => 0, 'unpub_date' => 0], []);
    check((int)$row()['published'] === 0 && (int)$row()['pub_date'] === 0 && (int)$row()['unpub_date'] === 0, 'Publisher can unpublish and clear dates');

    $startId = (int)$db->query("SELECT setting_value FROM evo_system_settings WHERE setting_name = 'site_start'")->fetchColumn();
    if ($startId > 0) {
        call($url, $admin, $tools, 'evo.write.content.update', ['id' => $startId, 'fields' => ['published' => false]], 'cannot be unpublished');
    }

    // Exercise one successful call for each remaining tool, using only disposable CMS records.
    $read('search', ['limit' => 5, 'parent' => $root, 'with_tvs' => [$name1]]);
    check($read('get', ['id' => $chainEnd + 99])->item === null, 'content.get missing item output');
    call($url, $admin, $tools, 'evo.model.list', ['model' => 'SiteTemplate', 'limit' => 1]);
    call($url, $admin, $tools, 'evo.model.get', ['model' => 'SiteTemplate', 'id' => $template]);
    call($url, $admin, $tools, 'evo.elements.list', ['type' => 'template', 'limit' => 1]);
    call($url, $admin, $tools, 'evo.elements.get', ['type' => 'template', 'id' => $template]);
    $created = call($url, $admin, $tools, 'evo.write.content.create', ['parent' => $other, 'fields' => ['pagetitle' => 'Schema fixture ' . $anchor]]);
    call($url, $admin, $tools, 'evo.write.content.publish', ['id' => $created->item->id, 'action' => 'publish']);
    call($url, $admin, $tools, 'evo.write.elements.save', ['type' => 'chunk', 'fields' => ['name' => 'schema_chunk_' . $anchor, 'snippet' => 'Fixture']]);
    call($url, $admin, $tools, 'evo.write.cache.clear', []);
} finally {
    run($db, 'DELETE FROM evo_emcp_tokens WHERE user_id=?', [$user]);
}

echo "Toolset v2 HTTP checks passed: 14 schemas, migration, visibility, rollback and publication permissions.\n";
