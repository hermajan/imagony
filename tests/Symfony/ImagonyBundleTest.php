<?php

declare(strict_types=1);

namespace Imagony\Tests\Symfony;

use Imagony\Commands\{CheckCommand, CleanCommand};
use Imagony\Diagnostics\Environment;
use Imagony\EventListener\PlaceholderListener;
use Imagony\ImagonyBundle;
use Imagony\Symfony\ImagonyExtension;
use Imagony\Thumbnails\Thumbnail;
use Imagony\Twig\ImagonyExtension as TwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\{ContainerBuilder, ContainerInterface};

class ImagonyBundleTest extends TestCase {
	/**
	 * Regression: the extension lives outside of the default `DependencyInjection` namespace,
	 * so without the override the `imagony:` config key has no extension.
	 */
	public function testBundleProvidesExtension(): void {
		$extension = (new ImagonyBundle())->getContainerExtension();

		$this->assertInstanceOf(ImagonyExtension::class, $extension);
		$this->assertSame("imagony", $extension->getAlias());
	}

	public function testBuiltInPlaceholderIsTheDefaultFallback(): void {
		$container = new ContainerBuilder();
		(new ImagonyExtension())->load([], $container);

		$this->assertNull($container->getParameter("imagony.thumbnails")["fallback"], "Null = the placeholder served from vendor, nothing to install.");
	}

	public function testPlaceholderIsServedByARequestListenerBeforeTheRouter(): void {
		$container = new ContainerBuilder();
		(new ImagonyExtension())->load([], $container);

		$definition = $container->getDefinition(PlaceholderListener::class);
		$tags = $definition->getTag("kernel.event_listener");
		$this->assertCount(1, $tags);
		$this->assertSame("kernel.request", $tags[0]["event"]);
		$this->assertGreaterThan(32, $tags[0]["priority"], "The router listener has priority 32.");
	}

	public function testExtensionLoadsParametersAndServices(): void {
		$container = new ContainerBuilder();
		(new ImagonyExtension())->load([["thumbnails" => ["folder" => "/tmp/thumbs/", "formats" => ["webp"]]]], $container);

		$parameter = $container->getParameter("imagony.thumbnails");
		$this->assertSame("/tmp/thumbs/", $parameter["folder"]);
		$this->assertSame(["webp"], $parameter["formats"]);
		$this->assertSame(["shrink_only"], $parameter["flags"]);

		$this->assertTrue($container->hasDefinition(Thumbnail::class));
		$arguments = $container->getDefinition(Thumbnail::class)->getArguments();
		$this->assertSame("%imagony.thumbnails%", $arguments['$parameters']);
		$this->assertSame("%kernel.debug%", $arguments['$debug']);
		$this->assertSame("logger", (string)$arguments['$logger']);
		$this->assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $arguments['$logger']->getInvalidBehavior(), "The logger is optional.");
		$this->assertTrue($container->getDefinition(Thumbnail::class)->isPublic());

		$this->assertTrue($container->hasDefinition(Environment::class));
		$this->assertTrue($container->hasDefinition(CheckCommand::class));
		$this->assertTrue($container->getDefinition(CheckCommand::class)->hasTag("console.command"));
		$this->assertTrue($container->hasDefinition(CleanCommand::class));
		$this->assertTrue($container->getDefinition(CleanCommand::class)->hasTag("console.command"));

		$this->assertTrue($container->hasDefinition(TwigExtension::class));
		$this->assertTrue($container->getDefinition(TwigExtension::class)->hasTag("twig.extension"));

		// Regression: services.yaml pointed at classes which had been moved
		foreach($container->getDefinitions() as $id => $definition) {
			if($id === "service_container") {
				continue;
			}
			$this->assertTrue(class_exists($definition->getClass() ?? $id), "Class of the service `$id` does not exist.");
		}
	}
}
