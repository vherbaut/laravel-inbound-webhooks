<?php

use Vherbaut\InboundWebhooks\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeWebhookStatus', function (string $expected) {
    return $this->value->value === $expected;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a valid Stripe signature for testing.
 */
function createStripeSignature(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

/**
 * Create a valid GitHub signature for testing.
 */
function createGitHubSignature(string $payload, string $secret): string
{
    return 'sha256=' . hash_hmac('sha256', $payload, $secret);
}

/**
 * Create a valid Slack signature for testing.
 */
function createSlackSignature(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $signatureBaseString = "v0:{$timestamp}:{$payload}";
    $signature = hash_hmac('sha256', $signatureBaseString, $secret);

    return "v0={$signature}";
}

/**
 * Create a valid Twilio signature for testing.
 */
function createTwilioSignature(string $url, array $params, string $authToken): string
{
    ksort($params);
    $dataString = $url;

    foreach ($params as $key => $value) {
        $dataString .= $key . $value;
    }

    return base64_encode(hash_hmac('sha1', $dataString, $authToken, true));
}