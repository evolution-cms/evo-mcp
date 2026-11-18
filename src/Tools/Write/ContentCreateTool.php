<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\DocumentManager\Facades\DocumentManager;
use EvolutionCMS\Exceptions\ServiceActionException;
use EvolutionCMS\Exceptions\ServiceValidationException;
use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SiteTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.content.create')]
#[Description('Create a document under a parent. Requires new_document (and publish_document to create it published).')]
/** Create a document under an authorized parent and return its ID and state. */
class ContentCreateTool extends BaseWriteTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'parent' => ['required', 'integer', 'min:0'],
            'fields' => ['required', 'array'],
            'fields.pagetitle' => ['required', 'string'],
            'tvs' => ['nullable', 'array'],
        ]);

        $parent = (int)$validated['parent'];
        // validate() keeps only the keys that have nested rules (fields.pagetitle), so read the raw map.
        $fields = $this->pickFields((array)$request->get('fields', []), ContentUpdateTool::WRITABLE_FIELDS);
        unset($fields['parent']);
        $tvs = (array)($validated['tvs'] ?? []);

        $this->requirePermission('new_document');
        if (!empty($fields['published'])) {
            $this->requirePermission('publish_document');
        }

        if ($parent > 0) {
            $parentDocument = $this->findDocument($parent);
            $this->requireDocumentAccess($parent);
            $fields['template'] = $fields['template'] ?? $parentDocument->template;
        } else {
            $this->requireDocumentAccess(0, 0);
            $fields['template'] = $fields['template'] ?? (int)evo()->getConfig('default_template');
        }
        $fields['template'] = $this->existingTemplate((int)$fields['template']);

        $data = $fields + ['parent' => $parent] + $this->tvValues($tvs);

        try {
            $document = DocumentManager::create($data);
        } catch (ServiceValidationException $e) {
            $flat = [];
            foreach ($e->getValidationErrors() as $field => $messages) {
                $flat[(string)$field] = implode(' ', array_map('strval', (array)$messages));
            }
            throw ValidationException::withMessages($flat);
        } catch (ServiceActionException $e) {
            throw ValidationException::withMessages(['document' => $e->getMessage()]);
        }

        /** @var SiteContent $document */
        $this->logManagerAction(4, 'Created resource via MCP', (int)$document->getKey(), (string)$document->pagetitle);

        return $this->respondItem([
            'id' => (int)$document->getKey(),
            'parent' => (int)$document->parent,
            'pagetitle' => $document->pagetitle,
            'alias' => $document->alias,
            'published' => (bool)$document->published,
        ]);
    }

    /**
     * A minimal install can ship a `default_template` that points nowhere; a page with a
     * dangling template renders "Template missing", so fall back to the first real one.
     */
    private function existingTemplate(int $template): int
    {
        if ($template > 0 && SiteTemplate::query()->whereKey($template)->exists()) {
            return $template;
        }

        return (int)(SiteTemplate::query()->orderBy('id')->value('id') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $tvs
     * @return array<string, mixed>
     */
    private function tvValues(array $tvs): array
    {
        $values = [];
        foreach ($tvs as $name => $value) {
            $name = trim((string)$name);
            if (!preg_match('~^[A-Za-z0-9_\-]+$~', $name) || in_array($name, ContentUpdateTool::WRITABLE_FIELDS, true)) {
                throw ValidationException::withMessages(['tvs' => "Invalid TV name [{$name}]."]);
            }
            $values[$name] = is_array($value) ? implode('||', array_map('strval', $value)) : (string)$value;
        }

        return $values;
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'parent' => $schema->integer()->min(0)->required()->description('Parent document id, 0 for the site root'),
            'fields' => $schema->object([
                'pagetitle' => $schema->string()->required()->description('Title of the new document.'),
            ])->required()->description('Document columns. Select the parent with the top-level parent argument. Allowed: ' . implode(', ', ContentUpdateTool::WRITABLE_FIELDS)),
            'tvs' => $schema->object()->nullable()->description('TV values keyed by name for the chosen template.'),
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
        return ToolContract::item($schema, [
            'id' => $schema->integer()->required(),
            'parent' => $schema->integer()->required(),
            'pagetitle' => $schema->string()->required(),
            'alias' => $schema->string()->nullable()->required(),
            'published' => $schema->boolean()->required(),
        ], nullable: false);
    }
}
