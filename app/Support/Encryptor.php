<?php
declare(strict_types=1);

namespace App\Support;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;

final class Encryptor
{
    private Key $key;

    public function __construct(string $asciiKey)
    {
        $this->key = Key::loadFromAsciiSafeString($asciiKey);
    }

    public static function generateKey(): string
    {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    public function encrypt(string $plain): string
    {
        return Crypto::encrypt($plain, $this->key);
    }

    public function decrypt(string $cipher): string
    {
        try {
            return Crypto::decrypt($cipher, $this->key);
        } catch (WrongKeyOrModifiedCiphertextException $e) {
            throw new DecryptException('Unable to decrypt value', 0, $e);
        }
    }
}
