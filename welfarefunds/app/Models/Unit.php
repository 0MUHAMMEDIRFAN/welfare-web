<?php  

namespace App\Models;  

use Illuminate\Database\Eloquent\Model;  
use Illuminate\Database\Eloquent\Relations\BelongsTo;  
use Illuminate\Database\Eloquent\Relations\HasMany;  

class Unit extends Model  
{  
    protected $fillable = [  
        'name',  
        'localbody_id',  
        'target_amount'  
    ];  

    protected $casts = [  
        'target_amount' => 'integer'  
    ];  

    public function localBody(): BelongsTo  
    {  
        return $this->belongsTo(LocalBody::class, 'localbody_id');  
    }  

    public function users(): HasMany  
    {  
        return $this->hasMany(User::class);  
    }  

    public function donations(): HasMany  
    {  
        return $this->hasMany(Donation::class);  
    }  
}  