<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sms:send-installment-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('sms:send-cheque-reminders')->dailyAt('10:00')->timezone('Asia/Tehran')->withoutOverlapping(10);
