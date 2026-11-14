<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $attributes = [
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /** The staff profile this account was created for (staff.user_id), if any. */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /**
     * Resolve SMS destinations from the person's linked school profiles.
     *
     * Student-audience notices are routed to linked guardian phones because student accounts
     * do not store phone numbers directly.
     *
     * @return list<string>
     */
    public function routeNotificationForSms(): array
    {
        $phoneNumbers = collect();
        $staffPhone = $this->staff()->where('status', 'active')->value('phone');

        if (filled($staffPhone)) {
            $phoneNumbers->push($staffPhone);
        }

        if ($this->hasRole('Parent')) {
            $phoneNumbers->push(...$this->guardians()->whereNotNull('phone')->pluck('phone'));
        }

        if ($this->hasRole('Student')) {
            $student = $this->student()
                ->where('status', 'active')
                ->with('guardians')
                ->first();

            if ($student !== null) {
                $phoneNumbers->push(...$student->guardians->pluck('phone'));
            }
        }

        return $phoneNumbers
            ->map(fn (string $phone): string => trim($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
