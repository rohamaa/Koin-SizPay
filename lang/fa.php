<?php

return [
    'driver.name' => 'سایزپی',
    'service.name' => 'سایزپی',
    'fields.apiKey' => 'شناسه مرچنت',
    'fields.apiKey_help' => 'شناسه مرچنت سایزپی.',
    'fields.terminalId' => 'شناسه ترمینال',
    'fields.terminalId_help' => 'شناسه ترمینال سایزپی.',
    'fields.userName' => 'نام کاربری (کلید رمزنگاری ۱)',
    'fields.userName_help' => 'کلید رمزنگاری ۱ سایزپی.',
    'fields.password' => 'رمز عبور (کلید رمزنگاری ۲)',
    'fields.password_help' => 'کلید رمزنگاری ۲ سایزپی.',
    'messages.SizPay requires an order id (unique per day).' => 'سایزپی نیازمند شناسه سفارش (یکتا در روز) است.',
    'messages.SizPay requires a callback URL.' => 'سایزپی نیازمند آدرس بازگشت است.',
    'messages.SizPay requires a terminal id.' => 'سایزپی نیازمند شناسه ترمینال است.',
    'messages.SizPay requires a user name.' => 'سایزپی نیازمند نام کاربری است.',
    'messages.SizPay requires a password.' => 'سایزپی نیازمند رمز عبور است.',
    'messages.SizPay invalid Callback payload.' => 'پاسخ بازگشتی سایزپی نامعتبر است.',
    'messages.Amount must be greater than zero.' => 'مبلغ باید بزرگ‌تر از صفر باشد.',
    'messages.SizPay verified amount mismatch.' => 'مبلغ تایید شده سایزپی با مبلغ ثبت‌شده همخوانی ندارد.',
];
