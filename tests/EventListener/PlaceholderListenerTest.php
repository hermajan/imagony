<?php

declare(strict_types=1);

namespace Imagony\Tests\EventListener;

use Imagony\EventListener\PlaceholderListener;
use Imagony\Placeholder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request};
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PlaceholderListenerTest extends TestCase {
	private function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent {
		$event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
		(new PlaceholderListener())($event);

		return $event;
	}

	public function testServesThePlaceholderFromTheBundle(): void {
		$event = $this->handle(Request::create(Placeholder::URL));

		$response = $event->getResponse();
		$this->assertInstanceOf(BinaryFileResponse::class, $response);
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(Placeholder::file(), $response->getFile()->getPathname());
		$this->assertSame("image/png", $response->headers->get("Content-Type"));
		$this->assertTrue($event->isPropagationStopped(), "The router is not reached.");
	}

	public static function variants(): iterable {
		yield "webp" => [".webp", "image/webp"];
		yield "avif" => [".avif", "image/avif"];
	}

	#[DataProvider("variants")]
	public function testServesTheVariants(string $suffix, string $type): void {
		$response = $this->handle(Request::create(Placeholder::URL.$suffix))->getResponse();

		$this->assertInstanceOf(BinaryFileResponse::class, $response);
		$this->assertSame(Placeholder::file().$suffix, $response->getFile()->getPathname());
		$this->assertSame($type, $response->headers->get("Content-Type"));
	}

	public function testResponseIsPubliclyCacheable(): void {
		$response = $this->handle(Request::create(Placeholder::URL))->getResponse();

		$this->assertTrue($response->headers->hasCacheControlDirective("public"));
		$this->assertSame(86400, $response->getMaxAge());
		$this->assertNotNull($response->getEtag());
		$this->assertNotNull($response->getLastModified());
	}

	public function testAnswersNotModified(): void {
		$first = $this->handle(Request::create(Placeholder::URL))->getResponse();
		$request = Request::create(Placeholder::URL);
		$request->headers->set("If-None-Match", $first->getEtag());

		$response = $this->handle($request)->getResponse();

		$this->assertSame(304, $response->getStatusCode());
	}

	public function testHeadRequestIsServed(): void {
		$this->assertNotNull($this->handle(Request::create(Placeholder::URL, "HEAD"))->getResponse());
	}

	public function testOtherMethodsAreLeftToTheApplication(): void {
		$this->assertNull($this->handle(Request::create(Placeholder::URL, "POST"))->getResponse());
	}

	public function testSubRequestsAreIgnored(): void {
		$this->assertNull($this->handle(Request::create(Placeholder::URL), HttpKernelInterface::SUB_REQUEST)->getResponse());
	}

	public static function otherUrls(): iterable {
		yield "root" => ["/"];
		yield "other file" => ["/_imagony/other.png"];
		yield "traversal" => ["/_imagony/../composer.json"];
		yield "page" => ["/cinema/1"];
	}

	#[DataProvider("otherUrls")]
	public function testOtherUrlsAreLeftToTheApplication(string $url): void {
		$this->assertNull($this->handle(Request::create($url))->getResponse());
	}
}
