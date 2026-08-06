<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\DataLoader;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use Overblog\DataLoader\DataLoader;

/**
 * A SyncPromiseAdapter that drains pending DataLoader batch queues while
 * webonyx waits on a promise.
 *
 * webonyx's default SyncPromiseAdapter only runs its own internal queue of
 * already-scheduled `then()` callbacks (`SyncPromise::runQueue()`); it has no
 * knowledge of `overblog/dataloader-php`, whose `DataLoader::load()` calls
 * queue keys but never dispatch a batch on their own — something has to call
 * `DataLoader::await()` to trigger `batch()` and settle the promise. Without
 * this adapter, a resolver that returns a DataLoader promise (the documented
 * usage of {@see DataLoaderRegistry}) never resolves and
 * `SyncPromiseAdapter::wait()` spins forever.
 *
 * {@see \GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter::onWait()} is the
 * extension point webonyx provides for exactly this: it runs on every
 * iteration of the wait loop while a promise is still pending.
 */
final class DataLoaderPromiseAdapter extends SyncPromiseAdapter
{
    protected function onWait(Promise $promise): void
    {
        DataLoader::await();
    }
}
