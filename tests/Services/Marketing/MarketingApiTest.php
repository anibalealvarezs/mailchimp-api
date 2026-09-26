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
use Anibalealvarezs\MailchimpApi\Support\MailchimpErrorClassifier;
use Symfony\Component\Yaml\Yaml;
use Anibalealvarezs\ApiSkeleton\Classes\Exceptions\ApiRequestException;

class MarketingApiTest extends TestCase
{
    private MarketingApi $marketingApi;

    protected function createMockedGuzzleClient(MockHandler $mock): GuzzleClient
    {
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }

    protected function setUp(): void
    {
        $configFile = __DIR__ . "/../../../config/config.yaml";
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
        } else {
            $config = [
                'mailchimp_marketing_api_key' => 'key',
                'mailchimp_marketing_server_prefix' => 'us1'
            ];
        }
        $this->marketingApi = new MarketingApi(
            apiKey: $config['mailchimp_marketing_api_key'],
            serverPrefix: $config['mailchimp_marketing_server_prefix'],
        );
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(MarketingApi::class, $this->marketingApi);
    }

    public function testConstructWithBearerToken(): void
    {
        $client = new MarketingApi(
            serverPrefix: 'us14',
            accessToken: 'oauth_bearer_token_xyz'
        );
        $this->assertInstanceOf(MarketingApi::class, $client);
    }

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

    public function testGetListsInfo(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['lists' => [], 'total_items' => 0])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $lists = $client->getListsInfo(
            count: 10,
            hasEcommerceStore: false,
            includeTotalContacts: true,
            sortField: 'date_created',
            sortDir: 'ASC',
        );
        $this->assertIsArray($lists);
        $this->assertArrayHasKey('lists', $lists);
        $this->assertIsArray($lists['lists']);
    }

    public function testGetAllListsInfo(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['lists' => [['id' => 'l1']], 'total_items' => 1])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $lists = $client->getAllListsInfo(
            hasEcommerceStore: false,
            includeTotalContacts: true,
            sortField: 'date_created',
            sortDir: 'ASC',
            loopLimit: 1,
        );
        $this->assertIsArray($lists);
        $this->assertArrayHasKey('lists', $lists);
        $this->assertIsArray($lists['lists']);
    }

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
        $client->getAllCampaignsAndProcess(
            callback: function ($data) use (&$processedCount) {
                $processedCount += count($data);
            },
            batchSize: 1
        );

        $this->assertEquals(2, $processedCount);
    }

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

        $client->getAllCampaignsAndProcess(
            callback: function ($data) {},
            batchSize: 1
        );
    }

    public function testMailchimpSemanticRetryableFalsy200EventuallySucceeds(): void
    {
        $retryableBody = [
            'type' => 'rate_limit',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'You have exceeded your API call limit.',
        ];
        $successBody = ['health_status' => 'Everything\'s Chimpy!'];

        $mock = new MockHandler([
            new Response(200, [], json_encode($retryableBody)),
            new Response(200, [], json_encode($successBody)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $response = $client->performRequest(method: 'GET', endpoint: 'ping');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(is_callable($client->getRateLimitDetector()));
    }

    public function testMailchimpErrorClassifierRecognizesThrottlingSignals(): void
    {
        $classification = MailchimpErrorClassifier::classify([
            'status' => 429,
            'type' => 'throttled',
            'detail' => 'Too many requests',
        ]);

        $this->assertSame('retryable', $classification['category']);
        $this->assertTrue($classification['should_retry']);
    }

    // =========================================================================
    // NEW CAMPAIGN REPORTS & EVENT ACTIVITY STREAM TESTS (PLAN-03)
    // =========================================================================

    public function testGetEmailActivityAndOpenDetails(): void
    {
        $activityResponse = [
            'emails' => [
                ['email_id' => 'hash_1', 'activity' => [['action' => 'open', 'timestamp' => '2026-06-01T10:00:00Z']]],
            ],
            'total_items' => 1,
        ];
        $opensResponse = [
            'members' => [
                ['email_id' => 'hash_1', 'opens_count' => 1, 'proxy_open' => true],
            ],
            'total_items' => 1,
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($activityResponse)),
            new Response(200, [], json_encode($opensResponse)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $activity = $client->getEmailActivity('camp_123');
        $this->assertCount(1, $activity['emails']);
        $this->assertEquals('open', $activity['emails'][0]['activity'][0]['action']);

        $opens = $client->getOpenDetails('camp_123');
        $this->assertCount(1, $opens['members']);
        $this->assertTrue($opens['members'][0]['proxy_open']);
    }

    public function testGetClickDetailsAndClickMembers(): void
    {
        $clicksResponse = [
            'urls_clicked' => [
                ['id' => 'link_99', 'url' => 'https://store.example.com/item', 'total_clicks' => 45, 'unique_clicks' => 30],
            ],
            'total_items' => 1,
        ];
        $clickMembersResponse = [
            'members' => [
                ['email_id' => 'hash_shopper', 'clicks' => 2],
            ],
            'total_items' => 1,
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($clicksResponse)),
            new Response(200, [], json_encode($clickMembersResponse)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $clicks = $client->getClickDetails('camp_123');
        $this->assertCount(1, $clicks['urls_clicked']);
        $this->assertEquals('link_99', $clicks['urls_clicked'][0]['id']);

        $members = $client->getClickMembers('camp_123', 'link_99');
        $this->assertCount(1, $members['members']);
        $this->assertEquals('hash_shopper', $members['members'][0]['email_id']);
    }

    // =========================================================================
    // E-COMMERCE STORES, ORDERS & CUSTOMERS TESTS (PLAN-03)
    // =========================================================================

    public function testGetEcommerceStoresAndOrders(): void
    {
        $storesResponse = [
            'stores' => [
                ['id' => 'store_shopify', 'name' => 'Shopify Brand Store', 'domain' => 'brand.myshopify.com'],
            ],
            'total_items' => 1,
        ];
        $ordersResponse = [
            'orders' => [
                ['id' => 'ord_001', 'order_total' => 150.00, 'campaign_id' => 'camp_123'],
            ],
            'total_items' => 1,
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($storesResponse)),
            new Response(200, [], json_encode($ordersResponse)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $stores = $client->getEcommerceStores();
        $this->assertCount(1, $stores['stores']);
        $this->assertEquals('store_shopify', $stores['stores'][0]['id']);

        $orders = $client->getEcommerceOrders('store_shopify');
        $this->assertCount(1, $orders['orders']);
        $this->assertEquals('ord_001', $orders['orders'][0]['id']);
        $this->assertEquals(150.00, $orders['orders'][0]['order_total']);
    }

    // =========================================================================
    // TAXONOMY: FOLDERS & TEMPLATES TESTS (PLAN-03)
    // =========================================================================

    public function testGetFoldersAndTemplates(): void
    {
        $foldersResponse = [
            'folders' => [
                ['id' => 'fold_1', 'name' => 'Seasonal Promos'],
            ],
            'total_items' => 1,
        ];
        $templatesResponse = [
            'templates' => [
                ['id' => 101, 'name' => 'Cyber Monday Template', 'type' => 'user'],
            ],
            'total_items' => 1,
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($foldersResponse)),
            new Response(200, [], json_encode($templatesResponse)),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $folders = $client->getCampaignFolders();
        $this->assertCount(1, $folders['folders']);

        $templates = $client->getTemplates();
        $this->assertCount(1, $templates['templates']);
        $this->assertEquals(101, $templates['templates'][0]['id']);
    }

    public function testGetAllAndProcessMethods(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['urls_clicked' => [['id' => 'l1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['sent_to' => [['email_id' => 's1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['unsubscribes' => [['email_id' => 'u1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['stores' => [['id' => 'st1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['orders' => [['id' => 'o1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['customers' => [['id' => 'cu1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['folders' => [['id' => 'f1']], 'total_items' => 1])),
            new Response(200, [], json_encode(['templates' => [['id' => 't1']], 'total_items' => 1])),
        ]);
        $guzzle = $this->createMockedGuzzleClient($mock);
        $client = new MarketingApi(apiKey: 'key', serverPrefix: 'us1', guzzleClient: $guzzle);

        $clicksCount = 0;
        $client->getAllClickDetailsAndProcess('c1', function ($batch) use (&$clicksCount) {
            $clicksCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $clicksCount);

        $sentCount = 0;
        $client->getAllSentToMembersAndProcess('c1', function ($batch) use (&$sentCount) {
            $sentCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $sentCount);

        $unsubCount = 0;
        $client->getAllUnsubscribedMembersAndProcess('c1', function ($batch) use (&$unsubCount) {
            $unsubCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $unsubCount);

        $storesCount = 0;
        $client->getAllEcommerceStoresAndProcess(function ($batch) use (&$storesCount) {
            $storesCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $storesCount);

        $ordersCount = 0;
        $client->getAllEcommerceOrdersAndProcess('st1', function ($batch) use (&$ordersCount) {
            $ordersCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $ordersCount);

        $customersCount = 0;
        $client->getAllEcommerceCustomersAndProcess('st1', function ($batch) use (&$customersCount) {
            $customersCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $customersCount);

        $foldersCount = 0;
        $client->getAllCampaignFoldersAndProcess(function ($batch) use (&$foldersCount) {
            $foldersCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $foldersCount);

        $templatesCount = 0;
        $client->getAllTemplatesAndProcess(function ($batch) use (&$templatesCount) {
            $templatesCount += count($batch);
        }, batchSize: 1);
        $this->assertEquals(1, $templatesCount);
    }
}

