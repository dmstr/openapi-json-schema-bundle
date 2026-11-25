<?php
// file generated with AI assistance: Claude Code - 2026-11-25 23:07:13 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\Fixtures;

use Dmstr\OpenApiJsonSchema\Attribute\JsonSchema;

/**
 * Entity fixture for {@see \Dmstr\OpenApiJsonSchema\OpenApi\JsonFieldSchemaDecorator} tests.
 *
 * A named class is required because the decorator derives the schema file name
 * from the short class name ("jsonfieldentity-{schemaName}.json").
 */
final class JsonFieldEntity
{
    /** Explicit schema name, resolved to an object schema file. */
    #[JsonSchema(schemaName: 'metadata')]
    public array $metadataJson = [];

    /** Schema name derived from the property name ("config"), resolved to an anyOf-only schema file. */
    #[JsonSchema]
    public array $configJson = [];

    /** Property without the attribute. */
    public string $title = '';
}
