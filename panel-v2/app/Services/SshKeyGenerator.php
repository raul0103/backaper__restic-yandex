<?php

namespace App\Services;

use phpseclib3\Crypt\EC;

class SshKeyGenerator
{
    /** @return array{private: string, public: string} */
    public function generate(): array
    {
        $key = EC::createKey('Ed25519');

        return [
            'private' => $key->toString('OpenSSH'),
            'public' => $key->getPublicKey()->toString('OpenSSH'),
        ];
    }
}
