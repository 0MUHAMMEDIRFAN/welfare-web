<?php  

namespace App\Models;  

use Illuminate\Database\Eloquent\Model;  
use Illuminate\Database\Eloquent\Relations\BelongsTo;  
use Illuminate\Database\Eloquent\Relations\HasMany;  

class Mandalam extends Model  
{  
    protected $fillable = [  
        'name',  
        'district_id'  
    ];  

    public function district(): BelongsTo  
    {  
        return $this->belongsTo(District::class);  
    }  

    public function localBodies(): HasMany  
    {  
        return $this->hasMany(LocalBody::class);  
    }  

    public function users(): HasMany  
    {  
        return $this->hasMany(User::class);  
    }  
} 