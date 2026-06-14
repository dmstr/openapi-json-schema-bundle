<?php
// file generated with AI assistance: Claude Code - 2026-06-13 23:14:54 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\Attribute;

use Dmstr\OpenApiJsonSchema\Attribute\JsonSchema;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the {@see JsonSchema} property attribute.
 */
final class JsonSchemaTest extends TestCase
{
    public function testSchemaNameDefaultsToNull(): void
    {
        self::assertNull((new JsonSchema())->schemaName);
    }

    public function testSchemaNameIsStored(): void
    {
        self::assertSame('metadata', (new JsonSchema('metadata'))->schemaName);
    }

    public function testReadableAsPropertyAttributeViaReflection(): void
    {
        $fixture = new class {
            #[JsonSchema(schemaName: 'config')]
            public array $configJson = [];
        };

        $property = new \ReflectionProperty($fixture, 'configJson');
        $attributes = $property->getAttributes(JsonSchema::class);

        self::assertCount(1, $attributes);
        self::assertSame('config', $attributes[0]->newInstance()->schemaName);
    }
}
