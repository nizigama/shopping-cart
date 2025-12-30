<?php

use App\Jobs\SendDailySalesReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily sales report to run every evening at 6 PM
Schedule::job(new SendDailySalesReportJob())->dailyAt('18:00');

Artisan::command('sales:report', function () {
    SendDailySalesReportJob::dispatch();
})->purpose('Send daily sales report');