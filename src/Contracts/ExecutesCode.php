<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * Marker for tools that store code the server executes: PHP (snippets, plugins, modules, an
 * executable file on disk), an @EVAL / @SELECT / @INCLUDE binding, SQL.
 *
 * Always a write tool, so every WritesSite gate applies too. On top of those, eMCP refuses such
 * tools unless `security.enable_code_tools` is on, the token carries the code scope (`mcp:code`
 * by default) and the token owner holds a permission that already grants backend code in the
 * manager (save_snippet, save_plugin or save_module, as Evo's BackendCodePermissions defines it).
 * While disabled or not permitted they are hidden from `tools/list`. Calls are marked in the
 * audit log.
 *
 * Mark the whole tool even if only some calls write code (a file manager that writes any
 * extension): the gate cannot see inside a contributed tool's arguments.
 */
interface ExecutesCode extends WritesSite
{
}
