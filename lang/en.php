<?php

return [
    'driver.name' => 'SizPay',
    'service.name' => 'SizPay',
    'fields.apiKey' => 'Merchant ID',
    'fields.apiKey_help' => 'Your SizPay merchant id.',
    'fields.terminalId' => 'Terminal ID',
    'fields.terminalId_help' => 'Your SizPay terminal id.',
    'fields.userName' => 'Username (Encryption Key 1)',
    'fields.userName_help' => 'SizPay encryption key 1.',
    'fields.password' => 'Password (Encryption Key 2)',
    'fields.password_help' => 'SizPay encryption key 2.',
    'messages.SizPay requires an order id (unique per day).' => 'SizPay requires an order id (unique per day).',
    'messages.SizPay requires a callback URL.' => 'SizPay requires a callback URL.',
    'messages.SizPay requires a terminal id.' => 'SizPay requires a terminal id.',
    'messages.SizPay requires a user name.' => 'SizPay requires a user name.',
    'messages.SizPay requires a password.' => 'SizPay requires a password.',
    'messages.SizPay invalid Callback payload.' => 'SizPay invalid Callback payload.',
    'messages.Amount must be greater than zero.' => 'Amount must be greater than zero.',
    'messages.SizPay verified amount mismatch.' => 'SizPay verified amount mismatch.',
];
