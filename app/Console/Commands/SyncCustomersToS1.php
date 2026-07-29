<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncCustomersToS1 extends Command
{
    protected $signature = 'customers:sync-to-s1';
    protected $description = 'Sync all S3 customers to S1 as read-only projections';

    public function handle(): int
    {
        $s1Url = rtrim(env('S1_API_BASE_URL', 'https://roaster.crema.supply'), '') . '/api/customer/sync/customer';

        $customers = Customer::all();
        $this->info("Found {$customers->count()} customers in S3.");

        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            try {
                $response = Http::timeout(5)->retry(2, 100)->post($s1Url, [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'whatsapp_number' => $customer->whatsapp_number,
                    'phone_number' => $customer->phone_number,
                ]);

                if ($response->successful()) {
                    $success++;
                } else {
                    $failed++;
                    $this->warn("Failed for {$customer->email}: HTTP {$response->status()}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Error for {$customer->email}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Sync complete: {$success} succeeded, {$failed} failed.");

        return Command::SUCCESS;
    }
}
