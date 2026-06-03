<?php

namespace app\process;

use Workerman\Timer;
use Workerman\Worker;

class Monitor
{
    protected array $monitorDir = [];
    protected array $monitorExtensions = [];
    protected array $options = [];
    protected string $lastMtime = '';

    public function __construct(array $monitorDir, array $monitorExtensions, array $options = [])
    {
        $this->monitorDir = $monitorDir;
        $this->monitorExtensions = $monitorExtensions;
        $this->options = $options;
    }

    public function onWorkerStart(Worker $worker): void
    {
        if (($this->options['enable_file_monitor'] ?? false) === false) {
            return;
        }

        $this->lastMtime = $this->getFilesMtime();

        Timer::add(2, function () {
            $current = $this->getFilesMtime();
            if ($current !== $this->lastMtime) {
                $this->lastMtime = $current;
                echo "Monitor: file change detected, reloading...\n";
                posix_kill(posix_getppid(), SIGUSR1);
            }
        });
    }

    protected function getFilesMtime(): string
    {
        $hashes = [];
        foreach ($this->monitorDir as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $pattern = '/\.(' . implode('|', array_map('preg_quote', $this->monitorExtensions)) . ')$/i';
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match($pattern, $file->getFilename())) {
                    $hashes[] = $file->getMTime();
                }
            }
        }
        return md5(implode(',', $hashes));
    }
}
