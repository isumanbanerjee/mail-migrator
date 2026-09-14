<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Encryptor;
use PHPUnit\Framework\TestCase;

final class EncryptorTest extends TestCase
{
    public function test_roundtrip(): void
    {
        $key = Encryptor::generateKey();
        $enc = new Encryptor($key);
        $cipher = $enc->encrypt('app-password-123');
        $this->assertNotSame('app-password-123', $cipher);
        $this->assertSame('app-password-123', $enc->decrypt($cipher));
    }

    public function test_tampered_ciphertext_throws(): void
    {
        $enc = new Encryptor(Encryptor::generateKey());
        $cipher = $enc->encrypt('secret');
        $this->expectException(\App\Support\DecryptException::class);
        $enc->decrypt($cipher . 'x');
    }

    public function test_wrong_key_throws(): void
    {
        $cipher = (new Encryptor(Encryptor::generateKey()))->encrypt('secret');
        $this->expectException(\App\Support\DecryptException::class);
        (new Encryptor(Encryptor::generateKey()))->decrypt($cipher);
    }
}
