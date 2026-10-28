<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\ExternalSystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * External adapters that send berthing requests (P10-03).
 */
#[Table(name: 'external_systems', timestamps: false)]
#[Fillable(['code', 'name'])]
#[UseFactory(ExternalSystemFactory::class)]
class ExternalSystem extends Model
{
    /** @use HasFactory<ExternalSystemFactory> */
    use HasFactory;
}
