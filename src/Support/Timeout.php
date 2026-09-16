<?php
declare(strict_types=1);

namespace EmailMigration\Support;

use RuntimeException;

/** Thrown when a guarded operation exceeds its time budget. */
final class TimeoutException extends RuntimeException {}

final class Timeout
{
    /**
     * Run $fn, aborting with a TimeoutException if it takes longer than $seconds.
     *
     * Uses pcntl_alarm (SIGALRM) which, unlike stream_set_timeout, reliably
     * interrupts a blocking read/write on an SSL socket. If pcntl is unavailable
     * the callable simply runs without a hard timeout (graceful degradation).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function run(int $seconds, callable $fn)
    {
        if ($seconds <= 0 || !function_exists('pcntl_alarm') || !function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
            return $fn();
        }

        pcntl_async_signals(true);
        // restart_syscalls = false is essential: with the default (true) the SIGALRM
        // handler is installed with SA_RESTART, so an interrupted blocking fread()
        // auto-restarts and the timeout never breaks the hang. false makes the syscall
        // return control to PHP so the handler below can throw.
        pcntl_signal(SIGALRM, static function (): void {
            throw new TimeoutException('operation timed out');
        }, false);
        pcntl_alarm($seconds);
        try {
            return $fn();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_DFL);
        }
    }
}
