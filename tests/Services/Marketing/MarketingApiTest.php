<?php

namespace Tests\Services\Marketing;

use Faker\Factory;
use Faker\Generator;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use Anibalealvarezs\MailchimpApi\Services\Marketing\MarketingApi;
use Symfony\Component\Yaml\Yaml;

class MarketingApiTest extends TestCase
{
    private MarketingApi $marketingApi;
    private Generator $faker;
    private string $listName;

    /**
     * @throws GuzzleException
     */
    protected function setUp(): void
    {
        $config = Yaml::parseFile(__DIR__ . "/../../../config/config.yaml");
        $this->marketingApi = new MarketingApi(
            apiKey: $config['mailchimp_marketing_api_key'],
            serverPrefix: $config['mailchimp_marketing_server_prefix'],
        );
        $this->listName = $config['mailchimp_marketing_list_for_tests'];
        $this->faker = Factory::create();
    }

    public function testConstruct(): void
    {
        $this->assertInstanceOf(marketingApi::class, $this->marketingApi);
    }

    /**
     * @throws GuzzleException
     */
    public function testPing(): void
    {
        $response = $this->marketingApi->ping();
        $this->assertIsArray($response);
        $this->assertArrayHasKey('health_status', $response);
        $this->assertEquals('Everything\'s Chimpy!', $response['health_status']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetListsInfo(): void
    {
        $lists = $this->marketingApi->getListsInfo(
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
        $lists = $this->marketingApi->getAllListsInfo(
            hasEcommerceStore: $this->faker->boolean,
            includeTotalContacts: $this->faker->boolean,
            sortField: $this->faker->randomElement(['date_created']),
            sortDir: $this->faker->randomElement(['ASC', 'DESC']),
            loopLimit: $this->faker->numberBetween(1, 20),
        );
        $this->assertIsArray($lists);
        $this->assertArrayHasKey('lists', $lists);
        $this->assertIsArray($lists['lists']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetListMembersInfo(): void
    {
        $status = $this->faker->randomElement(['subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional', 'archived']);
        $listMembers = $this->marketingApi->getListMembersInfo(
            listName: $this->listName,
            count: $this->faker->numberBetween(1, 1000),
            fields: ['members.email_address', 'members.id'],
            status: $status,
            sortField: $this->faker->randomElement(["timestamp_signup", "timestamp_opt", "last_changed"]),
            sortDir: $this->faker->randomElement(['ASC', 'DESC']),
            vip_only: $this->faker->boolean,
            unsubscribedSince: $status == 'unsubscribed' ? $this->faker->date() : null,
        );
        $this->assertIsArray($listMembers);
        $this->assertArrayHasKey('members', $listMembers);
        $this->assertIsArray($listMembers['members']);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetAllListMembersInfo(): void
    {
        $status = $this->faker->randomElement(['subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional', 'archived']);
        $listMembers = $this->marketingApi->getAllListMembersInfo(
            listName: $this->listName,
            fields: ['members.email_address', 'members.id'],
            status: $status,
            sortField: $this->faker->randomElement(["timestamp_signup", "timestamp_opt", "last_changed"]),
            sortDir: $this->faker->randomElement(['ASC', 'DESC']),
            vip_only: $this->faker->boolean,
            unsubscribedSince: $status == 'unsubscribed' ? $this->faker->date() : null,
            loopLimit: $this->faker->numberBetween(1, 20),
        );
        $this->assertIsArray($listMembers);
        $this->assertArrayHasKey('members', $listMembers);
        $this->assertIsArray($listMembers['members']);
    }
}