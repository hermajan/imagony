<?php

declare(strict_types=1);

namespace Imagony\Symfony;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ImagonyExtension extends Extension {
	public function load(array $configs, ContainerBuilder $container): void {
		$configuration = new Configuration();
		$config = $this->processConfiguration($configuration, $configs);

		$container->setParameter('imagony.thumbnails', $config['thumbnails']);

		$loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
		$loader->load('services.yaml');

		// the check command needs symfony/console, which is only suggested
		if(class_exists(Command::class)) {
			$loader->load('console.yaml');
		}
	}
}
