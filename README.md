# Koin — SizPay

<p align="center">
  <a href="https://rohamaa.github.io/Koin-Core/">Docs site</a> ·
  <a href="https://github.com/rohamaa/Koin-Core/blob/docs/index.md">Docs on GitHub</a>
</p>

The **SizPay** gateway driver for the Koin package — built on SizPay's REST **Simple** API (base `rt.sizpay.ir`).

### Install

```bash
composer require rohama/koin-sizpay
```

### Features

- Payment request — GetToken + redirect URL
- Verify — Confirm
- Reverse
- Callback parsing

> No official test (sandbox) environment exists for this API version; the `sandbox` flag is a no-op.

### Configuration

| Field | Description |
| --- | --- |
| `apiKey` | API key |
| `terminalId` | Terminal id |
| `userName` | User name |
| `password` | Password |

### Usage

```php
use Rohama\Koin\Sizpay\SizpayGateway;
use Rohama\Koin\Support\PaymentStatus;

$gateway = new SizpayGateway(
    'api-key',
    'https://example.com/callback',
    extra: [
        'terminalId' => '…',
        'userName'   => '…',
        'password'   => '…',
    ],
);

$payment = $gateway->request(150000, 'ORDER-1', 'Subscription purchase');

if ($payment->isSuccessful()) {
    header('Location: ' . $payment->redirectUrl);
    exit;
}

// In the callback page — parseCallback runs verify() before reporting success
$callback = $gateway->parseCallback($_GET, $_POST);
if ($callback->status === PaymentStatus::SUCCESS) {
    // Verified
}
```

> `refund()` (in gateways that offer Refund) is available only when the developer-only 5th constructor parameter (`refundable`) is enabled.

Full documentation: <https://rohamaa.github.io/Koin-Core/>