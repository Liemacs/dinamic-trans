<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Today's BNM rates into the settings.
 *
 * The app does this on its own the first time a new route is opened each day, so
 * nothing breaks without a scheduler. This is for when one is running: it keeps
 * the Valori implicite screen current even on a day nobody calculates a route.
 *
 * Twice a day because BNM publishes the next day's rate in the afternoon, and a
 * morning run catches anything an overnight outage missed.
 */
Artisan::command('rates:bnm {--force : Preia chiar dacă cursurile de azi sunt deja la zi}', function () {
    $ok = \App\Support\BnmRates::refreshSettings((bool) $this->option('force'));
    $rates = \App\Support\BnmRates::provenance();

    if (! $ok) {
        $this->warn('BNM nu a răspuns. Au rămas ultimele cursuri cunoscute.');

        return 1;
    }

    $this->info(sprintf(
        'Curs BNM din %s — EUR %s, USD %s',
        $rates['date']?->format('d.m.Y') ?? '—',
        \App\Models\Setting::get('eur_rate'),
        \App\Models\Setting::get('usd_rate'),
    ));

    return 0;
})->purpose('Preia cursurile oficiale EUR și USD de la Banca Națională a Moldovei');

\Illuminate\Support\Facades\Schedule::command('rates:bnm')
    ->twiceDaily(6, 14)
    ->timezone(\App\Support\BnmRates::TIMEZONE);
