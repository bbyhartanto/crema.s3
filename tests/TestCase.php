<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // S3 → S1 customer projection is dispatched by CustomerObserver on every
        // Customer save (register/login). With QUEUE_CONNECTION=sync the job runs
        // inline and would otherwise issue a real HTTP call — stub outbound HTTP
        // so tests never touch the network.
        Http::fake();
    }
}
