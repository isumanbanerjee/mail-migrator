<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Support\Request;

$app = App::boot(dirname(__DIR__));
$app->handle(Request::fromGlobals())->send();
