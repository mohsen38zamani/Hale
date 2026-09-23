<?php

namespace App\Models;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Creative\Models\CreativeProject;
use App\Domains\Credits\Models\CreditAccount;
use App\Domains\Generations\Models\Generation;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Notifications\Notifications\VerifyEmailNotification;
use App\Domains\Products\Models\Product;
use App\Support\PhoneNormalizer;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use CanResetPasswordTrait, HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'plan_key',
    ];

    /**
     * Attributes appended to the model's serialized form.
     *
     * @var list<string>
     */
    protected $appends = ['is_banned'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'phone_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'banned_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => PhoneNormalizer::normalize($value),
        );
    }

    /**
     * Phone-only accounts have no email address, so they are exempt from
     * email verification (they already went through OTP verification).
     */
    public function hasVerifiedEmail()
    {
        return $this->email === null || ! is_null($this->email_verified_at);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function requiresEmailVerification(): bool
    {
        return $this->email !== null && $this->email_verified_at === null;
    }

    /**
     * A ban with a past banned_until has lapsed and no longer blocks the
     * account, even if the row was never cleaned up.
     */
    public function currentlyBanned(): bool
    {
        if ($this->banned_at === null) {
            return false;
        }

        if ($this->banned_until !== null && $this->banned_until->isPast()) {
            return false;
        }

        return true;
    }

    public function getIsBannedAttribute(): bool
    {
        return $this->currentlyBanned();
    }

    public function ban(?string $until, ?string $reason): void
    {
        $this->forceFill([
            'banned_at' => now(),
            'banned_until' => $until,
            'ban_reason' => $reason,
        ])->save();

        // Every outstanding session/token must die immediately.
        $this->tokens()->delete();
    }

    public function unban(): void
    {
        $this->forceFill([
            'banned_at' => null,
            'banned_until' => null,
            'ban_reason' => null,
        ])->save();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function creativeProjects(): HasMany
    {
        return $this->hasMany(CreativeProject::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CreditAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
