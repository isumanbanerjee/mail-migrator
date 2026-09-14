<?php
declare(strict_types=1);

namespace App\Services;

use EmailMigration\FolderMapper;

final class ConnectionTester
{
    public function __construct(private ConnectionCheckerInterface $checker) {}

    public function test(array $source, array $dest, FolderMapper $mapper): array
    {
        $src = $this->checker->check($source);
        $dst = $this->checker->check($dest);

        $map = [];
        if ($src['ok']) {
            foreach ($src['folders'] as $folder) {
                $map[$folder] = $mapper->map($folder);
            }
        }
        return ['source' => $src, 'dest' => $dst, 'folder_map' => $map];
    }
}
