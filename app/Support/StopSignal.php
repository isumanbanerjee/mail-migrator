<?php
declare(strict_types=1);

namespace App\Support;

final class StopSignal extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("stop: {$reason}");
    }
}
