<?php

declare(strict_types=1);

namespace Imagony\Commands;

use Imagony\Converters\Format;
use Imagony\Diagnostics\Environment;
use Imagony\Placeholder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks that PHP and the configuration are able to produce thumbnails and tells what to fix.
 * Run it after installation, on deploy or in a Docker build. The exit code is 1 when thumbnails can not work as configured.
 */
#[AsCommand(name: "imagony:check", description: "Checks PHP image extensions and the configuration of Imagony")]
class CheckCommand extends Command {
	/**
	 * @param array $parameters Configuration `imagony.thumbnails`.
	 */
	public function __construct(private readonly Environment $environment, private readonly array $parameters) {
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$errors = [];
		$warnings = [];

		$io->title("Imagony");

		$io->section("PHP image extensions");
		$rows = [];
		foreach($this->environment->drivers() as $driver) {
			$rows[] = [
				$driver->name,
				$driver->available ? "yes" : "no",
				$driver->version ?? "-",
				$driver->available ? ($driver->supports(Format::WEBP) ? "yes" : "no") : "-",
				$driver->available ? ($driver->supports(Format::AVIF) ? "yes" : "no") : "-",
			];
		}
		$io->table(["Driver", "Installed", "Version", "WebP", "AVIF"], $rows);

		if($this->environment->firstAvailable() === null) {
			$errors[] = $this->environment->explain();
		}

		$io->section("Configuration");
		$formats = $this->parameters["formats"] ?? [];
		foreach($formats as $configured) {
			try {
				$format = Format::fromConfig($configured);
			} catch(\InvalidArgumentException $e) {
				$errors[] = "`imagony.thumbnails.formats`: ".$e->getMessage();
				continue;
			}

			if($this->environment->firstAvailable() !== null and $this->environment->firstSupporting($format) === null) {
				$errors[] = "`imagony.thumbnails.formats` contains ".strtoupper($format->value).". ".$this->environment->explain($format);
			}
		}

		$base = (string)($this->parameters["base"] ?? "");
		if(!is_dir($base)) {
			$errors[] = "`imagony.thumbnails.base` does not exist: `{$base}`.";
		}

		$folder = (string)($this->parameters["folder"] ?? "");
		if(is_dir($folder)) {
			if(!is_writable($folder)) {
				$errors[] = "`imagony.thumbnails.folder` is not writable: `{$folder}`.";
			}
		} else {
			$parent = dirname(rtrim($folder, "/\\"));
			while(!is_dir($parent) and $parent !== dirname($parent)) {
				$parent = dirname($parent);
			}
			if(!is_writable($parent)) {
				$errors[] = "`imagony.thumbnails.folder` `{$folder}` does not exist and can not be created, `{$parent}` is not writable.";
			}
		}

		$fallback = $this->parameters["fallback"] ?? null;
		if($fallback === null) {
			if(Placeholder::resolve(Placeholder::URL) === null) {
				$errors[] = "The built-in placeholder image `".Placeholder::file()."` is missing, reinstall the package or set `imagony.thumbnails.fallback`.";
			}
		} elseif(!is_file($fallback)) {
			$warnings[] = "`imagony.thumbnails.fallback` does not exist: `{$fallback}`. It is returned when an image is missing or can not be processed. Create the file or remove the option to use the built-in placeholder.";
		} elseif(($realBase = realpath($base)) !== false and !str_starts_with((string)realpath($fallback), rtrim($realBase, "/\\").DIRECTORY_SEPARATOR)) {
			$warnings[] = "`imagony.thumbnails.fallback` `{$fallback}` is not inside `imagony.thumbnails.base` `{$base}`, so no URL can be built for it. Move it into `base`.";
		}

		foreach($this->parameters["templates"] ?? [] as $name => $template) {
			$path = $base.($template["path"] ?? "");
			if(!is_dir($path)) {
				$warnings[] = "Template `{$name}`: the folder `{$path}` does not exist.";
			}
		}

		foreach($warnings as $warning) {
			$io->warning($warning);
		}
		foreach($errors as $error) {
			$io->error($error);
		}

		if($errors !== []) {
			return Command::FAILURE;
		}

		$io->success($warnings === [] ? "Thumbnails can be generated as configured." : "Thumbnails can be generated, see the warnings.");

		return Command::SUCCESS;
	}
}
