<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenericHttpSmsGateway
{
    public function send(string $recipient, string $message, string $idempotencyKey): void
    {
        $url = config('services.sms_gateway.url');

        if (! is_string($url) || blank($url)) {
            throw new RuntimeException('SMS delivery is enabled, but SMS_GATEWAY_URL is not configured.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout((int) config('services.sms_gateway.timeout', 10))
            ->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        $token = config('services.sms_gateway.token');

        if (is_string($token) && filled($token)) {
            $request = $request->withToken($token);
        }

        $payload = [
            'recipient' => $recipient,
            'message' => $message,
        ];
        $sender = config('services.sms_gateway.sender');

        if (is_string($sender) && filled($sender)) {
            $payload['sender'] = $sender;
        }

        $request->post($url, $payload)->throw();
    }
}
