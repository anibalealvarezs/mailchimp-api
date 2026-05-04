<?php

namespace Anibalealvarezs\MailchimpApi\Services\Transactional;

use Anibalealvarezs\ApiSkeleton\Clients\NoAuthClient;
use Anibalealvarezs\MailchimpApi\Support\MailchimpErrorClassifier;
use GuzzleHttp\Exception\GuzzleException;

class TransactionalApi extends NoAuthClient
{
    /**
     * @param string $apiKey
     * @param \GuzzleHttp\Client|null $guzzleClient
     * @throws GuzzleException
     */
    public function __construct(
        string $apiKey,
        ?\GuzzleHttp\Client $guzzleClient = null,
    ) {
        parent::__construct(
            baseUrl: 'https://mandrillapp.com/api/1.0/',
            defaultHeaders: [
                'Content-Type' => 'application/json',
                'key' => $apiKey,
            ],
            guzzleClient: $guzzleClient,
        );

        $this->setResponseErrorDetector('message');
        $this->setErrorMessageParser(fn ($data) => $data['message'] ?? json_encode($data));
        $this->setRateLimitDetector([MailchimpErrorClassifier::class, 'isRetryable']);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function ping(): array
    {
        $response = $this->performRequest(
            method: "POST",
            endpoint: "users/ping",
        );
        // Return response
        return json_decode($response->getBody()->getContents(), true);
    }
}
