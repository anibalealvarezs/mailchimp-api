<?php

namespace Tests\Services\Marketing;

use Faker\Factory;
use Faker\Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Anibalealvarezs\MailchimpApi\Services\Marketing\MarketingApi;
use Symfony\Component\Yaml\Yaml;
use Anibalealvarezs\ApiSkeleton\Classes\Exceptions\ApiRequestException;

class MarketingApiTest extends TestCase
{
    private MarketingApi $marketingApi;
    private Generator $faker;
    private string $listName;

    /**
     * @param MockHandler $mock
     * @return GuzzleClient
     */
    protected function createMockedGuzzleClient(MockHandler $mock): GuzzleClient
    {
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }

    /**
     * @throws GuzzleException
     */
    protected function setUp(): void
    {
        $configFile = __DIR__ . "/../../../config/config.yaml";
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            $this->listName = $config['mailchimp_marketing_list_for_tests'];
        } else {
            $config = [
                'mailchimp_marketing_api_key' => 'key',
                'mailchimp_marketing_server_prefix' => 'us1'
            ];
            $this->listName = 'test-list';
        }
        $this->marketingApi = new MarketingApi(
            apiKey: $config['mailchimp_marketing_api_key'],
            serverPrefix: $config['mailchimp_marketing_server_prefix'],
        );
        $this->faker = Factory::create();
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(MarketingApi::class, $this->marketingApi);
    }

    /**
     * @throws GuzzleException
     */
    public function testPing(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['health_status' => 'Everything\'s Chimpy!'])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $response = $client->ping();
        $this->assertIsArray($response);
        $this->assertArrayHasKey('health_status', $response);
        $this->assertEquals('Everything\'s Chimpy!', $response['health_status']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetListsInfo(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['lists' => [], 'total_items' => 0])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $lists = $client->getListsInfo(
            count: $this->faker->numberBetween(1, 1000),
            hasEcommerceStore: $this->faker->boolean,
            includeTotalContacts: $this->faker->boolean,
            sortField: $this->faker->randomElement(['date_created']),
            sortDir: $this->faker->randomElement(['ASC', 'DESC']),
        );
        $this->assertIsArray($lists);
        $this->assertArrayHasKey('lists', $lists);
        $this->assertIsArray($lists['lists']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllListsInfo(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['lists' => [['id' => 'l1']], 'total_items' => 1])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $lists = $client->getAllListsInfo(
            hasEcommerceStore: $this->faker->boolean,
            includeTotalContacts: $this->faker->boolean,
            sortField: $this->faker->randomElement(['date_created']),
            sortDir: $this->faker->randomElement(['ASC', 'DESC']),
            loopLimit: 1,
        );
        $this->assertIsArray($lists);
        $this->assertArrayHasKey('lists', $lists);
        $this->assertIsArray($lists['lists']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllCampaignsAndProcess(): void
    {
        $response1 = [
            'campaigns' => [['id' => 'c1']],
            'total_items' => 2
        ];
        $response2 = [
            'campaigns' => [['id' => 'c2']],
            'total_items' => 2
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($response1)),
            new Response(200, [], json_encode($response2)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);

        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $processedCount = 0;
        $client->getAllCampaignsAndProcess(function ($data) use (&$processedCount) {
            $processedCount += count($data);
        });

        $this->assertEquals(2, $processedCount);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllCampaignsEmpty(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['campaigns' => [], 'total_items' => 0])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);

        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $result = $client->getAllCampaigns();
        
        $this->assertCount(0, $result['campaigns']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllCampaignsErrorMidLoop(): void
    {
        $response1 = [
            'campaigns' => [['id' => 'c1']],
            'total_items' => 2
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($response1)),
            new Response(500, [], 'Internal Server Error'),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);

        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $this->expectException(ApiRequestException::class);

        $client->getAllCampaignsAndProcess(function ($data) {});
    }
}
