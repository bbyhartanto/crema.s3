<?php

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pushes a read-only customer projection from S3 (identity provider) to S1
 * (resource server) over the internal webhook, replacing the legacy direct
 * MySQL write to S1's customers table.
 *
 * Payload: { id, name, email, whatsapp_number } — no auth columns, ever.
 * Signed with HMAC-SHA256 of the raw JSON body in X-Crema-Signature using the
 * shared WEBHOOK_SECRET, mirroring the catalog:sync-s3 sender contract.
 */
class PushCustomerProjectionToS1 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public Customer $customer,
    ) {}

    public function backoff(): array
    {
        return [5, 10, 30, 60, 120];
    }

    public function handle(): void
    {
        if (! $this->customer instanceof Customer || ! $this->customer->exists) {
            // Customer was deleted between dispatch and execution — nothing to sync.
            return;
        }

        $payload = [
            'id' => $this->customer->id,
            'name' => $this->customer->name,
            'email' => strtolower(trim((string) $this->customer->email)),
            'whatsapp_number' => $this->customer->whatsapp_number,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $secret = (string) config('services.crema.webhook_secret', '');

        $url = rtrim((string) config('services.s1.internal_url', ''), '/')
            .'/api/internal/webhooks/customer-sync';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Crema-Signature' => hash_hmac('sha256', $json, $secret),
            ])
                ->withBody($json, 'application/json')
                ->timeout(10)
                ->post($url);
        } catch (ConnectionException $e) {
            Log::warning("PushCustomerProjectionToS1: connection failed for {$this->customer->email}: {$e->getMessage()}");
            throw new RuntimeException('S1 customer-sync webhook unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $message = "S1 customer-sync webhook failed (HTTP {$response->status()}) for {$this->customer->email}: {$response->body()}";
            Log::warning("PushCustomerProjectionToS1: {$message}");
            throw new RuntimeException($message);
        }

        Log::info("PushCustomerProjectionToS1: synced {$this->customer->email} → {$url} ({$response->status()}).");
    }
}
