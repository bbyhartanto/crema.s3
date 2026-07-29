<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCustomersToS1 extends Command
{
    protected $signature = 'customers:sync-to-s1';
    protected $description = 'Sync all S3 customers to S1 as read-only projections (writes directly to S1 DB)';

    public function handle(): int
    {
        $s3Customers = Customer::all();
        $this->info("Found {$s3Customers->count()} customers in S3.");

        $bar = $this->output->createProgressBar($s3Customers->count());
        $bar->start();

        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($s3Customers as $s3c) {
            try {
                // Match by email (legacy S1 customers may have different UUIDs)
                $existing = DB::connection('s1_sync')->table('customers')
                    ->where('email', strtolower(trim($s3c->email)))
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    DB::connection('s1_sync')->table('customers')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $s3c->name,
                            'whatsapp_number' => $s3c->whatsapp_number,
                            'phone_number' => $s3c->phone_number,
                            'updated_at' => now(),
                        ]);
                    $updated++;
                } else {
                    DB::connection('s1_sync')->table('customers')->insert([
                        'id' => $s3c->id,
                        'name' => $s3c->name,
                        'email' => strtolower(trim($s3c->email)),
                        'whatsapp_number' => $s3c->whatsapp_number,
                        'phone_number' => $s3c->phone_number,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Failed for {$s3c->email}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Sync complete: {$created} created, {$updated} updated, {$failed} failed.");

        return Command::SUCCESS;
    }
}
