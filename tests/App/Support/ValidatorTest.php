<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_passes_valid_data(): void
    {
        $v = Validator::make(
            ['name' => 'Bob', 'email' => 'b@x.com', 'port' => '993', 'enc' => 'ssl'],
            ['name' => 'required', 'email' => 'required|email', 'port' => 'required|int', 'enc' => 'in:ssl,tls,none']
        );
        $this->assertTrue($v->passes());
        $this->assertSame([], $v->errors());
    }

    public function test_collects_errors(): void
    {
        $v = Validator::make(
            ['email' => 'nope', 'port' => 'abc'],
            ['name' => 'required', 'email' => 'email', 'port' => 'int']
        );
        $this->assertFalse($v->passes());
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('email', $v->errors());
        $this->assertArrayHasKey('port', $v->errors());
    }
}
