<?php

namespace Vherbaut\InboundWebhooks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Throwable;
use Vherbaut\InboundWebhooks\Drivers\DriverInterface;
use Vherbaut\InboundWebhooks\Drivers\DriverManager;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;
use Vherbaut\InboundWebhooks\Exceptions\UnknownProviderException;
use Vherbaut\InboundWebhooks\Jobs\ProcessWebhook;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Controller handling incoming webhook requests from external providers.
 *
 * This controller is the entry point for all webhook requests. It validates
 * the signature, stores the webhook in the database, and dispatches a job
 * for asynchronous processing. The response is returned immediately (< 100ms)
 * to satisfy provider timeout requirements.
 *
 * Route: POST /webhooks/{provider}
 */
class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param DriverManager $driverManager The driver manager for resolving provider drivers
     */
    public function __construct(
        protected DriverManager $driverManager
    ) {}

    /**
     * Handle an incoming webhook request.
     *
     * Processing flow:
     * 1. Resolve the driver for the provider
     * 2. Validate the webhook signature
     * 3. Handle special cases (e.g., Slack URL verification)
     * 4. Store the webhook in the database
     * 5. Dispatch async processing job
     * 6. Return 200 response immediately
     *
     * @param Request $request  The incoming HTTP request
     * @param string  $provider The provider name from the URL (e.g., "stripe", "github")
     *
     * @return JsonResponse|Response JSON response with status or error message
     */
    public function handle(Request $request, string $provider): JsonResponse|Response
    {
        try {
            $driver = $this->driverManager->driver($provider);

            $driver->validateSignature($request);

            if ($provider === 'slack' && $request->input('type') === 'url_verification') {
                return response()->json([
                    'challenge' => $request->input('challenge'),
                ]);
            }

            $webhook = $this->storeWebhook($request, $provider, $driver);

            ProcessWebhook::dispatch($webhook);

            return response()->json([
                'status' => 'received',
                'id' => $webhook->uuid,
            ]);
        } catch (InvalidSignatureException $e) {
            report($e);

            return response()->json([
                'error' => 'Invalid signature',
            ], 401);
        } catch (UnknownProviderException $e) {
            return response()->json([
                'error' => 'Unknown provider',
            ], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Internal error',
            ], 500);
        }
    }

    /**
     * Store the incoming webhook in the database.
     *
     * Creates an InboundWebhook record with the parsed data from the driver.
     * The payload storage can be disabled via configuration to save space.
     *
     * @param Request         $request  The incoming HTTP request
     * @param string          $provider The webhook provider name
     * @param DriverInterface $driver   The driver instance for this provider
     *
     * @return InboundWebhook The created webhook model with pending status
     */
    protected function storeWebhook(Request $request, string $provider, DriverInterface $driver): InboundWebhook
    {
        /** @var bool $storePayload */
        $storePayload = config('inbound-webhooks.storage.store_payload', true);

        /** @var InboundWebhook $webhook */
        $webhook = InboundWebhook::query()
            ->create([
                'provider' => $provider,
                'event_type' => $driver->getEventType($request),
                'external_id' => $driver->getExternalId($request),
                'headers' => $driver->getStorableHeaders($request),
                'payload' => $storePayload ? $driver->getPayload($request) : null,
                'status' => WebhookStatus::Pending,
            ]);

        return $webhook;
    }
}
