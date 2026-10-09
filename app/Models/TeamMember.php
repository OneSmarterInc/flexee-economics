<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place on a team, and the part of the company they look after.
 */
#[Fillable(['team_id', 'user_id', 'seat'])]
class TeamMember extends Model
{
    /** Seat keys and the words students see for them. */
    public const SEATS = [
        'evp' => 'EVP (overall leadership)',
        'oil_fields' => 'Oil fields',
        'refineries' => 'Refineries',
        'gas_stations' => 'Gas stations',
        'trading_finance' => 'Trading & finance',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
