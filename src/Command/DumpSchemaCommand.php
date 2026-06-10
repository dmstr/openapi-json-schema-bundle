<?php
// file generated with AI assistance: Claude Code - 2025-11-02 00:00:00

declare(strict_types=1);

namespace Dmstr\OpenApiJsonSchema\Command;

use Dmstr\OpenApiJsonSchema\Service\SchemaRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:schema:dump',
    description: 'Dump the unified API configuration schema'
)]
class DumpSchemaCommand extends Command
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->schemaRegistry->getUnifiedSchema();

        $output->writeln(json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
