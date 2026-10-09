<?php

declare(strict_types=1);

namespace Imagony\EventListener;

use Imagony\Placeholder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Serves the built-in placeholder image (and its WebP/AVIF variants) from the bundle, without a route and without `assets:install`.
 * It answers before the router, only for the few URLs of {@see Placeholder}.
 */
final class PlaceholderListener {
	public function __invoke(RequestEvent $event): void {
		$request = $event->getRequest();
		if(!$event->isMainRequest() or !$request->isMethodCacheable()) {
			return;
		}

		$placeholder = Placeholder::resolve($request->getPathInfo());
		if($placeholder === null) {
			return;
		}

		$response = new BinaryFileResponse($placeholder["file"], 200, ["Content-Type" => $placeholder["type"]], false, null, true, true);
		$response->setPublic();
		$response->setMaxAge(86400);
		$response->isNotModified($request);

		$event->setResponse($response);
	}
}
