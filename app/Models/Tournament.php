<?php

namespace App\Models;

use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property int $organizer_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string|null $rules
 * @property string|null $banner_image
 * @property int $max_teams
 * @property int $registration_fee
 * @property string|null $prize_pool
 * @property Carbon $registration_deadline
 * @property Carbon $start_date
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read User $organizer
 * @property-read Collection<int, TournamentRegistration> $registrations
 */
class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'organizer_id',
        'title',
        'slug',
        'description',
        'rules',
        'banner_image',
        'max_teams',
        'registration_fee',
        'prize_pool',
        'registration_deadline',
        'start_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_id' => 'integer',
            'organizer_id' => 'integer',
            'max_teams' => 'integer',
            'registration_fee' => 'integer',
            'registration_deadline' => 'date',
            'start_date' => 'date',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function approvedRegistrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class)->where('status', 'approved');
    }

    public function isFull(): bool
    {
        return $this->approvedRegistrations()->count() >= $this->max_teams;
    }

    public function canAcceptRegistrations(): bool
    {
        return $this->status === 'open'
            && ! $this->isFull()
            && now()->startOfDay()->lte($this->registration_deadline);
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
