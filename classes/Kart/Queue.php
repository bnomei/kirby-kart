<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cache\FileCache;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuid;

class Queue
{
    private string $dir;

    public function __construct()
    {
        /** @var FileCache $cache */
        $cache = kirby()->cache('bnomei.kart.queue');
        $this->dir = $cache->root();
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

        $jobs = [];
        $files = [];
        $l = F::read($this->dir.'/.lock');
        $lockTime = $l !== false ? (int) $l : false;

        // only lock for n seconds max, 5 sec same as SQLite PDO::ATTR_TIMEOUT
        if ($lockTime && $lockTime + 5 > time()) {
            return;
        }

        $index = Dir::files($this->dir, absolute: true);
        if (empty($index)) {
            return;
        }

        $foundLock = false; // abort if any file is currently locked
        F::write($this->dir.'/.lock', time());

        foreach ($index as $file) {
            if (F::exists($file) && F::extension($file) === 'json') {
                $files[] = $file;

                if ($locking) {
                    $fileHandle = fopen($file, 'r');
                    if ($fileHandle && flock($fileHandle, LOCK_EX)) {
                        $fs = filesize($file);
                        if (! $fs) {
                            continue;
                        }
                        $job = fread($fileHandle, $fs);
                        flock($fileHandle, LOCK_UN);
                        fclose($fileHandle);
                    } elseif ($fileHandle) {
                        // unable to lock file
                        @fclose($fileHandle);
                        $foundLock = true;
                        break;
                    } else {
                        continue;
                    }
                } else {
                    $job = F::read($file);
                }

                if (empty($job)) {
                    continue;
                }

                $jobs[] = $job;
            }
        }

        // if encountering a single lock assume not to be the process in charge
        if ($foundLock) {
            return;
        }

        // remove jobs
        foreach ($files as $file) {
            @unlink($file);
        }

        // remove the global lock file
        @unlink($this->dir.'/.lock');

        // process all jobs now in sequence
        foreach ($jobs as $job) {
            $job = json_decode($job, true);
            if (is_array($job) && ! $this->handle($job)) {
                $this->failed($job); // no retries
            }
        }
    }

    public function handle(array $job): bool
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
