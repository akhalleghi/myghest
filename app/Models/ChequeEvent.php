<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChequeEvent extends Model
{
    protected $guarded = ['id'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(ChequeInstrument::class, 'cheque_instrument_id');
    }

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
