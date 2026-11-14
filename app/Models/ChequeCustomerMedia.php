<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ChequeCustomerMedia extends Model
{
    public const KINDS = CustomerMedia::KINDS;

    protected $table = 'cheque_customer_media';

    protected $fillable = ['cheque_customer_id', 'kind', 'path', 'original_name', 'mime_type', 'size_bytes'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}
