<?php

namespace Bnomei\Kart;

use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuid;

class Queue
{
    private string $dir;

    public function __construct()
    {
        $this->dir = kirby()->cache('bnomei.kart.queue')->root();
        if (! Dir::exists($this->dir)) {
            Dir::make($this->dir);
        }
    }

    public function push(array $job): bool
    {
        $key = date('U_u').'-'.Kart::hash(json_encode($job).Uuid::generate());
        $job['key'] = $key;
        $job['createdAt'] = date('Y-m-d H:i:s u');

        return F::write($this->dir.'/'.$key.'.json', json_encode($job));
    }

    public function failed(array $job): bool
    {
        $key = $job['key'];
        $job['failedAt'] = date('Y-m-d H:i:s u');

        return F::write($this->dir.'/failed/'.$key.'.json', json_encode($job));
    }

    public function remove(string $key): bool
    {
        return F::remove($this->dir.'/'.$key.'.json');
    }

    public function process(): void
    {
        $locking = kart()->option('queues.locking');

        foreach (Dir::files($this->dir, absolute: true) as $file) {
            // file might have been deleted between the dir index
            // and the iteration of the loop by another request
            if (F::exists($file) && F::extension($file) === 'json') {
                // be as quick as possible to remove a job to avoid issues
                // on concurrent requests. delay json decoding for that reason.

                if ($locking) {
                    $fileHandle = fopen($file, 'r');
                    if ($fileHandle && flock($fileHandle, LOCK_EX)) {
                        $job = fread($fileHandle, filesize($file));
                        flock($fileHandle, LOCK_UN);
                        fclose($fileHandle);
                        @unlink($file);
                    } else {
                        // Handle error if unable to lock file:
                        // some other process is handling it
                        // so this process will not
                        fclose($fileHandle);

                        continue;
                    }
                } else {
                    $job = F::read($file);
                    F::remove($file);
                }

                $job = json_decode($job, true);
                if (! $this->handle($job)) {
                    $this->failed($job); // no retries
                }
            }
        }
    }

    public function handle(mixed $job): bool
    {
        if ($page = A::get($job, 'page')) {
            if ($page = page($page)) { // retrieves the mutable version every time
                $method = A::get($job, 'method');
                if (! method_exists($page, $method)) {
                    return false;
                }
                $page->$method(...$job['payload']);

                return true;
            }
        }

        return false;
    }
}
