<?php

namespace App\Observers;

use App\Jobs\PushCustomerProjectionToS1;
use App\Models\Customer;

/**
 * Drives the S3 → S1 read-only customer projection webhook.
 *
 * Fires whenever a Customer is created or updated (one-way sync to S1).
 * The actual transport lives in PushCustomerProjectionToS1; the observer
 * only decides WHEN to dispatch it, keeping customer auth flows decoupled
 * from the (eventual queued) S1 call.
 */
class CustomerObserver
{
    public function saved(Customer $customer): void
    {
        // "saved" covers both create and update, including registration/login
        // flows that mutate the customer. Skip soft-deleted rows — S1's
        // webhook restores trashed projections, but a delete must not re-sync.
        if ($customer->trashed()) {
            return;
        }

        PushCustomerProjectionToS1::dispatch($customer);
    }

    public function restored(Customer $customer): void
    {
        // Soft-delete restoration is not a "save", so handle it explicitly.
        PushCustomerProjectionToS1::dispatch($customer);
    }
}
