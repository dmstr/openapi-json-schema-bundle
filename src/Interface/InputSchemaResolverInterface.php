<?php
// file generated with AI assistance: Claude Code - 2026-06-17 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Interface;

/**
 * Resolves the JSON-Schema input for an API Platform operation.
 *
 * Resolvers are collected into a priority-ordered chain
 * ({@see \Dmstr\OpenApiJsonSchema\Service\ChainInputSchemaResolver}); the first
 * resolver that {@see supports()} an operation wins. The default
 * {@see \Dmstr\OpenApiJsonSchema\Service\OperationInputSchemaResolver} reads a
 * static schema file; custom resolvers may compute a schema dynamically (for
 * example per resource instance, passed through $context).
 */
interface InputSchemaResolverInterface
{
    /**
     * Whether this resolver can provide an input schema for the operation.
     *
     * @param array<string,mixed> $context optional resolution context (e.g. a
     *                                      resource id for per-instance schemas)
     */
    public function supports(string $operationName, array $context = []): bool;

    /**
     * Resolve the decoded JSON-Schema for the operation, or null when none.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public function resolve(string $operationName, array $context = []): ?array;
}
