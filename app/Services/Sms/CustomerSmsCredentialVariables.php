<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Customer;
use Illuminate\Support\Facades\Crypt;

final class CustomerSmsCredentialVariables
{
    /**
     * @return array{username:string,password:string}
     */
    public static function for(Customer $customer): array
    {
        $password = '';
        $encryptedPassword = $customer->password_print_encrypted;

        if (is_string($encryptedPassword) && trim($encryptedPassword) !== '') {
            try {
                $password = Crypt::decryptString($encryptedPassword);
            } catch (\Throwable) {
                // A password hash or an old/unreadable ciphertext cannot be sent as a plain password.
            }
        }

        return [
            'username' => trim((string) $customer->username),
            'password' => $password,
        ];
    }
}
