<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'gender',
        'mobile',
        'access_blocked',
        'password',
        'profile_photo_path',
        'profile_thumbnail_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['profile_photo_url', 'profile_thumbnail_url', 'avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'access_blocked' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function legacyPermissions()
    {
        return $this->hasMany(UserPermission::class);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    public function getProfileThumbnailUrlAttribute(): ?string
    {
        return $this->profile_thumbnail_path
            ? Storage::disk('public')->url($this->profile_thumbnail_path)
            : null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_thumbnail_url ?: $this->profile_photo_url;
    }
}
