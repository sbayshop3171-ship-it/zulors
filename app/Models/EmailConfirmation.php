<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Base\SupportsHashIds;

class EmailConfirmation extends Model
{
    use SupportsHashIds;
    
    public $fillable = ['email', 'token'];

    public $table = Table::EMAIL_CONF;
}
