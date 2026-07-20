<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable,HasApiTokens, HasFactory,HasRoles,Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    // enc uanto se crea el user se crea automaticamente el user profile
    protected static function booted()
    {
        static::created(function ($user) {
            $user->profile()->create();
        });
    }

    public function companies()
    {
        return $this->hasMany(OrgCompanyUser::class);
    }

    public function areaAssignments()
    {
        return $this->hasMany(OrgAreaUserRole::class);
    }

    // Para saber si un usuario tiene invitaciones pendientes:
    public function invitations()
    {
        return $this->hasMany(
            OrgCompanyInvitation::class,
            'email',
            'email'
        );
    }

    public function createdEvents()
    {
        return $this->hasMany(OrgEvent::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function chatParticipants()
    {
        return $this->hasMany(ChatParticipant::class);
    }

    // Relación para traer las propiedades de este inversionista
    public function orgProperties()
    {
        return $this->hasMany(OrgProperty::class, 'user_id');
    }

    // Accessor para calcular el nivel actual dinámicamente
    public function getCurrentInvestorTierAttribute()
    {
        // 1. Contamos cuántas propiedades cerradas tiene el usuario
        $closedPropertiesCount = $this->orgProperties()->where('status', 'closed')->count();

        // 2. Buscamos el ID de la empresa de forma segura
        $companyUserPivot = $this->companies()->first();
        $companyId = $companyUserPivot ? $companyUserPivot->org_company_id : 1;

        // 3. Buscamos el nivel que le corresponde
        return OrgInvestorTier::where('org_company_id', $companyId)
            ->where('is_active', true)
            ->where('min_properties', '<=', $closedPropertiesCount)
            ->orderBy('min_properties', 'desc')
            ->first();
    }

    public function attendingEvents()
    {
        return $this->belongsToMany(OrgEvent::class, 'org_event_attendees')
            ->withTimestamps();
    }

    /**
     * Órdenes de servicio asignadas a este usuario como responsable principal (Owner)
     */
    public function assignedServiceOrders()
    {
        return $this->hasMany(OrgServiceOrder::class, 'assigned_to');
    }

    /**
     * Órdenes de servicio donde este usuario participa como seguidor/apoyo (Follower)
     */
    public function followingServiceOrders()
    {
        return $this->belongsToMany(
            OrgServiceOrder::class,
            'org_service_order_followers',
            'user_id',
            'org_service_order_id'
        )->withTimestamps();
    }

    // funcion para la parte de nivel en ventas en focado en yel pro

    // 1. Relación para traer las ventas que ha hecho este usuario (como vendedor)
    public function sellerSales()
    {
        // Traemos las ventas donde este usuario es el "seller_id"
        return $this->hasMany(OrgSale::class, 'seller_id');
    }

    // 2. Accessor para calcular el nivel actual en Yel Pro dinámicamente
    // Accessor ultra rápido para obtener el nivel actual en el frontend
    // Accessor ultra rápido y auto-reparable para obtener el nivel actual
    public function getCurrentPartnerTierAttribute()
    {
        // 1. Obtenemos el perfil del partner (aprovechando el Eager Loading del controlador)
        $profile = $this->partnerProfile;

        // Si el usuario no tiene registro de vendedor, retornamos null
        if (! $profile) {
            return null;
        }

        // 2. Lógica de Auto-Sanación: Si el partner existe pero no tiene nivel asignado
        if (empty($profile->org_partner_tier_id)) {
            // Buscamos el nivel inicial (el que empieza en 0 o el más bajo)
            $defaultTier = \App\Models\OrgPartnerTier::where('is_active', true)
                ->orderBy('min_sales_volume', 'asc')
                ->first();

            if ($defaultTier) {
                // Le asignamos el nivel en la base de datos para futuras consultas
                $profile->org_partner_tier_id = $defaultTier->id;
                $profile->save();

                // Como acabamos de asignarlo, lo devolvemos directamente
                return $defaultTier;
            }
        }

        // 3. Si ya tiene nivel asignado, simplemente lo retornamos
        return $profile->tier;
    }

    // Relación directa al perfil de vendedor/partner del usuario
    public function partnerProfile()
    {
        return $this->hasOne(OrgCompanyPartner::class, 'user_id');
    }

    // Accessor para ver el volumen total histórico de ventas
    public function getLifetimeSalesVolumeAttribute()
    {
        return $this->partnerProfile ? $this->partnerProfile->lifetime_sales_volume : 0.00;
    }
}
