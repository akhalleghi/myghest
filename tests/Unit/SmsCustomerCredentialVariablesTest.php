<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\Sms\CustomerSmsCredentialVariables;
use App\Services\Sms\SmsTemplateRenderer;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class SmsCustomerCredentialVariablesTest extends TestCase
{
    public function test_customer_credentials_are_rendered_from_username_and_encrypted_print_password(): void
    {
        $customer = new Customer([
            'username' => 'customer-123',
            'password_print_encrypted' => Crypt::encryptString('Secret$123\\Pass'),
        ]);

        $message = app(SmsTemplateRenderer::class)->render(
            'ورود: {{username}} رمز: {{password}}',
            CustomerSmsCredentialVariables::for($customer),
        );

        self::assertSame('ورود: customer-123 رمز: Secret$123\\Pass', $message);
    }

    public function test_missing_or_unreadable_password_does_not_expose_hash_or_ciphertext(): void
    {
        $customer = new Customer([
            'username' => 'customer-123',
            'password' => 'hashed-password-value',
            'password_print_encrypted' => 'unreadable-ciphertext',
        ]);

        $message = app(SmsTemplateRenderer::class)->render(
            '{{username}}|{{password}}',
            CustomerSmsCredentialVariables::for($customer),
        );

        self::assertSame('customer-123|', $message);
        self::assertStringNotContainsString('hashed-password-value', $message);
        self::assertStringNotContainsString('unreadable-ciphertext', $message);
    }
}
