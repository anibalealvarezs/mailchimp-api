<?php

namespace Tests\Services\Transactional;

use Anibalealvarezs\MailchimpApi\Services\Transactional\TransactionalApi;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TransactionalApiTest extends TestCase
{
    protected function createMockedGuzzleClient(MockHandler $mock): GuzzleClient
    {
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }

    /**
     * @throws GuzzleException
     */
    public function testConstructSetsCallableRateLimitDetector(): void
    {
        $client = new TransactionalApi(apiKey: 'key');
        $this->assertTrue(is_callable($client->getRateLimitDetector()));
    }

    /**
     * @throws GuzzleException
     */
    public function testPingSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['PING' => 'PONG!'])),
        ]);
        $client = new TransactionalApi(
            apiKey: 'key',
            guzzleClient: $this->createMockedGuzzleClient($mock)
        );

        $response = $client->ping();
        $this->assertSame('PONG!', $response['PING']);
    }
}

