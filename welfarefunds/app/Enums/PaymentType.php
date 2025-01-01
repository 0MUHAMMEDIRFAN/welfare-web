<?php  

namespace App\Enums;  

enum PaymentType: string  
{  
    case CASH = 'CASH';  
    case ONLINE = 'ONLINE';  
}  