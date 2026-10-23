<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Support/CustomConfigPath.php';
require_once __DIR__ . '/../../src/Support/SettingsFile.php';
require_once __DIR__ . '/../../src/Support/SettingsCatalog.php';

use EvolutionCMS\eMCP\Support\SettingsCatalog;
use EvolutionCMS\eMCP\Support\SettingsFile;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function evalConfig(string $code): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'emcp');
    file_put_contents($tmp, $code);
    try {
        $config = include $tmp;
    } finally {
        unlink($tmp);
    }
    assertTrue(is_array($config), 'Rewritten config must still return an array');

    return $config;
}

$defaultPath = SettingsFile::defaultSource();
$defaultCode = (string)file_get_contents($defaultPath);
$defaults = include $defaultPath;

// --- ensurePublished: creates missing directories, never overwrites the site's file.
$dir = sys_get_temp_dir() . '/emcp-settings-' . bin2hex(random_bytes(4));
$target = $dir . '/cms/settings/eMCP.php';
assertTrue(SettingsFile::ensurePublished($defaultPath, $target) === true, 'First publish must create the file');
assertTrue(file_get_contents($target) === $defaultCode, 'Published file must equal the package defaults');
file_put_contents($target, "<?php\n\nreturn ['enable' => false];\n");
assertTrue(SettingsFile::ensurePublished($defaultPath, $target) === false, 'Existing file must not be republished');
assertTrue(str_contains((string)file_get_contents($target), "'enable' => false"), 'Existing file must not be overwritten');

// --- applyBooleans on the shipped defaults: values flip, comments and other keys survive.
$code = SettingsFile::applyBooleans($defaultCode, [
    'security.enable_write_tools' => true,
    'stream.enabled' => true,
    'enable' => false,
]);
$config = evalConfig($code);
assertTrue($config['security']['enable_write_tools'] === true, 'security.enable_write_tools must be true');
assertTrue($config['stream']['enabled'] === true, 'stream.enabled must be true');
assertTrue($config['enable'] === false, 'Top-level enable must be false');
assertTrue($config['rate_limit']['enabled'] === $defaults['rate_limit']['enabled'], 'Same-named key in another section must not change');
assertTrue($config['auth'] === $defaults['auth'], 'Untouched sections must stay identical');
assertTrue($config['domain'] === $defaults['domain'], 'Nested lists must stay identical');
assertTrue(str_contains($code, '// Extra scope a token needs to call evo.write.* tools.'), 'Comments must be preserved');
assertTrue(SettingsFile::applyBooleans($defaultCode, ['enable' => true]) === $defaultCode, 'Unchanged values must leave the file byte-identical');

// --- Keys the site file lacks are added to the closest existing section.
$trimmed = "<?php\n\nreturn [\n    // site overrides\n    'security' => [\n        'deny_tools' => [],\n    ],\n];\n";
$config = evalConfig(SettingsFile::applyBooleans($trimmed, [
    'security.enable_write_tools' => true,
    'stream.enabled' => false,
    'enable' => true,
]));
assertTrue($config['security']['enable_write_tools'] === true, 'Missing key must be inserted into its existing section');
assertTrue($config['security']['deny_tools'] === [], 'Existing keys in that section must survive');
assertTrue($config['stream'] === ['enabled' => false], 'Missing section must be created');
assertTrue($config['enable'] === true, 'Missing top-level key must be inserted');

// --- writeBooleans: saves to disk and the result parses.
SettingsFile::writeBooleans($target, ['security.enable_write_tools' => true, 'enable' => true]);
$config = include $target;
assertTrue($config['enable'] === true && $config['security']['enable_write_tools'] === true, 'writeBooleans must persist values');
assertTrue(glob($target . '.*.tmp') === [], 'writeBooleans must not leave temp files behind');

$broken = $dir . '/broken.php';
file_put_contents($broken, "<?php\nreturn 'not an array';\n");
$failed = false;
try {
    SettingsFile::writeBooleans($broken, ['enable' => true]);
} catch (RuntimeException) {
    $failed = true;
}
assertTrue($failed, 'A file without a returned array must be refused');

// --- SettingsCatalog: every switch is a boolean in the shipped defaults.
foreach (SettingsCatalog::fields() as $path => $key) {
    $value = $defaults;
    foreach (explode('.', $path) as $segment) {
        assertTrue(is_array($value) && array_key_exists($segment, $value), "Catalog setting [{$path}] missing from defaults");
        $value = $value[$segment];
    }
    assertTrue(is_bool($value), "Catalog setting [{$path}] must be boolean");
}

$values = SettingsCatalog::valuesFromInput(['enable_write_tools' => '1', 'audit' => 'on']);
assertTrue($values['security.enable_write_tools'] === true, 'Checked box must map to true');
assertTrue($values['logging.audit_enabled'] === true, '"on" must map to true');
assertTrue($values['enable'] === false, 'Unchecked box must map to false');
assertTrue(count($values) === count(SettingsCatalog::fields()), 'Every catalog setting must be written');

// --- Every locale translates the settings page.
foreach (glob(__DIR__ . '/../../lang/*/global.php') as $langFile) {
    $locale = basename(dirname($langFile));
    $lang = include $langFile;
    assertTrue(is_string($lang['menu_settings'] ?? null) && $lang['menu_settings'] !== '', "[{$locale}] menu_settings missing");
    foreach (['title', 'save', 'saved'] as $key) {
        assertTrue(($lang['settings'][$key] ?? '') !== '', "[{$locale}] settings.{$key} missing");
    }
    foreach (['intro', 'error_write'] as $key) {
        assertTrue(str_contains((string)($lang['settings'][$key] ?? ''), ':path'), "[{$locale}] settings.{$key} must contain :path");
    }
    foreach (SettingsCatalog::fields() as $key) {
        assertTrue(($lang['settings']['fields'][$key]['label'] ?? '') !== '', "[{$locale}] settings.fields.{$key}.label missing");
        assertTrue(($lang['settings']['fields'][$key]['hint'] ?? '') !== '', "[{$locale}] settings.fields.{$key}.hint missing");
    }
}

// --- forgetCachedConfig: drops Evo's configuration cache when the loader exists.
eval('namespace EvolutionCMS\Bootstrap; class EnvCacheLoader { public static array $calls = []; '
    . 'public static function invalidate(string $root): void { self::$calls[] = $root; } }');
define('EVO_BASE_PATH', '/site/');
SettingsFile::forgetCachedConfig();
assertTrue(\EvolutionCMS\Bootstrap\EnvCacheLoader::$calls === ['/site/'], 'forgetCachedConfig must invalidate the Evo config cache');

array_map('unlink', glob($dir . '/*.php') ?: []);
array_map('unlink', glob($dir . '/cms/settings/*') ?: []);
@rmdir($dir . '/cms/settings');
@rmdir($dir . '/cms');
@rmdir($dir);

echo "Settings file checks passed.\n";
