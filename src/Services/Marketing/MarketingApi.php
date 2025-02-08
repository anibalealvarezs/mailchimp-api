<?php

namespace Anibalealvarezs\MailchimpApi\Services\Marketing;

use Carbon\Carbon;
use Anibalealvarezs\ApiSkeleton\Clients\BasicClient;
use Anibalealvarezs\ApiSkeleton\Enums\EncodingMethod;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MarketingApi extends BasicClient
{
    /**
     * @param string $apiKey
     * @param string $serverPrefix
     * @throws GuzzleException
     */
    public function __construct(
        string $apiKey,
        string $serverPrefix,
    ) {
        return parent::__construct(
            baseUrl: 'https://'.$serverPrefix.'.api.mailchimp.com/3.0/',
            username: 'whatever',
            password: $apiKey,
            encodingMethod: EncodingMethod::base64,
            delayHeader: "X-Rate-Limit-Reset",
        );
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function ping(): array {
        $response = $this->performRequest(
            method: "GET",
            endpoint: "ping",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param int $count
     * @param int $offset
     * @param bool $hasEcommerceStore
     * @param bool $includeTotalContacts
     * @param string $sortField
     * @param string $sortDir
     * @return array
     * @throws GuzzleException
     */
    public function getListsInfo(
        int $count = 1000,
        int $offset = 0,
        bool $hasEcommerceStore = false,
        bool $includeTotalContacts = true,
        string $sortField = "date_created", // Currently just "date_created" is supported
        string $sortDir = "DESC",
    ): array {
        $query =[
            "count" => $count,
            "offset" => $offset,
            "has_ecommerce_store" => $hasEcommerceStore,
            "include_total_contacts" => $includeTotalContacts,
            "sort_field" => $sortField,
            "sort_dir" => $sortDir,
        ];
        $response = $this->performRequest(
            method: "GET",
            endpoint: "lists",
            query: $query,
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param bool $hasEcommerceStore
     * @param bool $includeTotalContacts
     * @param string $sortField
     * @param string $sortDir
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllListsInfo(
        bool $hasEcommerceStore = false,
        bool $includeTotalContacts = true,
        string $sortField = "date_created", // Currently just "date_created" is supported
        string $sortDir = "DESC",
        ?int $loopLimit = null,
    ): array {
        $count = 1000;
        $offset = 0;
        $lists = [];
        $loops = 0;

        do {
            $response = $this->getListsInfo(
                count: $count,
                offset: $offset,
                hasEcommerceStore: $hasEcommerceStore,
                includeTotalContacts: $includeTotalContacts,
                sortField: $sortField,
                sortDir: $sortDir
            );
            $lists = [...$lists, ...$response['lists']];
            $offset += $count;
            $loops++;
        } while ($response['total_items'] > $offset && (is_null($loopLimit) || $loops < $loopLimit));

        // Return all lists
        return [
            'total_items' => $response['total_items'],
            'lists' => $lists
        ];
    }

    /**
     * @param string|null $listId
     * @param string|null $listName
     * @param int $count
     * @param int $offset
     * @param array $fields
     * @param string|null $status
     * @param string $sortField
     * @param string $sortDir
     * @param bool $vip_only
     * @param string|null $unsubscribedSince
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public function getListMembersInfo(
        ?string $listId = null,
        ?string $listName = null,
        int $count = 1000,
        int $offset = 0,
        array $fields = [
            'members.id','members.email_address','members.unique_email_id','members.contact_id','members.full_name',
            'members.web_id', 'members.status', 'members.consents_to_one_to_one_messaging', 'members.sms_phone_number',
            'members.sms_subscription_status', 'members.stats','members.timestamp_signup','members.timestamp_opt','members.vip',
            'members.location'.'country_code','members.location.timezone','members.location.region','members.tags_count',
            'members.tags','list_id','total_items',
        ],
        ?string $status = "subscribed",
        string $sortField = "timestamp_opt", // Possible values: "timestamp_signup", "timestamp_opt", "last_changed"
        string $sortDir = "DESC", // Possible values: "ASC", "DESC"
        bool $vip_only = false,
        string $unsubscribedSince = null,
    ): array {
        if ($listId === null && $listName === null) {
            throw new Exception("Either listId or listName must be provided.");
        }
        if ($listId === null) {
            $lists = $this->getAllListsInfo();
            $list = array_filter($lists['lists'], function($list) use ($listName) {
                return $list['name'] === $listName;
            });
            if (count($list) === 0) {
                throw new Exception("List with name ".$listName." not found.");
            }
            $listId = $list[0]['id'];
        }
        $query =[
            "count" => $count,
            "offset" => $offset,
            "sort_field" => $sortField,
            "sort_dir" => $sortDir,
            "vip_only" => $vip_only,
        ];
        if ($status !== null) {
            $query['status'] = $status;
        }
        if (count($fields) > 0) {
            $query['fields'] = implode(",", $fields);
        }
        if ($unsubscribedSince !== null) {
            if ($status !== 'unsubscribed') {
                throw new Exception("Status must equal 'unsubscribed' in order to use the 'unsubscribedSince' param");
            }
            $query['unsubscribed_since'] = Carbon::parse($unsubscribedSince)->format('Y-m-d');
        }
        $response = $this->performRequest(
            method: "GET",
            endpoint: "lists/".$listId."/members",
            query: $query,
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string|null $listId
     * @param string|null $listName
     * @param array $fields
     * @param string|null $status
     * @param string $sortField
     * @param string $sortDir
     * @param bool $vip_only
     * @param string|null $unsubscribedSince
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public function getAllListMembersInfo(
        ?string $listId = null,
        ?string $listName = null,
        array $fields = [
            'members.id','members.email_address','members.unique_email_id','members.contact_id','members.full_name',
            'members.web_id', 'members.status', 'members.consents_to_one_to_one_messaging', 'members.sms_phone_number',
            'members.sms_subscription_status', 'members.stats','members.timestamp_signup','members.timestamp_opt','members.vip',
            'members.location'.'country_code','members.location.timezone','members.location.region','members.tags_count',
            'members.tags','list_id','total_items',
        ],
        ?string $status = "subscribed",
        string $sortField = "timestamp_opt", // Possible values: "timestamp_signup", "timestamp_opt", "last_changed"
        string $sortDir = "DESC", // Possible values: "ASC", "DESC"
        bool $vip_only = false,
        string $unsubscribedSince = null,
        ?int $loopLimit = null,
    ): array {
        $count = 1000;
        $offset = 0;
        $members = [];
        $loops = 0;

        do {
            $response = $this->getListMembersInfo(
                listId: $listId,
                listName: $listName,
                count: $count,
                offset: $offset,
                fields: $fields,
                status: $status,
                sortField: $sortField,
                sortDir: $sortDir,
                vip_only: $vip_only,
                unsubscribedSince: $unsubscribedSince,
            );
            $members = [...$members, ...$response['members']];
            if (!isset($response['total_items'])) {
                $response['total_items'] = count($members);
            }
            $offset += $count;
            $loops++;
        } while ($response['total_items'] > $offset && (is_null($loopLimit) || $loops < $loopLimit));

        // Return all members
        return [
            'total_items' => $response['total_items'],
            'members' => $members
        ];
    }
}