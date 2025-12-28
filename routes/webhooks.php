<?php

use Illuminate\Support\Facades\Route;
use Vherbaut\InboundWebhooks\Http\Controllers\WebhookController;

Route::post(
    config('inbound-webhooks.path', 'webhooks').'/{provider}',
    [WebhookController::class, 'handle']
)
    ->middleware(config('inbound-webhooks.middleware', ['api']))
    ->name('inbound-webhooks.handle');
