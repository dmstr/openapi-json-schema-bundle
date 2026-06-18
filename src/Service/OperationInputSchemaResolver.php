<?php
// file generated with AI assistance: Claude Code - 2026-06-06 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Service;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * Resolves an API Platform operation name to its JSON-Schema input file.
 *
 * Two resolution strategies, tried in order:
 *   1. Entity-local: operation `ref_project_scan` → split at last `_` to get
 *      verb `scan` and entity stem `ref_project` → StudlyCase `RefProject` →
 *      scan each registered entity root for `**\/RefProject/scan.input.json`.
 *   2. Path-convention (fallback): operation `ref_project_scan` →
 *      `<basePath>/ref-project-scan-input.json`.
 *
 * Single source of truth for two consumers:
 *   - {@see \App\OpenApi\OperationInputSchemaDecorator} injects the schema as
 *     the operation's OpenAPI `requestBody`.
 *   - {@see \App\Service\JobQueueService} validates incoming request bodies
 *     against the same schema before dispatching a job.
 */
final class OperationInputSchemaResolver implements InputSchemaResolverInterface
{
    /** @var array<string,?string> */
    private array $cache = [];

    /**
     * @param list<string>            $schemaBasePaths       Path-convention roots, searched in order; first hit wins.
     * @param list<array{0:string,1:string}> $entityResolutionRoots Tuples `[directory, namespacePrefix]`. Entity-local lookups are tried before the path-convention fallback.
     */
    public function __construct(
        private readonly array $schemaBasePaths,
        private readonly array $entityResolutionRoots = [],
    ) {
    }

    public function getSchemaFile(string $operationName): ?string
    {
        if ('' === $operationName) {
            return null;
        }
        if (\array_key_exists($operationName, $this->cache)) {
            return $this->cache[$operationName];
        }

        $found = $this->resolveFromEntityRoots($operationName)
            ?? $this->resolveFromBasePaths($operationName);

        return $this->cache[$operationName] = $found;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function loadSchema(string $operationName): ?array
    {
        $file = $this->getSchemaFile($operationName);
        if (null === $file) {
            return null;
        }
        $content = file_get_contents($file);
        if (false === $content) {
            return null;
        }
        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * {@inheritDoc}
     *
     * The static file resolver ignores $context — a schema file either exists
     * for the operation or it does not.
     */
    public function supports(string $operationName, array $context = []): bool
    {
        return null !== $this->getSchemaFile($operationName);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $operationName, array $context = []): ?array
    {
        return $this->loadSchema($operationName);
    }

    /**
     * A schema is treated as "required" for OpenAPI purposes when its top
     * level declares any required properties.
     *
     * @param array<string,mixed> $schema
     */
    public function isRequired(array $schema): bool
    {
        $required = $schema['required'] ?? null;

        return \is_array($required) && [] !== $required;
    }

    private function resolveFromEntityRoots(string $operationName): ?string
    {
        if ([] === $this->entityResolutionRoots) {
            return null;
        }
        $lastUnderscore = strrpos($operationName, '_');
        if (false === $lastUnderscore || 0 === $lastUnderscore) {
            return null;
        }
        $entityStem = substr($operationName, 0, $lastUnderscore);
        $verb = substr($operationName, $lastUnderscore + 1);
        if ('' === $verb) {
            return null;
        }
        $entityStudly = str_replace(' ', '', ucwords(str_replace('_', ' ', $entityStem)));
        $fileName = $verb.'.input.json';

        foreach ($this->entityResolutionRoots as $tuple) {
            $root = rtrim($tuple[0], '/');
            if (!is_dir($root)) {
                continue;
            }
            $candidate = $root.'/'.$entityStudly.'/'.$fileName;
            if (is_file($candidate)) {
                return $candidate;
            }
            foreach ($this->globEntityCandidates($root, $entityStudly, $fileName) as $match) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @return iterable<string>
     */
    private function globEntityCandidates(string $root, string $entityStudly, string $fileName): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $info) {
            if (!$info instanceof \SplFileInfo || !$info->isDir()) {
                continue;
            }
            if ($info->getFilename() !== $entityStudly) {
                continue;
            }
            $candidate = $info->getPathname().'/'.$fileName;
            if (is_file($candidate)) {
                yield $candidate;
            }
        }
    }

    private function resolveFromBasePaths(string $operationName): ?string
    {
        $fileName = str_replace('_', '-', $operationName).'-input.json';
        foreach ($this->schemaBasePaths as $base) {
            $path = rtrim($base, '/').'/'.$fileName;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
