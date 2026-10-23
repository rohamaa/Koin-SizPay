<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Rohama\Koin\Exception\InvalidRequestException;
use Rohama\Koin\Exception\VerificationFailedException;
use Rohama\Koin\RefundableGateway;
use Rohama\Koin\ReverseGateway;
use Rohama\Koin\Sizpay\SizpayGateway;
use Rohama\Translator\Translator;

final class SizpayGatewayTest extends TestCase
{
    private static function sizpayGateway(array $extra = [], ?string $callbackUrl = null): SizpayGateway
    {
        return new SizpayGateway('M', $callbackUrl, false, array_merge([
            'terminalId' => 'T',
            'userName' => 'U',
            'password' => 'P',
        ], $extra));
    }

    public function testParseCallbackThrowsWithoutAToken(): void
    {
        $this->expectException(InvalidRequestException::class);
        self::sizpayGateway()->parseCallback([], []);
    }

    public function testParseCallbackMapsResCod0ToSuccess(): void
    {
        $gateway = self::sizpayGateway();
        $gateway->setClient($this->mockHttp([
            [200, ['ResCod' => '0', 'Amount' => 10000, 'RefNo' => 'ref1']],
        ]));
        $result = $gateway->parseCallback([], ['Token' => 'tk1', 'OrderID' => 'o1', 'ResCod' => '0']);

        $this->assertSame('success', $result->status);
        $this->assertSame('tk1', $result->transactionId);
    }

    public function testParseCallbackVerifiesTheCallbackResult(): void
    {
        $gateway = self::sizpayGateway();
        $gateway->setClient($this->mockHttp([
            [200, ['ResCod' => '3', 'Message' => 'transaction not found']],
        ]));
        $result = $gateway->parseCallback([], ['Token' => 'tk1', 'OrderID' => 'o1', 'ResCod' => '0']);

        $this->assertSame('failed', $result->status);
    }

    public function testParseCallbackMapsANonZeroResCodToFailed(): void
    {
        $gateway = self::sizpayGateway();
        $result = $gateway->parseCallback([], ['Token' => 'tk1', 'OrderID' => 'o1', 'ResCod' => '1']);

        $this->assertSame('failed', $result->status);
    }

    public function testVerifyReturnsSuccessWithConvertedAmount(): void
    {
        $gateway = self::sizpayGateway();
        $gateway->setClient($this->mockHttp([
            [200, ['ResCod' => '0', 'Amount' => 10000, 'RefNo' => 'ref1']],
        ]));

        $result = $gateway->verify('tk1', amount: 1000);

        $this->assertSame('success', $result->status);
        $this->assertSame('ref1', $result->referenceId);
        $this->assertSame(1000, $result->amount);
    }

    public function testVerifyThrowsOnAmountMismatch(): void
    {
        $gateway = self::sizpayGateway();
        $gateway->setClient($this->mockHttp([
            [200, ['ResCod' => '0', 'Amount' => 9999]],
        ]));

        $this->expectException(VerificationFailedException::class);
        $this->expectExceptionMessage('amount mismatch');
        $gateway->verify('tk1', amount: 1000);
    }

    public function testSizpayDoesNotClaimToBeRefundableAndExposesReverse(): void
    {
        $gateway = self::sizpayGateway();

        $this->assertNotInstanceOf(RefundableGateway::class, $gateway);
        $this->assertInstanceOf(ReverseGateway::class, $gateway);
        $this->assertTrue(method_exists($gateway, 'reverse'));
        $this->assertFalse(method_exists($gateway, 'refund'));
    }

    public function testReverseReturnsARefundedResultForAPreConfirmCancel(): void
    {
        $gateway = self::sizpayGateway();
        $gateway->setClient($this->mockHttp([
            [200, ['ResCod' => '0', 'RefNo' => 'ref1']],
        ]));

        $result = $gateway->reverse('tk1');

        $this->assertSame('refunded', $result->status);
        $this->assertSame('ref1', $result->referenceId);
    }

    public function testRequestMapsWhitelistedExtrasOntoThePayload(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['ResCod' => '0', 'Token' => 'tk1'])),
        ]);
        $gateway = self::sizpayGateway(callbackUrl: 'https://example.com/cb');
        $gateway->setClient(new Client(['handler' => $mock]));

        $gateway->request(1000, orderId: 'o1', extra: [
            'payerName' => 'Ali',
            'payTitleId' => 7,
            'extraInf' => 'note',
        ]);

        $body = json_decode($mock->getLastRequest()->getBody()->getContents(), true);
        $this->assertSame('Ali', $body['AppExtraInf']['PayerNm']);
        $this->assertSame(7, $body['AppExtraInf']['PayTitleID']);
        $this->assertSame('note', $body['ExtraInf']);
        $this->assertSame('', $body['InvoiceNo']);
    }

    public function testConfigFieldsListsTheSizpayInputs(): void
    {
        $keys = array_map(fn ($f) => $f['key'], SizpayGateway::configFields());

        $this->assertSame(['apiKey', 'terminalId', 'userName', 'password'], $keys);
    }

    public function testLangFilesCoverTheSizpayName(): void
    {
        $this->assertSame('SizPay', Translator::trans('Koin-sizpay:driver.name'));
    }
}
