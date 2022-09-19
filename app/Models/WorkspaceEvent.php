<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkspaceEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_code',
        'data',
    ];

    protected $casts = [
        'data'  => 'json',
    ];
}
