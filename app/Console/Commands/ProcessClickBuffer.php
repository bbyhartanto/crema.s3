<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClick;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessClickBuffer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clicks:process-buffer {--batch-size=500}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pop buffered marketplace clicks from Redis list and bulk insert into MySQL.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $items = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $rawPayload = Redis::lpop('clicks:buffer');
            if (!$rawPayload) {
                break;
            }

            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        if (!empty($items)) {
            MarketplaceClick::insert($items);
            $count = count($items);
            $this->info("Successfully inserted {$count} marketplace click logs into MySQL.");
            Log::info("ProcessClickBuffer: Flushed {$count} click records from Redis to MySQL.");
        } else {
            $this->info("No clicks found in Redis buffer.");
        }

        return Command::SUCCESS;
    }
}
