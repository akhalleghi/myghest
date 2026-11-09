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
        'password_print_encrypted',
        'city',
        'address',
        'postal_code',
        'purchase_credit_ceiling_toman',
        'sms_sending_enabled',
        'overdue_sms_minimum_toman',
        'credentials_sms_sent_at',
        'mobile_verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_print_encrypted',
        'remember_token',
    ];

    protected static function booted(): void
    {
        self::updating(static function (self $customer): void {
            if ($customer->isDirty('mobile') && $customer->mobile_verified_at !== null) {
                $customer->mobile_verified_at = null;
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'membership_at' => 'date',
            'birth_date' => 'date',
            'credentials_sms_sent_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'purchase_credit_ceiling_toman' => 'integer',
            'sms_sending_enabled' => 'boolean',
            'overdue_sms_minimum_toman' => 'integer',
            'installment_reward_enabled' => 'boolean',
            'installment_reward_percent' => 'decimal:2',
            'installment_reward_after_payment_id' => 'integer',
            'password' => 'hashed',
        ];
    }

    /**
     * آیا پیامک‌های اطلاع‌رسانی/یادآور برای این مشتری مجاز است (OTP ورود جداست).
     */
    public function receivesOutboundSms(): bool
    {
        return (bool) ($this->sms_sending_enabled ?? true);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CustomerBankAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CustomerMedia::class);
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

    public function adminNotes(): HasMany
    {
        return $this->hasMany(CustomerAdminNote::class)->latest('id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
