<?php
// file generated with AI assistance: Claude Code - 2026-06-17 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Service;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * Priority-ordered composite of {@see InputSchemaResolverInterface} members.
 *
 * The first member that {@see supports()} an operation resolves it. Members are
 * injected via the `dmstr_openapi_json_schema.input_resolver` tag; higher tag
 * priority is tried first. The default file resolver is registered at a low
 * priority so custom (e.g. dynamic, per-instance) resolvers can take precedence.
 */
final class ChainInputSchemaResolver implements InputSchemaResolverInterface
{
    /** @var list<InputSchemaResolverInterface> */
    private readonly array $resolvers;

    /**
     * @param iterable<InputSchemaResolverInterface> $resolvers priority-ordered
     */
    public function __construct(iterable $resolvers)
    {
        $this->resolvers = $resolvers instanceof \Traversable
            ? iterator_to_array($resolvers, false)
            : array_values($resolvers);
    }

    public function supports(string $operationName, array $context = []): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($operationName, $context)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(string $operationName, array $context = []): ?array
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($operationName, $context)) {
                return $resolver->resolve($operationName, $context);
            }
        }

        return null;
    }
}
