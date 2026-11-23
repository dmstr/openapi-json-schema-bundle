<?php
// file generated with AI assistance: Claude Code - 2026-11-23 22:48:31 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Service;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validates the input of an operation against the JSON-Schema the
 * {@see InputSchemaResolverInterface} chain resolves for it.
 *
 * The same schema documents the OpenAPI request body
 * ({@see \Dmstr\OpenApiJsonSchema\OpenApi\OperationInputSchemaDecorator}), so a
 * REST processor and its CLI mirror that both call this service enforce the
 * input contract the API publishes. Defaults declared in the schema are not
 * applied; callers fall back to their own defaults.
 *
 * Errors are HTTP exceptions (400 malformed JSON, 422 schema violation), which
 * API Platform turns into problem responses; a console command reports their
 * message.
 */
final class InputSchemaValidator
{
    public function __construct(
        private readonly InputSchemaResolverInterface $resolver,
    ) {
    }

    /**
     * Decode a raw JSON request body and validate it. An empty body counts as
     * an empty object.
     *
     * @return array<string,mixed> the decoded, valid input
     */
    public function validateRaw(string $operationName, string $rawJson): array
    {
        $rawJson = trim($rawJson);
        if ($rawJson === '') {
            $rawJson = '{}';
        }

        try {
            $payload = json_decode($rawJson, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON: '.$e->getMessage(), $e);
        }
        if (!$payload instanceof \stdClass) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        $this->validatePayload($operationName, $payload);

        return json_decode($rawJson, true);
    }

    /**
     * Validate already decoded input, e.g. the options of a console command.
     *
     * @param array<string,mixed> $data
     */
    public function validate(string $operationName, array $data): void
    {
        // json_encode([]) is a JSON list; the input of an operation is an object
        $payload = $data === []
            ? new \stdClass()
            : json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        $this->validatePayload($operationName, $payload);
    }

    private function validatePayload(string $operationName, mixed $payload): void
    {
        $schema = $this->resolver->resolve($operationName);
        if ($schema === null) {
            throw new \LogicException(sprintf('No input schema for operation "%s"', $operationName));
        }

        $result = (new Validator())->validate(
            $payload,
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR)
        );
        if (!$result->isValid()) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Input validation failed for operation "%s": %s',
                $operationName,
                json_encode((new ErrorFormatter())->format($result->error()), JSON_UNESCAPED_SLASHES)
            ));
        }
    }
}
