<?php

declare(strict_types=1);

namespace PhpAgent\Tests;

use PHPUnit\Framework\TestCase;
use PhpAgent\Response;
use PhpAgent\Llm\Usage;

class ResponseTest extends TestCase
{
	public function testResponseProperties(): void
	{
		$usage = new Usage(1, 2, 3);
		$response = new Response(
			content: 'Hello world',
			role: 'assistant',
			finishReason: 'stop',
			usage: $usage,
			iterations: 2,
			metadata: ['foo' => 'bar']
		);

		$this->assertSame('Hello world', $response->content);
		$this->assertSame('assistant', $response->role);
		$this->assertSame('stop', $response->finishReason);
		$this->assertSame($usage, $response->usage);
		$this->assertSame(2, $response->iterations);
		$this->assertSame(['foo' => 'bar'], $response->metadata);
	}
}
