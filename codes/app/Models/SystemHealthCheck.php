<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['system_name', 'status', 'response_time_ms', 'checked_at'])]
class SystemHealthCheck extends Model
{
    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }
}
