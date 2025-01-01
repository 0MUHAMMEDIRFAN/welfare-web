<?php  

namespace App\Models;  

use App\Enums\UserRole;  
use Illuminate\Foundation\Auth\User as Authenticatable;  
use Illuminate\Database\Eloquent\Relations\BelongsTo;  
use Illuminate\Database\Eloquent\Relations\HasMany;  
use Laravel\Sanctum\HasApiTokens;  

class User extends Authenticatable  
{  
    use HasApiTokens;  

    protected $fillable = [  
        'name',  
        'phone',  
        'mpin',  
        'role',  
        'district_id',  
        'mandalam_id',  
        'localbody_id',  
        'unit_id',  
        'is_active'  
    ];  

    protected $hidden = [  
        'mpin',  
        'remember_token',  
    ];  

    protected $casts = [  
        'role' => UserRole::class,  
        'is_active' => 'boolean'  
    ];  

    public function district(): BelongsTo  
    {  
        return $this->belongsTo(District::class);  
    }  

    public function mandalam(): BelongsTo  
    {  
        return $this->belongsTo(Mandalam::class);  
    }  

    public function localBody(): BelongsTo  
    {  
        return $this->belongsTo(LocalBody::class, 'localbody_id');  
    }  

    public function unit(): BelongsTo  
    {  
        return $this->belongsTo(Unit::class);  
    }  

    public function donations(): HasMany  
    {  
        return $this->hasMany(Donation::class, 'collector_id');  
    }  
}  