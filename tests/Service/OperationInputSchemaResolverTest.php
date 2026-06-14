<?php
// file generated with AI assistance: Claude Code - 2026-06-13 23:14:54 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\Service;

use Dmstr\OpenApiJsonSchema\Service\OperationInputSchemaResolver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see OperationInputSchemaResolver}.
 *
 * Tests operate on a temporary directory tree to exercise both resolution
 * strategies (entity-local + path-convention fallback) without touching any
 * real project filesystem. (Moved here from the consuming application's test
 * suite — this is the canonical home for the bundle's own unit tests.)
 */
final class OperationInputSchemaResolverTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir().'/oapi-resolver-'.uniqid('', true);
        mkdir($this->tempRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    public function testEntityLocalResolutionHits(): void
    {
        $entityRoot = $this->tempRoot.'/entity';
        $this->writeFile($entityRoot.'/Refs/RefProject/scan.input.json', '{}');

        $resolver = new OperationInputSchemaResolver(
            schemaBasePaths: [],
            entityResolutionRoots: [[$entityRoot, 'App\\Entity']],
        );

        $resolved = $resolver->getSchemaFile('ref_project_scan');
        self::assertSame($entityRoot.'/Refs/RefProject/scan.input.json', $resolved);
    }

    public function testFallbackToPathConvention(): void
    {
        $baseRoot = $this->tempRoot.'/schemas';
        $this->writeFile($baseRoot.'/custom-op-input.json', '{}');

        $resolver = new OperationInputSchemaResolver(
            schemaBasePaths: [$baseRoot],
            entityResolutionRoots: [[$this->tempRoot.'/empty-entity', 'App\\Entity']],
        );

        $resolved = $resolver->getSchemaFile('custom_op');
        self::assertSame($baseRoot.'/custom-op-input.json', $resolved);
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        $resolver = new OperationInputSchemaResolver(
            schemaBasePaths: [$this->tempRoot.'/none'],
            entityResolutionRoots: [[$this->tempRoot.'/also-none', 'App\\Entity']],
        );

        self::assertNull($resolver->getSchemaFile('nothing_to_find'));
    }

    public function testEntityLocalTakesPrecedenceOverPathConvention(): void
    {
        $entityRoot = $this->tempRoot.'/entity';
        $baseRoot = $this->tempRoot.'/schemas';
        $this->writeFile($entityRoot.'/Refs/RefProject/scan.input.json', '{"src":"entity"}');
        $this->writeFile($baseRoot.'/ref-project-scan-input.json', '{"src":"base"}');

        $resolver = new OperationInputSchemaResolver(
            schemaBasePaths: [$baseRoot],
            entityResolutionRoots: [[$entityRoot, 'App\\Entity']],
        );

        $resolved = $resolver->getSchemaFile('ref_project_scan');
        self::assertSame($entityRoot.'/Refs/RefProject/scan.input.json', $resolved);
    }

    public function testCachesLookupResultAcrossCalls(): void
    {
        $entityRoot = $this->tempRoot.'/entity';
        $this->writeFile($entityRoot.'/Refs/RefProject/scan.input.json', '{}');

        $resolver = new OperationInputSchemaResolver(
            schemaBasePaths: [],
            entityResolutionRoots: [[$entityRoot, 'App\\Entity']],
        );

        $first = $resolver->getSchemaFile('ref_project_scan');
        unlink($entityRoot.'/Refs/RefProject/scan.input.json');
        $second = $resolver->getSchemaFile('ref_project_scan');

        self::assertSame($first, $second, 'Cached lookup must survive filesystem changes within a single resolver lifetime.');
    }

    public function testEmptyOperationNameReturnsNull(): void
    {
        $resolver = new OperationInputSchemaResolver(schemaBasePaths: [], entityResolutionRoots: []);
        self::assertNull($resolver->getSchemaFile(''));
    }

    public function testLoadSchemaReturnsDecodedArray(): void
    {
        $entityRoot = $this->tempRoot.'/entity';
        $this->writeFile($entityRoot.'/Refs/RefTodo/sync.input.json', '{"type":"object","required":["id"]}');

        $resolver = new OperationInputSchemaResolver(
            schemaBasePaths: [],
            entityResolutionRoots: [[$entityRoot, 'App\\Entity']],
        );

        $schema = $resolver->loadSchema('ref_todo_sync');
        self::assertNotNull($schema);
        self::assertSame('object', $schema['type']);
        self::assertTrue($resolver->isRequired($schema));
    }

    public function testIsRequiredFalseForEmptyOrMissingRequired(): void
    {
        $resolver = new OperationInputSchemaResolver(schemaBasePaths: [], entityResolutionRoots: []);

        self::assertFalse($resolver->isRequired([]));
        self::assertFalse($resolver->isRequired(['required' => []]));
    }

    private function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            self::fail('Failed to create '.$dir);
        }
        file_put_contents($path, $contents);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
