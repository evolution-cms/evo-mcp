<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Describe the common structured envelopes advertised by the versioned Evo toolset.
 *
 * @since 0.3.0
 */
final class ToolContract
{
    public const VERSION = '2.0';

    /**
     * Describe a page whose items have tool-specific or model-dependent fields.
     *
     * @param array<string,mixed> $extraMeta
     * @return array<string,mixed>
     * @since 0.3.0
     */
    public static function listing(JsonSchema $schema, array $extraMeta = []): array
    {
        return [
            'items' => $schema->array()->items($schema->object())->required()
                ->description('Returned records in the order defined by the tool. Empty when there are no matches.'),
            'meta' => $schema->object([
                'limit' => $schema->integer()->min(1)->required()->description('Result limit applied by the tool.'),
                'offset' => $schema->integer()->min(0)->required()->description('Number of matching records skipped.'),
                'count' => $schema->integer()->min(0)->required()->description('Number of returned records in this response.'),
                'toolsetVersion' => $schema->string()->enum([self::VERSION])->required()->description('Version of the Evo tool contract.'),
            ] + $extraMeta)->required()->withoutAdditionalProperties(),
        ];
    }

    /**
     * Describe a single record or a successful write receipt.
     *
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $extraMeta
     * @return array<string,mixed>
     * @since 0.3.0
     */
    public static function item(JsonSchema $schema, array $fields = [], array $extraMeta = [], bool $nullable = true): array
    {
        return [
            'item' => $schema->object($fields)->required()->nullable($nullable)
                ->description($nullable ? 'The matching record, or null when it was not found.' : 'The successful write result.'),
            'meta' => $schema->object([
                'toolsetVersion' => $schema->string()->enum([self::VERSION])->required()->description('Version of the Evo tool contract.'),
            ] + $extraMeta)->required()->withoutAdditionalProperties(),
        ];
    }
}
