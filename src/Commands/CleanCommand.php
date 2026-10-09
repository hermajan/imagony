<?php

declare(strict_types=1);

namespace Imagony\Commands;

use Imagony\Thumbnails\Thumbnail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes generated thumbnails (and their WebP/AVIF siblings). They are generated again when they are needed.
 * Source images, folders and dot files (e.g. `.gitkeep`) are kept.
 */
#[AsCommand(name: "imagony:clean", description: "Removes generated thumbnails")]
class CleanCommand extends Command {
	/**
	 * @param Thumbnail $thumbnail Configures the thumbnails, its methods are static.
	 */
	public function __construct(private readonly Thumbnail $thumbnail) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addOption("dry-run", null, InputOption::VALUE_NONE, "Only lists the thumbnails which would be removed")
			->addOption("force", "f", InputOption::VALUE_NONE, "Does not ask for confirmation (required without interaction, e.g. in scripts)")
			->setHelp(<<<'TEXT'
The command removes files from `imagony.thumbnails.folder`, including the subfolders of templates.
It asks for confirmation, use <info>--force</info> in scripts and <info>--dry-run</info> to see what would be removed.
TEXT
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$folder = (string)(Thumbnail::getParameters()["folder"] ?? "");

		try {
			$found = Thumbnail::clean(true);
		} catch(\LogicException $e) {
			$io->error($e->getMessage());
			return Command::FAILURE;
		}

		if($found === []) {
			$io->success("There are no thumbnails in `{$folder}`.");
			return Command::SUCCESS;
		}

		if($input->getOption("dry-run")) {
			$io->listing($found);
			$io->note(sprintf("%d thumbnails would be removed from `%s`.", count($found), $folder));
			return Command::SUCCESS;
		}

		if(!$input->getOption("force") and !$io->confirm(sprintf("Remove %d thumbnails from `%s`?", count($found), $folder), false)) {
			$io->warning("Nothing was removed. Use --force to skip the confirmation.");
			return Command::SUCCESS;
		}

		$removed = Thumbnail::clean();
		if($output->isVerbose()) {
			$io->listing($removed);
		}

		if(count($removed) < count($found)) {
			$io->error(sprintf("%d of %d thumbnails could not be removed, check the permissions of `%s`.", count($found) - count($removed), count($found), $folder));
			return Command::FAILURE;
		}

		$io->success(sprintf("Removed %d thumbnails from `%s`. They are generated again when needed.", count($removed), $folder));

		return Command::SUCCESS;
	}
}
