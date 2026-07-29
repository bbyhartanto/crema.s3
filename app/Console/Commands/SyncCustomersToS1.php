<?php

namespace App\Console\Commands;

use App\Jobs\PushCustomerProjectionToS1;
use App\Models\Customer;
use Illuminate\Console\Command;
use Throwable;

class SyncCustomersToS1 extends Command
{
    protected $signature = 'customers:sync-to-s1';

    protected $description = 'Sync all S3 customers to S1 as read-only projections via the internal webhook (HTTP, not direct DB)';

    public function handle(): int
    {
        $s3Customers = Customer::all();
        $this->info("Found {$s3Customers->count()} customers in S3.");

        $bar = $this->output->createProgressBar($s3Customers->count());
        $bar->start();

        $synced = 0;
        $failed = 0;

        foreach ($s3Customers as $s3c) {
            // dispatchSync runs the (queued) job inline so the operator gets a
            // real progress bar + per-customer status without a queue worker.
            try {
                PushCustomerProjectionToS1::dispatchSync($s3c);
                $synced++;
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("Failed for {$s3c->email}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Sync complete: {$synced} synced, {$failed} failed.");

        return Command::SUCCESS;
    }
}
