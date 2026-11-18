<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Content;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Contracts\ToolArguments\ContentGetArgs;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.content.get')]
#[Description('Read one visible document by ID, including its body, introtext, menutitle and requested TVs. Returns item null when the document is missing or inaccessible. Use evo.content.search when the ID is unknown.')]
/** Return a single document with detail fields and optional TV values. */
class ContentGetTool extends BaseContentTool
{
    /**
     * A single document is read to be worked on, so unlike list tools it carries the body fields.
     *
     * @var array<int, string>
     */
    private const DETAIL_FIELDS = ['menutitle', 'introtext', 'content', 'link_attributes', 'searchable', 'cacheable', 'richtext', 'contentType', 'editedby', 'createdby', 'publishedon'];

    /**
     * @return array<string, mixed>
     */
    protected function validateStage(Request $request): array
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'with_tvs' => ['nullable', 'array'],
            'with_tvs.*' => ['string'],
        ]);

        $withTvs = $this->normalizeWithTvs($validated['with_tvs'] ?? null);

        return [
            'args' => new ContentGetArgs(
                (int)$validated['id'],
                $withTvs['entries'],
                $withTvs['names']
            ),
        ];
    }

    protected function queryStage(array $validated): mixed
    {
        /** @var ContentGetArgs $args */
        $args = $validated['args'];

        $query = SiteContent::query()
            ->select('site_content.*')
            ->where('id', $args->id)
            ->where('deleted', 0);

        $this->applyManagerAccess($query);

        if ($args->withTvEntries !== []) {
            $query->withTVs($args->withTvEntries);
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapStage(mixed $queryResult, array $validated): array
    {
        /** @var ContentGetArgs $args */
        $args = $validated['args'];

        /** @var SiteContent|null $item */
        $item = $queryResult instanceof SiteContent ? $queryResult : null;

        return [
            'item' => $item
                ? $this->contentMapper()->map($item, array_merge(self::PUBLIC_FIELDS, self::DETAIL_FIELDS), $args->withTvNames)
                : null,
        ];
    }

    protected function respondStage(array $mapped, array $validated): ResponseFactory
    {
        $item = $mapped['item'] ?? null;

        return $this->respondItem(is_array($item) ? $item : null);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('ID of the document to read.'),
            'with_tvs' => $this->tvSchema($schema),
        ];
    }

    /**
     * Advertise the structured envelope required by an MCP client.
     *
     * @return array<string,mixed>
     * @since 0.3.0
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return ToolContract::item($schema);
    }
}
