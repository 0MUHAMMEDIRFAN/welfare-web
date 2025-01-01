<?php  

namespace App\Enums;  

enum OrganizationLevel: string  
{  
    case STATE = 'state';  
    case DISTRICT = 'district';  
    case MANDALAM = 'mandalam';  
    case LOCALBODY = 'localbody';  
    case UNIT = 'unit';  

    public function label(): string  
    {  
        return match($this) {  
            self::STATE => 'State',  
            self::DISTRICT => 'District',  
            self::MANDALAM => 'Mandalam',  
            self::LOCALBODY => 'Local Body',  
            self::UNIT => 'Unit',  
        };  
    }  

    public static function getHierarchy(): array  
    {  
        return [  
            self::STATE,  
            self::DISTRICT,  
            self::MANDALAM,  
            self::LOCALBODY,  
            self::UNIT  
        ];  
    }  

    public function getChildLevel(): ?OrganizationLevel  
    {  
        return match($this) {  
            self::STATE => self::DISTRICT,  
            self::DISTRICT => self::MANDALAM,  
            self::MANDALAM => self::LOCALBODY,  
            self::LOCALBODY => self::UNIT,  
            self::UNIT => null,  
        };  
    }  

    public function getParentLevel(): ?OrganizationLevel  
    {  
        return match($this) {  
            self::STATE => null,  
            self::DISTRICT => self::STATE,  
            self::MANDALAM => self::DISTRICT,  
            self::LOCALBODY => self::MANDALAM,  
            self::UNIT => self::LOCALBODY,  
        };  
    }  
} 