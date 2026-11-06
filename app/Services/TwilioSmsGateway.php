<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioSmsGateway
{
    public function isConfigured(): bool
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $fromNumber = config('services.twilio.from_number');
        $messagingServiceSid = config('services.twilio.messaging_service_sid');
        $hasFromNumber = is_string($fromNumber) && filled($fromNumber);
        $hasMessagingService = is_string($messagingServiceSid)
            && preg_match('/\AMG[0-9a-fA-F]{32}\z/', $messagingServiceSid) === 1;

        return is_string($accountSid)
            && preg_match('/\AAC[0-9a-fA-F]{32}\z/', $accountSid) === 1
            && is_string($authToken)
            && filled($authToken)
            && ($hasFromNumber xor $hasMessagingService);
    }

    public function send(string $recipient, string $message): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Twilio SMS delivery is enabled, but the Twilio credentials or sender configuration is incomplete.');
        }

        $accountSid = config('services.twilio.account_sid');
        $payload = [
            'To' => $recipient,
            'Body' => $message,
        ];
        $messagingServiceSid = config('services.twilio.messaging_service_sid');

        if (filled($messagingServiceSid)) {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = config('services.twilio.from_number');
        }

        Http::acceptJson()
            ->asForm()
            ->connectTimeout(3)
            ->timeout((int) config('services.twilio.timeout', 10))
            ->withBasicAuth($accountSid, config('services.twilio.auth_token'))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload)
            ->throw();
    }
}
