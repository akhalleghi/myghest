<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'customer_code',
        'username',
        'first_name',
        'last_name',
        'father_name',
        'national_id',
        'mobile',
        'mobile2',
        'phone_landline',
        'membership_at',
        'birth_date',
        'email',
        'password',
        'city',
        'address',
        'postal_code',
        'credentials_sms_sent_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'membership_at' => 'date',
            'birth_date' => 'date',
            'credentials_sms_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CustomerBankAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    public function referrers(): HasMany
    {
        return $this->hasMany(CustomerReferrer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }

    public function loanFiles(): HasMany
    {
        return $this->hasMany(CustomerLoanFile::class)->latest('id');
    }

    public function depositDeclarations(): HasMany
    {
        return $this->hasMany(CustomerDepositDeclaration::class)->latest('id');
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(CustomerLoanRequest::class)->latest('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class, 'customer_id')->latest('id');
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(CustomerLoginLog::class)->orderByDesc('logged_in_at')->orderByDesc('id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
