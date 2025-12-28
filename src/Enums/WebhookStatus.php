<?php

namespace Vherbaut\InboundWebhooks\Enums;

/**
 * Enum representing the processing status of an inbound webhook.
 *
 * Status lifecycle:
 * 1. `Pending` - Webhook received and stored, awaiting processing
 * 2. `Processing` - Job is currently processing the webhook
 * 3. `Processed` - Webhook successfully processed
 * 4. `Failed` - Processing failed after all retry attempts
 */
enum WebhookStatus: string
{
    /** Webhook is queued and waiting to be processed. */
    case Pending = 'pending';

    /** Webhook is currently being processed by a job. */
    case Processing = 'processing';

    /** Webhook has been successfully processed. */
    case Processed = 'processed';

    /** Webhook processing failed after all retry attempts. */
    case Failed = 'failed';
}
