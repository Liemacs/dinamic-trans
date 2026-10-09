<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * No test reaches the network. Opening a new route asks BNM for today's
         * rates, so without this every test that touches the calculator would
         * call the real National Bank — slow, and passing or failing with its
         * uptime.
         *
         * A request nothing has faked throws, and BnmRates treats that exactly
         * like an outage: the settings' own rates stay, which is what every test
         * written before the feed assumes.
         *
         * Deliberately no default fake here. Laravel answers with the first fake
         * that matches, so one registered here would shadow a test's own — and a
         * test asserting "fetched once" would pass because the shadowing 404 had
         * tripped the failure cooldown, not because the cache worked.
         */
        Http::preventStrayRequests();
    }
}
