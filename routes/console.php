<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('deskmint:payment-reminder')->everyThreeHours()->withoutOverlapping()->runInBackground();
Schedule::command('deskmint:payment-reminder-requests')->everyThreeHours()->withoutOverlapping()->runInBackground();

Schedule::command('deskmint:generate-recurring-invoices')->cron('*/' . config('global.recurring_invoice_interval_minutes') . ' * * * *')->withoutOverlapping()->runInBackground();