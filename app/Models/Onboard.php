<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Onboard extends Model
{
    use HasFactory;

    public $fillable = ['user_id', 'step'];

    public $table = Table::ONBOARDINGS;

    /*
        Note that relationships between the User and Onboard tables and models have not been established.
        This decision was made intentionally, as the Onboard model will only be used during the user creation process.
        Establishing direct relationships could complicate the application structure and lead to unnecessary code clutter.
        Instead, we handle the onboarding process separately, which helps keep the code cleaner and more maintainable.

        (c) Mansur Terla. - Developer of Zulors social network script

        https://www.terla.me
    */
}
