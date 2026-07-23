<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceClick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessCatalogChunk;
use Tests\TestCase;

class Phase3PipelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: S1-to-S3 Catalog Webhook chunks 120 products into 3 Redis jobs (50, 50, 20).
     */
    public function test_catalog_webhook_chunks_products_into_redis_jobs(): void
    {
        Queue::fake();

        $products = [];
        for ($i = 1; $i <= 120; $i++) {
            $products[] = [
                'id' => "prod-{$i}",
                'name' => "Coffee Bean {$i}",
                'price' => 50000 + $i,
            ];
        }

        $response = $this->postJson('/api/v1/webhooks/catalog', [
            'products' => $products,
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
                'total_products' => 120,
                'chunks_queued' => 3,
            ]);

        Queue::assertPushed(ProcessCatalogChunk::class, 3);
    }

    /**
     * Test 2: Click tracker route drops click payload into Redis list and redirects HTTP 302.
     */
    public function test_click_tracker_buffers_in_redis_and_redirects(): void
    {
        Redis::del('clicks:buffer');

        $response = $this->get('/api/outbound?product_id=prod-99&target=shopee&tenant_id=tenant-1&destination_url=https%3A%2F%2Fshopee.co.id%2Fproduct-123');

        $response->assertStatus(302);
        $this->assertStringContainsString('shopee.co.id', $response->headers->get('Location'));

        $this->assertEquals(1, Redis::llen('clicks:buffer'));
        $rawItem = Redis::lpop('clicks:buffer');
        $item = json_decode($rawItem, true);

        $this->assertEquals('prod-99', $item['product_id']);
        $this->assertEquals('shopee', $item['target']);
        $this->assertEquals('tenant-1', $item['tenant_id']);
    }

    /**
     * Test 3: ProcessClickBuffer command flushes Redis click buffer to MySQL.
     */
    public function test_process_click_buffer_flushes_to_mysql(): void
    {
        Redis::del('clicks:buffer');

        $clickData = [
            'product_id' => 'prod-88',
            'target' => 'tokopedia',
            'tenant_id' => 'tenant-2',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'clicked_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Redis::rpush('clicks:buffer', json_encode($clickData));

        $this->artisan('clicks:process-buffer')
            ->assertExitCode(0);

        $this->assertDatabaseHas('marketplace_clicks', [
            'product_id' => 'prod-88',
            'target' => 'tokopedia',
            'tenant_id' => 'tenant-2',
        ]);
    }

    /**
     * Test 4: Token revocation adds JTI to Redis blacklist.
     */
    public function test_logout_blacklists_jwt_in_redis(): void
    {
        $customer = Customer::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
            'whatsapp_number' => '628123456789',
        ]);

        \Laravel\Passport\Passport::actingAs($customer);

        $dummyJti = 'test-jti-12345';
        $dummyToken = 'header.' . base64_encode(json_encode(['jti' => $dummyJti, 'exp' => time() + 3600])) . '.signature';

        Redis::del("jwt:blacklist:{$dummyJti}");

        $response = $this->withHeader('Authorization', "Bearer {$dummyToken}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertEquals(1, Redis::exists("jwt:blacklist:{$dummyJti}"));
    }

    /**
     * Test 5: Registration endpoint creates Customer in S3 MySQL and issues Passport JWT.
     */
    public function test_customer_registration_creates_user_and_issues_passport_jwt(): void
    {
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'Test Personal Client',
            '--provider' => 'customers',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Roaster',
            'email' => 'alice@roaster.com',
            'password' => 'secret12345',
            'whatsapp_number' => '62899887766',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'customer' => ['id', 'name', 'email', 'whatsapp_number'],
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'alice@roaster.com',
            'name' => 'Alice Roaster',
        ]);
    }
}
