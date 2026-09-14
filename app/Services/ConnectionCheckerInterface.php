<?php
declare(strict_types=1);

namespace App\Services;

interface ConnectionCheckerInterface
{
    /**
     * @param array{host:string,port:int,encryption:string,username:string,password:string} $account
     * @return array{ok:bool,folders:array<int,string>,error:?string}
     */
    public function check(array $account): array;
}
