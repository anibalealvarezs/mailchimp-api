<?php

namespace Anibalealvarezs\MailchimpApi\Services\Marketing;

use Carbon\Carbon;
use Anibalealvarezs\ApiSkeleton\Clients\BasicClient;
use Anibalealvarezs\ApiSkeleton\Enums\AuthType;
use Anibalealvarezs\ApiSkeleton\Enums\EncodingMethod;
use Anibalealvarezs\MailchimpApi\Support\MailchimpErrorClassifier;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MarketingApi extends BasicClient
{
    /**
     * @param string|null $apiKey
     * @param string $serverPrefix
     * @param \GuzzleHttp\Client|null $guzzleClient
     * @param string|null $accessToken
     * @throws GuzzleException
     * @throws Exception
     */
    public function __construct(
        ?string $apiKey = null,
        string $serverPrefix = 'us1',
        ?\GuzzleHttp\Client $guzzleClient = null,
        ?string $accessToken = null,
    ) {
        $defaultHeaders = [];
        $token = '';
        $authType = AuthType::basic;

        if ($accessToken) {
            $authType = AuthType::bearerToken;
            $token = $accessToken;
            $defaultHeaders['Authorization'] = 'Bearer ' . $accessToken;
        }

        parent::__construct(
            baseUrl: 'https://' . $serverPrefix . '.api.mailchimp.com/3.0/',
            username: 'whatever',
            password: (string)($apiKey ?: ($accessToken ?: 'dummy')),
            encodingMethod: EncodingMethod::base64,
            defaultHeaders: $defaultHeaders,
            delayHeader: "X-Rate-Limit-Reset",
            guzzleClient: $guzzleClient,
        );

        if ($accessToken) {
            $this->setAuthType(AuthType::bearerToken);
            $this->setToken($token);
        }

        $this->setResponseErrorDetector('detail');
        $this->setErrorMessageParser(fn ($data) => $data['detail'] ?? ($data['title'] ?? json_encode($data)));
        $this->setRateLimitDetector([MailchimpErrorClassifier::class, 'isRetryable']);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function ping(): array
    {
        $response = $this->performRequest(
            method: "GET",
            endpoint: "ping",
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    // =========================================================================
    // LISTS / AUDIENCES
    // =========================================================================

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
        string $sortField = "date_created",
        string $sortDir = "DESC",
    ): array {
        $query = [
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
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param callable $callback
     * @param bool $hasEcommerceStore
     * @param bool $includeTotalContacts
     * @param string $sortField
     * @param string $sortDir
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllListsInfoAndProcess(
        callable $callback,
        bool $hasEcommerceStore = false,
        bool $includeTotalContacts = true,
        string $sortField = "date_created",
        string $sortDir = "DESC",
        int $batchSize = 1000,
    ): void {
        $offset = 0;

        do {
            $response = $this->getListsInfo(
                count: $batchSize,
                offset: $offset,
                hasEcommerceStore: $hasEcommerceStore,
                includeTotalContacts: $includeTotalContacts,
                sortField: $sortField,
                sortDir: $sortDir
            );
            if (!empty($response['lists'])) {
                $callback($response['lists']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
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
        string $sortField = "date_created",
        string $sortDir = "DESC",
        ?int $loopLimit = null,
    ): array {
        $lists = [];
        $loops = 0;

        $this->getAllListsInfoAndProcess(
            callback: function ($batch) use (&$lists, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $lists = [...$lists, ...$batch];
                    $loops++;
                }
            },
            hasEcommerceStore: $hasEcommerceStore,
            includeTotalContacts: $includeTotalContacts,
            sortField: $sortField,
            sortDir: $sortDir,
            batchSize: 1000
        );

        return [
            'total_items' => count($lists),
            'lists' => $lists
        ];
    }

    // =========================================================================
    // LIST MEMBERS / CONTACTS
    // =========================================================================

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
            'members.location.country_code','members.location.timezone','members.location.region','members.tags_count',
            'members.tags','list_id','total_items',
        ],
        ?string $status = "subscribed",
        string $sortField = "timestamp_opt",
        string $sortDir = "DESC",
        bool $vip_only = false,
        string $unsubscribedSince = null,
    ): array {
        if ($listId === null && $listName === null) {
            throw new Exception("Either listId or listName must be provided.");
        }
        if ($listId === null) {
            $lists = $this->getAllListsInfo();
            $list = array_filter($lists['lists'], function ($list) use ($listName) {
                return $list['name'] === $listName;
            });
            if (count($list) === 0) {
                throw new Exception("List with name ".$listName." not found.");
            }
            $listId = $list[0]['id'];
        }
        $query = [
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
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param callable $callback
     * @param string|null $listId
     * @param string|null $listName
     * @param array $fields
     * @param string|null $status
     * @param string $sortField
     * @param string $sortDir
     * @param bool $vip_only
     * @param string|null $unsubscribedSince
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     * @throws Exception
     */
    public function getAllListMembersInfoAndProcess(
        callable $callback,
        ?string $listId = null,
        ?string $listName = null,
        array $fields = [
            'members.id','members.email_address','members.unique_email_id','members.contact_id','members.full_name',
            'members.web_id', 'members.status', 'members.consents_to_one_to_one_messaging', 'members.sms_phone_number',
            'members.sms_subscription_status', 'members.stats','members.timestamp_signup','members.timestamp_opt','members.vip',
            'members.location.country_code','members.location.timezone','members.location.region','members.tags_count',
            'members.tags','list_id','total_items',
        ],
        ?string $status = "subscribed",
        string $sortField = "timestamp_opt",
        string $sortDir = "DESC",
        bool $vip_only = false,
        string $unsubscribedSince = null,
        int $batchSize = 1000,
    ): void {
        $offset = 0;

        do {
            $response = $this->getListMembersInfo(
                listId: $listId,
                listName: $listName,
                count: $batchSize,
                offset: $offset,
                fields: $fields,
                status: $status,
                sortField: $sortField,
                sortDir: $sortDir,
                vip_only: $vip_only,
                unsubscribedSince: $unsubscribedSince,
            );
            if (!empty($response['members'])) {
                $callback($response['members']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
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
            'members.location.country_code','members.location.timezone','members.location.region','members.tags_count',
            'members.tags','list_id','total_items',
        ],
        ?string $status = "subscribed",
        string $sortField = "timestamp_opt",
        string $sortDir = "DESC",
        bool $vip_only = false,
        string $unsubscribedSince = null,
        ?int $loopLimit = null,
    ): array {
        $members = [];
        $loops = 0;

        $this->getAllListMembersInfoAndProcess(
            callback: function ($batch) use (&$members, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $members = [...$members, ...$batch];
                    $loops++;
                }
            },
            listId: $listId,
            listName: $listName,
            fields: $fields,
            status: $status,
            sortField: $sortField,
            sortDir: $sortDir,
            vip_only: $vip_only,
            unsubscribedSince: $unsubscribedSince,
            batchSize: 1000
        );

        return [
            'total_items' => count($members),
            'members' => $members
        ];
    }

    // =========================================================================
    // CAMPAIGNS
    // =========================================================================

    /**
     * @param int $count
     * @param int $offset
     * @param string|null $status
     * @param string $sortField
     * @param string $sortDir
     * @return array
     * @throws GuzzleException
     */
    public function getCampaigns(
        int $count = 1000,
        int $offset = 0,
        ?string $status = null,
        string $sortField = "create_time",
        string $sortDir = "DESC",
    ): array {
        $query = [
            "count" => $count,
            "offset" => $offset,
            "sort_field" => $sortField,
            "sort_dir" => $sortDir,
        ];
        if ($status) {
            $query['status'] = $status;
        }
        $response = $this->performRequest(
            method: "GET",
            endpoint: "campaigns",
            query: $query,
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param callable $callback
     * @param string|null $status
     * @param string $sortField
     * @param string $sortDir
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllCampaignsAndProcess(
        callable $callback,
        ?string $status = null,
        string $sortField = "create_time",
        string $sortDir = "DESC",
        int $batchSize = 1000,
    ): void {
        $offset = 0;

        do {
            $response = $this->getCampaigns(
                count: $batchSize,
                offset: $offset,
                status: $status,
                sortField: $sortField,
                sortDir: $sortDir
            );
            if (!empty($response['campaigns'])) {
                $callback($response['campaigns']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string|null $status
     * @param string $sortField
     * @param string $sortDir
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllCampaigns(
        ?string $status = null,
        string $sortField = "create_time",
        string $sortDir = "DESC",
        ?int $loopLimit = null,
    ): array {
        $campaigns = [];
        $loops = 0;

        $this->getAllCampaignsAndProcess(
            callback: function ($batch) use (&$campaigns, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $campaigns = [...$campaigns, ...$batch];
                    $loops++;
                }
            },
            status: $status,
            sortField: $sortField,
            sortDir: $sortDir,
            batchSize: 1000
        );

        return [
            'total_items' => count($campaigns),
            'campaigns' => $campaigns
        ];
    }

    // =========================================================================
    // CAMPAIGN REPORTS & EVENT ACTIVITY STREAMS (PLAN-03)
    // =========================================================================

    /**
     * GET /reports/{campaign_id}/email-activity
     *
     * @param string $campaignId
     * @param int $count
     * @param int $offset
     * @param string|null $since
     * @return array
     * @throws GuzzleException
     */
    public function getEmailActivity(
        string $campaignId,
        int $count = 1000,
        int $offset = 0,
        ?string $since = null
    ): array {
        $query = [
            'count' => $count,
            'offset' => $offset,
        ];
        if ($since) {
            $query['since'] = $since;
        }

        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'reports/' . $campaignId . '/email-activity',
            query: $query,
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param callable $callback
     * @param int $batchSize
     * @param string|null $since
     * @return void
     * @throws GuzzleException
     */
    public function getAllEmailActivityAndProcess(
        string $campaignId,
        callable $callback,
        int $batchSize = 1000,
        ?string $since = null
    ): void {
        $offset = 0;

        do {
            $response = $this->getEmailActivity(
                campaignId: $campaignId,
                count: $batchSize,
                offset: $offset,
                since: $since
            );
            if (!empty($response['emails'])) {
                $callback($response['emails']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $campaignId
     * @param string|null $since
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllEmailActivity(
        string $campaignId,
        ?string $since = null,
        ?int $loopLimit = null
    ): array {
        $emails = [];
        $loops = 0;

        $this->getAllEmailActivityAndProcess(
            campaignId: $campaignId,
            callback: function ($batch) use (&$emails, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $emails = [...$emails, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000,
            since: $since
        );

        return [
            'total_items' => count($emails),
            'emails' => $emails
        ];
    }

    /**
     * GET /reports/{campaign_id}/open-details
     * Includes Apple MPP proxy open flags (proxy_open).
     *
     * @param string $campaignId
     * @param int $count
     * @param int $offset
     * @param string|null $since
     * @return array
     * @throws GuzzleException
     */
    public function getOpenDetails(
        string $campaignId,
        int $count = 1000,
        int $offset = 0,
        ?string $since = null
    ): array {
        $query = [
            'count' => $count,
            'offset' => $offset,
        ];
        if ($since) {
            $query['since'] = $since;
        }

        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'reports/' . $campaignId . '/open-details',
            query: $query,
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param callable $callback
     * @param int $batchSize
     * @param string|null $since
     * @return void
     * @throws GuzzleException
     */
    public function getAllOpenDetailsAndProcess(
        string $campaignId,
        callable $callback,
        int $batchSize = 1000,
        ?string $since = null
    ): void {
        $offset = 0;

        do {
            $response = $this->getOpenDetails(
                campaignId: $campaignId,
                count: $batchSize,
                offset: $offset,
                since: $since
            );
            if (!empty($response['members'])) {
                $callback($response['members']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $campaignId
     * @param string|null $since
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllOpenDetails(
        string $campaignId,
        ?string $since = null,
        ?int $loopLimit = null
    ): array {
        $members = [];
        $loops = 0;

        $this->getAllOpenDetailsAndProcess(
            campaignId: $campaignId,
            callback: function ($batch) use (&$members, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $members = [...$members, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000,
            since: $since
        );

        return [
            'total_items' => count($members),
            'members' => $members
        ];
    }

    /**
     * GET /reports/{campaign_id}/click-details
     *
     * @param string $campaignId
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getClickDetails(
        string $campaignId,
        int $count = 1000,
        int $offset = 0
    ): array {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'reports/' . $campaignId . '/click-details',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllClickDetailsAndProcess(
        string $campaignId,
        callable $callback,
        int $batchSize = 1000
    ): void {
        $offset = 0;

        do {
            $response = $this->getClickDetails(
                campaignId: $campaignId,
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['urls_clicked'])) {
                $callback($response['urls_clicked']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $campaignId
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllClickDetails(
        string $campaignId,
        ?int $loopLimit = null
    ): array {
        $links = [];
        $loops = 0;

        $this->getAllClickDetailsAndProcess(
            campaignId: $campaignId,
            callback: function ($batch) use (&$links, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $links = [...$links, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000
        );

        return [
            'total_items' => count($links),
            'urls_clicked' => $links
        ];
    }

    /**
     * GET /reports/{campaign_id}/click-details/{link_id}/members
     *
     * @param string $campaignId
     * @param string $linkId
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getClickMembers(
        string $campaignId,
        string $linkId,
        int $count = 1000,
        int $offset = 0
    ): array {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'reports/' . $campaignId . '/click-details/' . $linkId . '/members',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param string $linkId
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllClickMembersAndProcess(
        string $campaignId,
        string $linkId,
        callable $callback,
        int $batchSize = 1000
    ): void {
        $offset = 0;

        do {
            $response = $this->getClickMembers(
                campaignId: $campaignId,
                linkId: $linkId,
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['members'])) {
                $callback($response['members']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $campaignId
     * @param string $linkId
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllClickMembers(
        string $campaignId,
        string $linkId,
        ?int $loopLimit = null
    ): array {
        $members = [];
        $loops = 0;

        $this->getAllClickMembersAndProcess(
            campaignId: $campaignId,
            linkId: $linkId,
            callback: function ($batch) use (&$members, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $members = [...$members, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000
        );

        return [
            'total_items' => count($members),
            'members' => $members
        ];
    }

    /**
     * GET /reports/{campaign_id}/sent-to
     *
     * @param string $campaignId
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getSentToMembers(
        string $campaignId,
        int $count = 1000,
        int $offset = 0
    ): array {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'reports/' . $campaignId . '/sent-to',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllSentToMembersAndProcess(
        string $campaignId,
        callable $callback,
        int $batchSize = 1000
    ): void {
        $offset = 0;

        do {
            $response = $this->getSentToMembers(
                campaignId: $campaignId,
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['sent_to'])) {
                $callback($response['sent_to']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $campaignId
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllSentToMembers(
        string $campaignId,
        ?int $loopLimit = null
    ): array {
        $sentTo = [];
        $loops = 0;

        $this->getAllSentToMembersAndProcess(
            campaignId: $campaignId,
            callback: function ($batch) use (&$sentTo, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $sentTo = [...$sentTo, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000
        );

        return [
            'total_items' => count($sentTo),
            'sent_to' => $sentTo
        ];
    }

    /**
     * GET /reports/{campaign_id}/unsubscribed
     *
     * @param string $campaignId
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getUnsubscribedMembers(
        string $campaignId,
        int $count = 1000,
        int $offset = 0
    ): array {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'reports/' . $campaignId . '/unsubscribed',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $campaignId
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllUnsubscribedMembersAndProcess(
        string $campaignId,
        callable $callback,
        int $batchSize = 1000
    ): void {
        $offset = 0;

        do {
            $response = $this->getUnsubscribedMembers(
                campaignId: $campaignId,
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['unsubscribes'])) {
                $callback($response['unsubscribes']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $campaignId
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllUnsubscribedMembers(
        string $campaignId,
        ?int $loopLimit = null
    ): array {
        $unsubscribes = [];
        $loops = 0;

        $this->getAllUnsubscribedMembersAndProcess(
            campaignId: $campaignId,
            callback: function ($batch) use (&$unsubscribes, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $unsubscribes = [...$unsubscribes, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000
        );

        return [
            'total_items' => count($unsubscribes),
            'unsubscribes' => $unsubscribes
        ];
    }

    // =========================================================================
    // E-COMMERCE STORES, ORDERS & CUSTOMERS (PLAN-03)
    // =========================================================================

    /**
     * GET /ecommerce/stores
     *
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getEcommerceStores(int $count = 100, int $offset = 0): array
    {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'ecommerce/stores',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllEcommerceStoresAndProcess(
        callable $callback,
        int $batchSize = 100
    ): void {
        $offset = 0;

        do {
            $response = $this->getEcommerceStores(
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['stores'])) {
                $callback($response['stores']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllEcommerceStores(?int $loopLimit = null): array
    {
        $stores = [];
        $loops = 0;

        $this->getAllEcommerceStoresAndProcess(
            callback: function ($batch) use (&$stores, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $stores = [...$stores, ...$batch];
                    $loops++;
                }
            },
            batchSize: 100
        );

        return [
            'total_items' => count($stores),
            'stores' => $stores
        ];
    }

    /**
     * GET /ecommerce/stores/{store_id}/orders
     *
     * @param string $storeId
     * @param int $count
     * @param int $offset
     * @param string|null $customerEmail
     * @param string|null $campaignId
     * @return array
     * @throws GuzzleException
     */
    public function getEcommerceOrders(
        string $storeId,
        int $count = 1000,
        int $offset = 0,
        ?string $customerEmail = null,
        ?string $campaignId = null
    ): array {
        $query = [
            'count' => $count,
            'offset' => $offset,
        ];
        if ($customerEmail) {
            $query['customer_email'] = $customerEmail;
        }
        if ($campaignId) {
            $query['campaign_id'] = $campaignId;
        }

        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'ecommerce/stores/' . $storeId . '/orders',
            query: $query,
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $storeId
     * @param callable $callback
     * @param int $batchSize
     * @param string|null $customerEmail
     * @param string|null $campaignId
     * @return void
     * @throws GuzzleException
     */
    public function getAllEcommerceOrdersAndProcess(
        string $storeId,
        callable $callback,
        int $batchSize = 1000,
        ?string $customerEmail = null,
        ?string $campaignId = null
    ): void {
        $offset = 0;

        do {
            $response = $this->getEcommerceOrders(
                storeId: $storeId,
                count: $batchSize,
                offset: $offset,
                customerEmail: $customerEmail,
                campaignId: $campaignId
            );
            if (!empty($response['orders'])) {
                $callback($response['orders']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $storeId
     * @param string|null $customerEmail
     * @param string|null $campaignId
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllEcommerceOrders(
        string $storeId,
        ?string $customerEmail = null,
        ?string $campaignId = null,
        ?int $loopLimit = null
    ): array {
        $orders = [];
        $loops = 0;

        $this->getAllEcommerceOrdersAndProcess(
            storeId: $storeId,
            callback: function ($batch) use (&$orders, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $orders = [...$orders, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000,
            customerEmail: $customerEmail,
            campaignId: $campaignId
        );

        return [
            'total_items' => count($orders),
            'orders' => $orders
        ];
    }

    /**
     * GET /ecommerce/stores/{store_id}/customers
     *
     * @param string $storeId
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getEcommerceCustomers(
        string $storeId,
        int $count = 1000,
        int $offset = 0
    ): array {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'ecommerce/stores/' . $storeId . '/customers',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $storeId
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllEcommerceCustomersAndProcess(
        string $storeId,
        callable $callback,
        int $batchSize = 1000
    ): void {
        $offset = 0;

        do {
            $response = $this->getEcommerceCustomers(
                storeId: $storeId,
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['customers'])) {
                $callback($response['customers']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $storeId
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllEcommerceCustomers(
        string $storeId,
        ?int $loopLimit = null
    ): array {
        $customers = [];
        $loops = 0;

        $this->getAllEcommerceCustomersAndProcess(
            storeId: $storeId,
            callback: function ($batch) use (&$customers, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $customers = [...$customers, ...$batch];
                    $loops++;
                }
            },
            batchSize: 1000
        );

        return [
            'total_items' => count($customers),
            'customers' => $customers
        ];
    }

    // =========================================================================
    // ORGANIZATIONAL TAXONOMY: FOLDERS & TEMPLATES (PLAN-03)
    // =========================================================================

    /**
     * GET /campaign-folders
     *
     * @param int $count
     * @param int $offset
     * @return array
     * @throws GuzzleException
     */
    public function getCampaignFolders(int $count = 100, int $offset = 0): array
    {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'campaign-folders',
            query: ['count' => $count, 'offset' => $offset],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param callable $callback
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllCampaignFoldersAndProcess(
        callable $callback,
        int $batchSize = 100
    ): void {
        $offset = 0;

        do {
            $response = $this->getCampaignFolders(
                count: $batchSize,
                offset: $offset
            );
            if (!empty($response['folders'])) {
                $callback($response['folders']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllCampaignFolders(?int $loopLimit = null): array
    {
        $folders = [];
        $loops = 0;

        $this->getAllCampaignFoldersAndProcess(
            callback: function ($batch) use (&$folders, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $folders = [...$folders, ...$batch];
                    $loops++;
                }
            },
            batchSize: 100
        );

        return [
            'total_items' => count($folders),
            'folders' => $folders
        ];
    }

    /**
     * GET /templates
     *
     * @param int $count
     * @param int $offset
     * @param string $type
     * @return array
     * @throws GuzzleException
     */
    public function getTemplates(
        int $count = 1000,
        int $offset = 0,
        string $type = 'user'
    ): array {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'templates',
            query: ['count' => $count, 'offset' => $offset, 'type' => $type],
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param callable $callback
     * @param string $type
     * @param int $batchSize
     * @return void
     * @throws GuzzleException
     */
    public function getAllTemplatesAndProcess(
        callable $callback,
        string $type = 'user',
        int $batchSize = 1000
    ): void {
        $offset = 0;

        do {
            $response = $this->getTemplates(
                count: $batchSize,
                offset: $offset,
                type: $type
            );
            if (!empty($response['templates'])) {
                $callback($response['templates']);
            }
            $totalItems = $response['total_items'] ?? $batchSize + $offset;
            $offset += $batchSize;
        } while ($totalItems > $offset);
    }

    /**
     * @param string $type
     * @param int|null $loopLimit
     * @return array
     * @throws GuzzleException
     */
    public function getAllTemplates(string $type = 'user', ?int $loopLimit = null): array
    {
        $templates = [];
        $loops = 0;

        $this->getAllTemplatesAndProcess(
            callback: function ($batch) use (&$templates, &$loops, $loopLimit) {
                if (is_null($loopLimit) || $loops < $loopLimit) {
                    $templates = [...$templates, ...$batch];
                    $loops++;
                }
            },
            type: $type,
            batchSize: 1000
        );

        return [
            'total_items' => count($templates),
            'templates' => $templates
        ];
    }
}
