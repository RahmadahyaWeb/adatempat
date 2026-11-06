<?php

namespace Tests\Feature\Xendit;

use App\Services\Xendit\XenditClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class XenditClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.xendit.secret_key' => 'xnd_development_test',
            'services.xendit.base_url' => 'https://api.xendit.co',
        ]);
    }

    public function test_create_qris_sends_the_documented_v3_contract(): void
    {
        Http::fake([
            'api.xendit.co/v3/payment_requests' => Http::response([
                'payment_request_id' => 'pr-123',
                'status' => 'REQUIRES_ACTION',
                'actions' => [
                    ['type' => 'PRESENT_TO_CUSTOMER', 'descriptor' => 'QR_STRING', 'value' => '0002010102'],
                ],
            ], 201),
        ]);

        $result = (new XenditClient)->createQris(
            'ADT-1',
            150000,
            CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC'),
            'Sewa lapangan',
            'biz-venue-1',
        );

        $this->assertSame('pr-123', $result['payment_request_id']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v3/payment_requests'
            && $request->method() === 'POST'
            && $request->hasHeader('api-version', '2024-11-11')
            && $request->hasHeader('for-user-id', 'biz-venue-1')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_test:'))
            && $request['reference_id'] === 'ADT-1'
            && $request['type'] === 'PAY'
            && $request['country'] === 'ID'
            && $request['currency'] === 'IDR'
            && $request['request_amount'] === 150000
            && $request['capture_method'] === 'AUTOMATIC'
            && $request['channel_code'] === 'QRIS'
            && $request['channel_properties']['expires_at'] === '2026-11-05T10:00:00Z');
    }

    public function test_missing_secret_key_throws_before_calling_xendit(): void
    {
        config(['services.xendit.secret_key' => null]);

        $this->expectException(RuntimeException::class);

        (new XenditClient)->createQris(
            'ADT-2',
            10000,
            CarbonImmutable::now()->addMinutes(10),
        );
    }

    public function test_refund_uses_payment_id_and_api_version(): void
    {
        Http::fake([
            'api.xendit.co/v3/refunds' => Http::response([
                'refund_id' => 'rf-123',
                'status' => 'PENDING',
            ], 200),
        ]);

        $result = (new XenditClient)->createRefund('py-123', 'ADT-REFUND-1', 50000, 'biz-venue-1');

        $this->assertSame('PENDING', $result['status']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v3/refunds'
            && $request->hasHeader('api-version', '2024-11-11')
            && $request->hasHeader('for-user-id', 'biz-venue-1')
            && $request['reference_id'] === 'ADT-REFUND-1'
            && $request['payment_id'] === 'py-123'
            && $request['amount'] === 50000
            && $request['reason'] === 'REQUESTED_BY_CUSTOMER');
    }

    public function test_simulate_posts_the_amount(): void
    {
        Http::fake([
            'api.xendit.co/v3/payment_requests/pr-123/simulate' => Http::response([
                'status' => 'PENDING',
            ], 200),
        ]);

        $result = (new XenditClient)->simulatePayment('pr-123', 150000);

        $this->assertSame('PENDING', $result['status']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v3/payment_requests/pr-123/simulate'
            && $request->method() === 'POST'
            && $request['amount'] === 150000);
    }

    public function test_test_mode_sub_account_uses_country_and_corporation(): void
    {
        Http::fake([
            'api.xendit.co/v3/accounts' => Http::response([
                'id' => 'biz-venue-1',
                'status' => 'LIVE',
            ], 201),
        ]);

        $result = (new XenditClient)->createTestSubAccount('Venue Test', 'venue@example.com', 'ID');

        $this->assertSame('LIVE', $result['status']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v3/accounts'
            && $request['name'] === 'Venue Test'
            && $request['email'] === 'venue@example.com'
            && $request['identity']['country_of_incorporation'] === 'ID'
            && $request['identity']['entity_type'] === 'CORPORATION');
    }
}
