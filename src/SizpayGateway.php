<?php

namespace Rohama\Koin\Sizpay;

use GuzzleHttp\Client;
use Rohama\Koin\Support\Currency;
use Rohama\Koin\Support\PaymentStatus;
use Rohama\Koin\Exception\InvalidConfigException;
use Rohama\Koin\Exception\InvalidRequestException;
use Rohama\Koin\Exception\PaymentFailedException;
use Rohama\Koin\Exception\VerificationFailedException;
use Rohama\Koin\PaymentGateway;
use Rohama\Koin\Requests\Callback;
use Rohama\Koin\Responses\PaymentResponse;
use Rohama\Koin\Responses\ReverseResponse;
use Rohama\Koin\Responses\VerificationResponse;
use Rohama\Koin\ReverseGateway;
use Rohama\Koin\Traits\IsPayment;
use Rohama\Translator\Translator;

/**
 * SizPay gateway — REST Simple API.
 */
class SizpayGateway implements PaymentGateway, ReverseGateway
{
    use IsPayment { __construct as protected paymentConstruct; }

    public const name = 'sizpay';

    private const RESCOD_SUCCESS = '0';
    private const RESCOD_SUCCESS_ALT = '00';

    public function __construct(string $apiKey, ?string $callbackUrl = null, bool $sandbox = false, array $extra = [], bool $refundable = false)
    {
        $this->paymentConstruct($apiKey, $callbackUrl, $sandbox, $extra, $refundable);

        if (($this->extra['terminalId'] ?? '') === '') {
            throw new InvalidConfigException('SizPay requires a terminal id.', gateway: self::name);
        }

        if (($this->extra['userName'] ?? '') === '') {
            throw new InvalidConfigException('SizPay requires a user name.', gateway: self::name);
        }

        if (($this->extra['password'] ?? '') === '') {
            throw new InvalidConfigException('SizPay requires a password.', gateway: self::name);
        }

        Translator::register('Koin-' . self::name, dirname(__DIR__) . '/lang');
        $this->client = new Client([
            'base_uri' => 'https://rt.sizpay.ir',
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public static function configFields(): array
    {
        $prefix = 'Koin-' . self::name;

        return [
            [
                'key' => 'apiKey',
                'name' => Translator::trans($prefix . ':fields.apiKey'),
                'desc' => Translator::trans($prefix . ':fields.apiKey_help'),
                'type' => 'text',
                'required' => true,
                'validation' => ['required', 'string'],
                'regex' => '/^.+$/',
            ],
            [
                'key' => 'terminalId',
                'name' => Translator::trans($prefix . ':fields.terminalId'),
                'desc' => Translator::trans($prefix . ':fields.terminalId_help'),
                'type' => 'text',
                'required' => true,
                'validation' => ['required', 'string'],
                'regex' => '/^.+$/',
            ],
            [
                'key' => 'userName',
                'name' => Translator::trans($prefix . ':fields.userName'),
                'desc' => Translator::trans($prefix . ':fields.userName_help'),
                'type' => 'text',
                'required' => true,
                'validation' => ['required', 'string'],
                'regex' => '/^.+$/',
            ],
            [
                'key' => 'password',
                'name' => Translator::trans($prefix . ':fields.password'),
                'desc' => Translator::trans($prefix . ':fields.password_help'),
                'type' => 'password',
                'required' => true,
                'validation' => ['required', 'string'],
                'regex' => '/^.+$/',
            ],
        ];
    }

    public function request(
        int $amount,
        string $orderId = '',
        ?string $callbackUrl = null,
        string $description = '',
        ?string $mobile = null,
        ?string $email = null,
        ?string $currency = null,
        array $metadata = [],
        array $extra = [],
    ): PaymentResponse {
        if ($amount <= 0) {
            throw new InvalidRequestException('Amount must be greater than zero.', gateway: self::name);
        }

        if ($orderId === '') {
            throw new InvalidRequestException('SizPay requires an order id (unique per day).', gateway: self::name);
        }

        if ($callbackUrl === null && $this->callbackUrl === null) {
            throw new InvalidRequestException('SizPay requires a callback URL.', gateway: self::name);
        }

        $payload = [
            'UserName' => $this->extra['userName'],
            'Password' => $this->extra['password'],
            'MerchantID' => $this->apiKey,
            'TerminalID' => $this->extra['terminalId'],
            'Amount' => Currency::toRial($currency ?? Currency::TOMAN, $amount),
            'OrderID' => $orderId,
            'ReturnURL' => $callbackUrl ?? $this->callbackUrl,
            'InvoiceNo' => '',
            'DocDate' => '',
            'ExtraInf' => '',
            'AppExtraInf' => [
                'PayerNm' => '',
                'PayerMobile' => $mobile ?? '',
                'PayerEmail' => $email ?? '',
                'Descr' => $description,
                'PayerIP' => '',
                'PayerNCode' => '',
                'PayTitleID' => 0,
            ],
            'SignData' => '',
        ];

        if (isset($extra['payerName'])) {
            $payload['AppExtraInf']['PayerNm'] = $extra['payerName'];
        }

        if (isset($extra['payerNCode'])) {
            $payload['AppExtraInf']['PayerNCode'] = $extra['payerNCode'];
        }

        if (isset($extra['payTitleId'])) {
            $payload['AppExtraInf']['PayTitleID'] = (int) $extra['payTitleId'];
        }

        if (isset($extra['invoiceNo'])) {
            $payload['InvoiceNo'] = $extra['invoiceNo'];
        }

        if (isset($extra['docDate'])) {
            $payload['DocDate'] = $extra['docDate'];
        }

        if (isset($extra['extraInf'])) {
            $payload['ExtraInf'] = $extra['extraInf'];
        }

        $response = $this->client()->post('/api/PaymentSimple/GetTokenSimple', [
            'json' => $payload,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        $resCod = (string) ($data['ResCod'] ?? '');

        if (($resCod !== self::RESCOD_SUCCESS && $resCod !== self::RESCOD_SUCCESS_ALT) || !isset($data['Token']) || $data['Token'] === '') {
            throw new PaymentFailedException($this->resCodError($resCod, $data), (int) $resCod, raw: $data, gateway: self::name);
        }

        $token = (string) $data['Token'];

        $data['redirect'] = [
            'MerchantID' => $this->apiKey,
            'TerminalID' => $this->extra['terminalId'],
            'Token' => $token,
        ];

        return new PaymentResponse(
            $data,
            $response,
            PaymentStatus::PENDING,
            $token,
            'https://rt.sizpay.ir/Route/Payment',
            $orderId,
            isset($data['Message']) ? (string) $data['Message'] : null,
        );
    }

    public function parseCallback(array $query, array $body): Callback
    {
        $raw = array_merge($query, $body);
        $token = isset($raw['Token']) ? (string) $raw['Token'] : '';
        $orderId = isset($raw['OrderID']) ? (string) $raw['OrderID'] : '';

        if ($token === '') {
            throw new InvalidRequestException('SizPay invalid Callback payload.', gateway: self::name);
        }

        if ((string) ($raw['ResCod'] ?? '') !== self::RESCOD_SUCCESS && (string) ($raw['ResCod'] ?? '') !== self::RESCOD_SUCCESS_ALT) {
            return new Callback(PaymentStatus::FAILED, $token, $orderId, $raw);
        }

        $verified = $this->verify($token);

        return new Callback($verified->status, $token, $orderId, $raw);
    }

    public function verify(
        string $transactionId,
        string $orderId = '',
        ?int $amount = null,
        ?string $currency = null,
        array $extra = [],
    ): VerificationResponse {
        $response = $this->client()->post('/api/PaymentSimple/ConfirmSimple', [
            'json' => array_filter(array_merge([
                'UserName' => $this->extra['userName'],
                'Password' => $this->extra['password'],
                'MerchantID' => $this->apiKey,
                'TerminalID' => $this->extra['terminalId'],
                'Token' => $transactionId,
                'SignData' => '',
            ], $extra), fn ($value) => $value !== null && $value !== ''),
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        $resCod = (string) ($data['ResCod'] ?? '');

        if ($resCod !== self::RESCOD_SUCCESS && $resCod !== self::RESCOD_SUCCESS_ALT) {
            return new VerificationResponse(
                $data,
                $response,
                PaymentStatus::FAILED,
                $transactionId,
                null,
                null,
                null,
                $this->resCodError($resCod, $data),
            );
        }

        if ($amount !== null) {
            $expected = Currency::toRial($currency ?? Currency::TOMAN, $amount);
            $actual = isset($data['Amount']) ? (int) $data['Amount'] : null;

            if ($actual === null || $actual !== $expected) {
                throw new VerificationFailedException(
                    sprintf('SizPay verified amount mismatch: expected %d, got %s.', $expected, $actual === null ? 'null' : (string) $actual),
                    0,
                    raw: ['expected' => $expected, 'actual' => $actual],
                    gateway: self::name,
                );
            }
        }

        return new VerificationResponse(
            $data,
            $response,
            PaymentStatus::SUCCESS,
            $transactionId,
            isset($data['RefNo']) ? (string) $data['RefNo'] : null,
            isset($data['Amount']) ? Currency::fromRial($currency ?? Currency::TOMAN, (int) $data['Amount']) : null,
            isset($data['CardNo']) ? (string) $data['CardNo'] : null,
            isset($data['Message']) ? (string) $data['Message'] : null,
        );
    }

    public function reverse(
        string $transactionId,
        string $orderId = '',
        ?int $amount = null,
        ?string $currency = null,
        array $extra = [],
    ): ReverseResponse {
        $response = $this->client()->post('/api/PaymentSimple/ReverseSimple', [
            'json' => array_filter(array_merge([
                'UserName' => $this->extra['userName'],
                'Password' => $this->extra['password'],
                'MerchantID' => $this->apiKey,
                'TerminalID' => $this->extra['terminalId'],
                'Token' => $transactionId,
                'SignData' => '',
            ], $extra), fn ($value) => $value !== null && $value !== ''),
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        $resCod = (string) ($data['ResCod'] ?? '');

        if ($resCod !== self::RESCOD_SUCCESS && $resCod !== self::RESCOD_SUCCESS_ALT) {
            throw new PaymentFailedException($this->resCodError($resCod, $data), (int) $resCod, raw: $data, gateway: self::name);
        }

        return new ReverseResponse(
            $data,
            $response,
            PaymentStatus::REFUNDED,
            $transactionId,
            isset($data['RefNo']) ? (string) $data['RefNo'] : null,
            isset($data['Message']) ? (string) $data['Message'] : null,
        );
    }

    /**
     * Builds an error message from a SizPay failure payload.
     */
    private function resCodError(string $resCod, array $data): string
    {
        $message = isset($data['Message']) ? (string) $data['Message'] : 'Unknown error';
        return sprintf('SizPay error (ResCod %s): %s', $resCod, $message);
    }
}
