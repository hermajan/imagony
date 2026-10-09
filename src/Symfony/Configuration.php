<?php

declare(strict_types=1);

namespace Imagony\Symfony;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The only place with defaults of the bundle. No config file is needed, `bin/console config:dump-reference imagony` prints this tree.
 */
class Configuration implements ConfigurationInterface {
	public function getConfigTreeBuilder(): TreeBuilder {
		$treeBuilder = new TreeBuilder('imagony');
		$rootNode = $treeBuilder->getRootNode();

		$rootNode
			->children()
				->arrayNode('thumbnails')
					->addDefaultsIfNotSet()
					->children()
						->scalarNode('base')
							->info('Base folder with images. Template paths are relative to it.')
							->defaultValue('%kernel.project_dir%/public/')
						->end()
						->scalarNode('folder')
							->info('Folder where thumbnails are generated. `Thumbnail::clean()` removes files from it.')
							->defaultValue('%kernel.project_dir%/public/thumbnails/')
						->end()
						->scalarNode('fallback')
							->info('Image used when the source image is missing or can not be processed. Null = the built-in placeholder, served by the bundle from its own folder as `/_imagony/no-image.png`. An own image has to be inside `base`, its URL is the path relative to `base`.')
							->defaultNull()
						->end()
						->booleanNode('strict')
							->info('Throw an exception when a configured format can not be written by the installed PHP extensions (see `bin/console imagony:check`). Otherwise it is only logged. Null = enabled in debug mode only.')
							->defaultNull()
						->end()
						->arrayNode('flags')
							->info('Default flags for resizing: fit, shrink_only, stretch, fill, exact. Flags of a template replace them.')
							->example(['shrink_only'])
							->prototype('scalar')->end()
							->defaultValue(['shrink_only'])
						->end()
						->arrayNode('formats')
							->info('Formats to which every thumbnail is also converted (<file>.webp, <file>.avif): webp, avif. Unsupported by the PHP image extension are skipped.')
							->example(['webp'])
							->prototype('scalar')->end()
							->defaultValue([])
						->end()
						->arrayNode('templates')
							->info('Named sizes used as `thumbnail("name", "image.jpg")`.')
							->example(['blog' => ['path' => 'images/blog/', 'height' => 250, 'quality' => 80]])
							->useAttributeAsKey('name')
							->arrayPrototype()
								->children()
									->scalarNode('path')->isRequired()->info('Folder with source images, relative to `base`.')->end()
									->integerNode('width')->defaultNull()->info('Missing width or height is calculated from the aspect ratio.')->end()
									->integerNode('height')->defaultNull()->end()
									->arrayNode('flags')->info('Flags for resizing, they replace the default ones.')->prototype('scalar')->end()->end()
									->integerNode('quality')->defaultNull()->info('0-100, the driver default when empty.')->end()
								->end()
							->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
