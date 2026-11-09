<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Sms\SmsTemplateRenderer;
use Tests\TestCase;

final class SmsTemplateAppUrlTest extends TestCase
{
    public function test_login_url_is_available_without_scenario_variables(): void
    {
        $renderer = app(SmsTemplateRenderer::class);
        self::assertSame('ورود '.route('customer.login'), $renderer->render('ورود {{ app_url }}', []));
        self::assertSame(route('customer.login'), $renderer->render('{{APP_URL}}', []));
        self::assertSame('Hello Ali', $renderer->render('Hello {{customer_name}}', ['customer_name' => 'Ali']));
    }
}
