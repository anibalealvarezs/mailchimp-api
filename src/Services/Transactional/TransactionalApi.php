<?php

namespace Anibalealvarezs\MailchimpApi\Services\Transactional;

use Anibalealvarezs\ApiSkeleton\Clients\NoAuthClient;
use GuzzleHttp\Exception\GuzzleException;

class TransactionalApi extends NoAuthClient
{
    /**
     * @param string $apiKey
     * @throws GuzzleException
     */
    public function __construct(
        string $apiKey,
    ) {
        return parent::__construct(
            baseUrl: 'https://mandrillapp.com/api/1.0/',
            defaultHeaders: [
                'Content-Type' => 'application/json',
                'key' => $apiKey,
            ],
        );
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function ping(): array {
        $response = $this->performRequest(
            method: "POST",
            endpoint: "users/ping",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }
}