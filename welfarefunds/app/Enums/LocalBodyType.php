<?php  

namespace App\Enums;  

enum LocalBodyType: string  
{  
    case MUNICIPALITY = 'MUNICIPALITY';  
    case CORPORATION = 'CORPORATION';  
    case PANCHAYAT = 'PANCHAYAT';  

    public function label(): string  
    {  
        return match($this) {  
            self::MUNICIPALITY => 'Municipality',  
            self::CORPORATION => 'Corporation',  
            self::PANCHAYAT => 'Panchayat',  
        };  
    }  
} 