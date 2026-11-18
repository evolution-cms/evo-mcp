<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts\ToolResponses;

/** Structured MCP list response shared by paged queries and bounded paths. */
final readonly class ListToolResponse
{
    /**
     * Carry the visible records and applied bounds for this result.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  bool|null  $truncated  Whether a bounded path was shortened, or null for paged lists.
     */
    public function __construct(
        public array $items,
        public int $limit,
        public int $offset,
        public int $count,
        public string $toolsetVersion,
        public ?string $model = null,
        public ?bool $truncated = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $meta = [
            'limit' => $this->limit,
            'offset' => $this->offset,
            'count' => $this->count,
            'toolsetVersion' => $this->toolsetVersion,
        ];

        if ($this->model !== null) {
            $meta['model'] = $this->model;
        }

        if ($this->truncated !== null) {
            $meta['truncated'] = $this->truncated;
        }

        return [
            'items' => $this->items,
            'meta' => $meta,
        ];
    }
}
