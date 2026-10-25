<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Command;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContextFactory;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedGenerator;
use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolver;
use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes the feed file. Read-only on the shop; the file is written atomically (temporary file in the same
 * directory, then rename), so that Merchant Center never fetches a half-written feed.
 */
#[AsCommand(name: 'altmenorg:google-shopping:generate', description: 'Write the Google Shopping product feed')]
final class GenerateFeedCommand extends Command
{
    public function __construct(
        private readonly FeedGenerator $generator,
        private readonly FeedContextFactory $contexts,
        private readonly ImageUrlResolverInterface $images,
        private readonly string $defaultOutput,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'file to write (relative to the project directory)', $this->defaultOutput)
            ->addOption('images', null, InputOption::VALUE_REQUIRED, 'image profile (images.profiles setting)')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'channel code')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'locale code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'country of the shipping costs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $start = microtime(true);
        $profile = $input->getOption('images');
        if ($profile !== null && $this->images instanceof ImageUrlResolver && !in_array($profile, $this->images->profileNames(), true)) {
            $output->writeln(sprintf('<error>--images: unknown profile "%s" (%s)</error>', $profile, implode(', ', $this->images->profileNames()) ?: 'none set'));

            return Command::INVALID;
        }
        try {
            $context = $this->contexts->create([
                'channel' => $input->getOption('channel'),
                'locale' => $input->getOption('locale'),
                'country' => $input->getOption('country'),
                'images' => $profile,
            ]);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::INVALID;
        }

        $feed = $this->generator->generate($context);

        $target = (string) $input->getOption('output');
        if (!str_starts_with($target, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $target)) {
            $target = $this->projectDir . '/' . $target;
        }
        $dir = \dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $output->writeln('<error>Cannot create directory ' . $dir . '</error>');

            return Command::FAILURE;
        }
        $tmp = @tempnam($dir, '.' . basename($target) . '.');
        if ($tmp === false || file_put_contents($tmp, $feed->xml) !== strlen($feed->xml)) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            $output->writeln('<error>Cannot write in ' . $dir . '</error>');

            return Command::FAILURE;
        }
        @chmod($tmp, 0644);
        if (!rename($tmp, $target)) {
            @unlink($tmp);
            $output->writeln('<error>Cannot rename to ' . $target . '</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('%s: %d items (%d products), %d bytes, %.1f s.', $target, $feed->items, $feed->products, strlen($feed->xml), microtime(true) - $start));

        return Command::SUCCESS;
    }
}
