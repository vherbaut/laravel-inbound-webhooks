<?php

use Vherbaut\InboundWebhooks\Enums\WebhookStatus;

describe('WebhookStatus Enum', function () {
    it('has correct values', function () {
        expect(WebhookStatus::Pending->value)->toBe('pending')
            ->and(WebhookStatus::Processing->value)->toBe('processing')
            ->and(WebhookStatus::Processed->value)->toBe('processed')
            ->and(WebhookStatus::Failed->value)->toBe('failed');
    });

    it('can be created from string', function () {
        expect(WebhookStatus::from('pending'))->toBe(WebhookStatus::Pending)
            ->and(WebhookStatus::from('processing'))->toBe(WebhookStatus::Processing)
            ->and(WebhookStatus::from('processed'))->toBe(WebhookStatus::Processed)
            ->and(WebhookStatus::from('failed'))->toBe(WebhookStatus::Failed);
    });

    it('returns null for invalid status with tryFrom', function () {
        expect(WebhookStatus::tryFrom('invalid'))->toBeNull();
    });

    it('throws exception for invalid status with from', function () {
        expect(fn () => WebhookStatus::from('invalid'))
            ->toThrow(ValueError::class);
    });

    it('has four cases', function () {
        expect(WebhookStatus::cases())->toHaveCount(4);
    });
});