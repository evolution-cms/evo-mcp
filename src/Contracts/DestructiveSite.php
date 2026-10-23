<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * Marker for tools that delete or irreversibly overwrite site data.
 *
 * Always a write tool, so every WritesSite gate applies too. On top of those, eMCP refuses
 * such tools unless `security.enable_destructive_tools` is on and the token carries the
 * destructive scope (`mcp:destructive` by default), and hides them from `tools/list` while
 * they are disabled. Calls are marked in the audit log.
 *
 * A tool may add `public static function reversible(): bool` (true for soft deletes, trash,
 * anything a manager can undo); it is reported in the audit log and in the tool hints.
 * Without it the operation counts as irreversible.
 */
interface DestructiveSite extends WritesSite
{
}
