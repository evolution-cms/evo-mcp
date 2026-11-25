<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * A contributed tool whose definition (description, schemas, annotations) can change at runtime.
 *
 * eMCP notices Composer updates, published config changes and tools appearing or disappearing.
 * A definition that depends on anything else (database rows, settings, files of the extra) must
 * change this revision too, otherwise connected clients are not notified.
 *
 * Called on every eligible `tools/call`, so keep it cheap: a stored counter, a timestamp, a hash.
 * A new revision only triggers a comparison; clients are notified when the definitions differ.
 */
interface HasDefinitionRevision
{
    public function definitionRevision(): string;
}
