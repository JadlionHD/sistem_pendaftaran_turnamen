<?php

namespace App\Models;

use Database\Factories\TournamentRegistrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property string $team_name
 * @property string $captain_name
 * @property string $captain_whatsapp
 * @property string $captain_email
 * @property string $team_members
 * @property string $status
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament $tournament
 * @property-read User $user
 */
class TournamentRegistration extends Model
{
    /** @use HasFactory<TournamentRegistrationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tournament_id',
        'user_id',
        'team_name',
        'captain_name',
        'captain_whatsapp',
        'captain_email',
        'team_members',
        'status',
        'admin_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tournament_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Retrieve the model for a bound value, ensuring numeric ID for PostgreSQL bigint column.
     *
     * @param  Builder<static>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        if ($field === $this->getKeyName() && ! ctype_digit((string) $value)) {
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
