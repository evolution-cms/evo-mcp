<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * A contributed tool that only managers with a given Evo permission may see and call.
 *
 * eMCP checks the permission for the impersonated token owner before the tool runs, on every
 * transport, and hides the tool from `tools/list` for users who lack it. Without a manager
 * identity (auth.mode=none, CLI) such tools are unavailable. Admins (role 1) always pass.
 *
 * The tool should still check what it touches (document access, ownership): this is the
 * entry gate, not a replacement for those checks.
 */
interface RequiresPermission
{
    /**
     * One permission key, or a list where any one of them is enough.
     *
     * @return string|array<int, string>
     */
    public static function permission(): string|array;
}
