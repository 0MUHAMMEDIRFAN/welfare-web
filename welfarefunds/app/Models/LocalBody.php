<?php  

namespace App\Models;  

use App\Enums\LocalBodyType;  
use Illuminate\Database\Eloquent\Model;  
use Illuminate\Database\Eloquent\Relations\BelongsTo;  
use Illuminate\Database\Eloquent\Relations\HasMany;  

class LocalBody extends Model  
{  
    protected $table = 'localbodies';  

    protected $fillable = [  
        'name',  
        'type',  
        'mandalam_id'  
    ];  

    protected $casts = [  
        'type' => LocalBodyType::class  
    ];  

    public function mandalam(): BelongsTo  
    {  
        return $this->belongsTo(Mandalam::class);  
    }  

    public function units(): HasMany  
    {  
        return $this->hasMany(Unit::class, 'localbody_id');  
    }  

    public function users(): HasMany  
    {  
        return $this->hasMany(User::class, 'localbody_id');  
    }  
}  