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
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuid;
use Throwable;

class Queue
{
    private readonly string $dir;

    public function __construct()
    {
        /** @var FileCache $cache */
        $cache = kirby()->cache('bnomei.kart.queue');
        $this->dir = $cache->root();
        if (! Dir::exists($this->dir)) {
            Dir::make($this->dir);
        }
    }

    public function flush(): void
    {
        Dir::remove($this->dir);
        Dir::make($this->dir);
    }

    public function remove(string $key): bool
    {
        return F::remove($this->dir.'/'.$key.'.json');
    }

    public function count(bool $failed = false): int
    {
        return count(Dir::files($this->dir.($failed ? '/failed' : '')));
    }

    public function push(array $job): ?string
    {
        $key = date('U_u').'-'.Kart::hash(json_encode($job).Uuid::generate());
        $job['key'] = $key;
        $job['createdAt'] = date('Y-m-d H:i:s u');

        return F::write($this->dir.'/'.$key.'.json', json_encode($job)) ? $key : null;
    }

    public function process(bool $lock = true, bool $unlock = true): ?int
    {
        $count = 0;
        $locking = kart()->option('queues.locking');

        $jobs = [];
        $files = [];
        $l = F::read($this->dir.'/.lock');
        $lockTime = $l !== false ? (int) $l : false;

        // only lock for n seconds max, 5 sec same as SQLite PDO::ATTR_TIMEOUT
        if ($lockTime && $lockTime + 5 > time()) {
            return null;
        }

        $index = Dir::files($this->dir, absolute: true);
        if (empty($index)) {
            return 0;
        }

        $foundLock = false; // abort if any file is currently locked
        $lock && F::write($this->dir.'/.lock', time());

        foreach ($index as $file) {
            if (F::exists($file) && F::extension($file) === 'json') {
                $files[] = $file;
                $key = basename((string) $file, '.json');

                if ($locking) {
                    $fileHandle = fopen($file, 'r');
                    if ($fileHandle && flock($fileHandle, LOCK_EX | LOCK_NB, $eWouldBlock) && ! $eWouldBlock) {
                        $fs = filesize($file);
                        if (! $fs) {
                            $jobs[$key] = ''; // broken but keep around to fail later

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
                        $jobs[$key] = ''; // broken but keep around to fail later

                        continue;
                    }
                } else {
                    $job = F::read($file);
                }

                $jobs[$key] = $job;
            }
        }

        // if encountering a single lock assume not to be the process in charge
        if ($foundLock) {
            return null;
        }

        // remove jobs
        foreach ($files as $file) {
            @unlink($file);
        }

        // remove the global lock file
        $unlock && @unlink($this->dir.'/.lock');

        // process all jobs now in sequence
        foreach ($jobs as $key => $job) {
            $job = $job ? json_decode($job, true) : false;
            if (is_array($job)) {
                $success = false;
                try {
                    $success = $this->handle($job);
                } catch (Throwable) {
                    // ray($e->getMessage(), $job);
                } finally {
                    if (! $success) {
                        $this->failed($job);
                    }
                }
            } else {
                $this->failed([
                    'key' => $key,
                ]);
            }
            $count++;
        }

        return $count;
    }

    public function handle(array $job): bool
    {
        if ($page = A::get($job, 'page')) {
            return $this->handlePage(strval($page), $job);
        } elseif ($class = A::get($job, 'class')) {
            return $this->handleClass(strval($class), $job);
        }

        return false;
    }

    private function handlePage(string $page, array $job): bool
    {
        if ($page = page($page)) { // retrieves the mutable version every time
            $method = A::get($job, 'method');
            if (! method_exists($page, $method)) {
                return false;
            }
            if ($data = A::get($job, 'data')) {
                $page->$method(...$data);
            } else {
                $page->$method();
            }

            return true;
        }

        return false;
    }

    private function handleClass(string $class, array $job): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $method = A::get($job, 'method');
        if (Str::startsWith($method, '::')) {
            $method = substr((string) $method, 2);
            if ($data = A::get($job, 'data')) {
                $class::$method(...$data);

                return true;
            } else {
                $class::$method();

                return true;
            }
        } else {
            if ($props = A::get($job, 'props')) {
                $job['props'] = $props;
                $obj = new $class(...$job['props']);
            } else {
                $obj = new $class;
            }
            if ($data = A::get($job, 'data')) {
                $obj->$method(...$data);

                return true;
            } else {
                $obj->$method();
            }

            return true;
        }
    }

    public function failed(array $job): bool
    {
        $key = $job['key'];
        $job['failedAt'] = date('Y-m-d H:i:s u');

        return F::write($this->dir.'/failed/'.$key.'.json', json_encode($job));
    }
}
