<?php

namespace App\Services;

use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;

class TwilioService
{
    /**
     * Generate a Twilio Video access token for a participant.
     */
    public function generateVideoToken(string $roomName, string $identity): string
    {
        $token = new AccessToken(
            config('services.twilio.account_sid'),
            config('services.twilio.api_key'),
            config('services.twilio.api_secret'),
            3600,
            $identity
        );

        $grant = new VideoGrant();
        $grant->setRoom($roomName);
        $token->addGrant($grant);

        return $token->toJWT();
    }
}
