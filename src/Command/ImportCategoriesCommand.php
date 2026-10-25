<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sets the Google product category of taxons from a JSON file `{"<taxon code>": "<category>", …}` (an export of
 * another platform, typically). Only empty categories are filled, unless `--overwrite`; `--dry-run` shows what
 * would change.
 */
#[AsCommand(name: 'altmenorg:google-shopping:import-categories', description: 'Set the Google product category of taxons from a JSON file')]
final class ImportCategoriesCommand extends Command
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'JSON object: taxon code => Google product category')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'replace categories already set')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'show the changes, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = json_decode((string) @file_get_contents((string) $input->getArgument('file')), true);
        if (!is_array($data)) {
            $output->writeln('<error>Unreadable JSON file.</error>');

            return Command::FAILURE;
        }
        $current = [];
        foreach ($this->db->fetchAllAssociative('SELECT code, google_product_category FROM sylius_taxon') as $r) {
            $current[(string) $r['code']] = $r['google_product_category'];
        }
        $set = $kept = $same = $unknown = 0;
        $dry = (bool) $input->getOption('dry-run');
        $this->db->beginTransaction();
        try {
            foreach ($data as $code => $category) {
                $code = (string) $code;
                $category = trim((string) $category);
                if (!array_key_exists($code, $current)) {
                    ++$unknown;
                    $output->writeln(sprintf('<comment>unknown taxon %s</comment>', $code));
                    continue;
                }
                $old = (string) ($current[$code] ?? '');
                if ($old === $category) {
                    ++$same;
                    continue;
                }
                if ($old !== '' && !$input->getOption('overwrite')) {
                    ++$kept;
                    $output->writeln(sprintf('%s: kept "%s" (file: "%s")', $code, $old, $category));
                    continue;
                }
                ++$set;
                $output->writeln(sprintf('%s: "%s" → "%s"', $code, $old, $category), OutputInterface::VERBOSITY_VERBOSE);
                if (!$dry) {
                    $this->db->executeStatement(
                        'UPDATE sylius_taxon SET google_product_category = :category WHERE code = :code',
                        ['category' => $category === '' ? null : $category, 'code' => $code],
                    );
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $output->writeln(sprintf('%s%d set, %d unchanged, %d kept (already set), %d unknown taxons.', $dry ? '[dry run] ' : '', $set, $same, $kept, $unknown));

        return Command::SUCCESS;
    }
}
