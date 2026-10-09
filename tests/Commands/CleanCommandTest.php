<?php

declare(strict_types=1);

namespace Imagony\Tests\Commands;

use Imagony\Commands\CleanCommand;
use Imagony\Tests\ImageTestCase;
use Imagony\Thumbnails\Thumbnail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CleanCommandTest extends ImageTestCase {
	private string $base;

	private string $folder;

	private string $columns;

	private Thumbnail $thumbnail;

	protected function setUp(): void {
		parent::setUp();

		// the output is wrapped to the width of the terminal, long paths would be split
		$this->columns = (string)getenv("COLUMNS");
		putenv("COLUMNS=500");

		$this->base = $this->dir."/public/";
		$this->folder = $this->base."thumbnails/";
		$this->configure($this->folder);

		mkdir($this->folder."small", 0755, true);
		file_put_contents($this->folder."a.png", "a");
		file_put_contents($this->folder."a.png.webp", "a");
		file_put_contents($this->folder."small/b.png", "b");
		file_put_contents($this->folder.".gitkeep", "");
		$this->saveImage($this->base."uploads/source.png", 10, 10);
	}

	protected function tearDown(): void {
		putenv($this->columns === "" ? "COLUMNS" : "COLUMNS=".$this->columns);
		parent::tearDown();
	}

	private function configure(string $folder): void {
		$this->thumbnail = new Thumbnail([
			"base" => $this->base,
			"folder" => $folder,
			"fallback" => $this->base."no-image.png",
			"flags" => ["shrink_only"],
			"formats" => [],
			"templates" => [],
		]);
	}

	private function runClean(array $input = [], array $options = []): CommandTester {
		$tester = new CommandTester(new CleanCommand($this->thumbnail));
		$tester->execute($input, $options + ["decorated" => false]);

		return $tester;
	}

	public function testForceRemovesThumbnailsRecursively(): void {
		$tester = $this->runClean(["--force" => true]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("Removed 3 thumbnails", $tester->getDisplay());
		$this->assertFileDoesNotExist($this->folder."a.png");
		$this->assertFileDoesNotExist($this->folder."a.png.webp");
		$this->assertFileDoesNotExist($this->folder."small/b.png");
	}

	public function testKeepsDotFilesFoldersAndSourceImages(): void {
		$this->runClean(["--force" => true]);

		$this->assertFileExists($this->folder.".gitkeep");
		$this->assertDirectoryExists($this->folder."small");
		$this->assertFileExists($this->base."uploads/source.png");
	}

	public function testDryRunListsAndRemovesNothing(): void {
		$tester = $this->runClean(["--dry-run" => true]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString($this->folder."a.png", $tester->getDisplay());
		$this->assertStringContainsString($this->folder."small/b.png", $tester->getDisplay());
		$this->assertStringContainsString("3 thumbnails would be removed", $tester->getDisplay());
		$this->assertFileExists($this->folder."a.png");
		$this->assertFileExists($this->folder."small/b.png");
	}

	public function testWithoutInteractionNothingIsRemovedWithoutForce(): void {
		$tester = $this->runClean([], ["interactive" => false]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("Nothing was removed", $tester->getDisplay());
		$this->assertFileExists($this->folder."a.png");
	}

	public function testConfirmationRemovesThumbnails(): void {
		$tester = new CommandTester(new CleanCommand($this->thumbnail));
		$tester->setInputs(["yes"]);
		$tester->execute([], ["decorated" => false]);

		$this->assertStringContainsString("Remove 3 thumbnails from", $tester->getDisplay());
		$this->assertStringContainsString("Removed 3 thumbnails", $tester->getDisplay());
		$this->assertFileDoesNotExist($this->folder."a.png");
	}

	public function testDecliningKeepsThumbnails(): void {
		$tester = new CommandTester(new CleanCommand($this->thumbnail));
		$tester->setInputs(["no"]);
		$tester->execute([], ["decorated" => false]);

		$this->assertStringContainsString("Nothing was removed", $tester->getDisplay());
		$this->assertFileExists($this->folder."a.png");
		$this->assertFileExists($this->folder."small/b.png");
	}

	public function testVerboseListsRemovedFiles(): void {
		$tester = $this->runClean(["--force" => true], ["verbosity" => OutputInterface::VERBOSITY_VERBOSE]);

		$this->assertStringContainsString($this->folder."small/b.png", $tester->getDisplay());
	}

	public function testNothingToClean(): void {
		$this->runClean(["--force" => true]);

		$tester = $this->runClean(["--force" => true]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("There are no thumbnails in", $tester->getDisplay());
	}

	public function testMissingFolderIsNotAnError(): void {
		$this->configure($this->base."never-created/");

		$tester = $this->runClean(["--force" => true]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("There are no thumbnails in", $tester->getDisplay());
	}

	public function testRefusesToCleanTheFolderWithSourceImages(): void {
		$this->configure($this->base);

		$tester = $this->runClean(["--force" => true]);

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString("Refusing to clean", $tester->getDisplay());
		$this->assertStringContainsString("imagony.thumbnails.folder", $tester->getDisplay());
		$this->assertFileExists($this->base."uploads/source.png");
		$this->assertFileExists($this->folder."a.png");
	}

	public function testRefusesToCleanAParentOfTheBaseFolder(): void {
		$this->configure($this->dir."/");

		$tester = $this->runClean(["--force" => true]);

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertFileExists($this->base."uploads/source.png");
	}
}
