<?php
// file generated with AI assistance: Claude Code - 2026-11-23 22:48:50 UTC

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Tests\Service;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Dmstr\OpenApiJsonSchema\Service\InputSchemaValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InputSchemaValidatorTest extends TestCase
{
    private InputSchemaValidator $validator;

    protected function setUp(): void
    {
        $resolver = new class implements InputSchemaResolverInterface {
            public function supports(string $operationName, array $context = []): bool
            {
                return $operationName === 'thing_prune';
            }

            public function resolve(string $operationName, array $context = []): ?array
            {
                return $this->supports($operationName) ? [
                    'type' => 'object',
                    'properties' => ['days' => ['type' => 'integer', 'minimum' => 1]],
                    'additionalProperties' => false,
                ] : null;
            }
        };
        $this->validator = new InputSchemaValidator($resolver);
    }

    public function testValidRawBodyIsDecoded(): void
    {
        self::assertSame(['days' => 7], $this->validator->validateRaw('thing_prune', '{"days": 7}'));
    }

    public function testEmptyBodyCountsAsEmptyObject(): void
    {
        self::assertSame([], $this->validator->validateRaw('thing_prune', ''));
        self::assertSame([], $this->validator->validateRaw('thing_prune', '{}'));
    }

    public function testSchemaViolationIs422(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->validator->validateRaw('thing_prune', '{"days": 0}');
    }

    public function testUnknownPropertyIs422(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->validator->validate('thing_prune', ['days' => 3, 'all' => true]);
    }

    public function testMalformedJsonIs400(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->validator->validateRaw('thing_prune', '{days');
    }

    public function testJsonListIs400(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->validator->validateRaw('thing_prune', '[1]');
    }

    public function testDecodedInputIsValidated(): void
    {
        $this->validator->validate('thing_prune', []);
        $this->validator->validate('thing_prune', ['days' => 30]);
        $this->addToAssertionCount(2);
    }

    public function testOperationWithoutSchemaIsALogicError(): void
    {
        $this->expectException(\LogicException::class);
        $this->validator->validate('other_op', []);
    }
}
