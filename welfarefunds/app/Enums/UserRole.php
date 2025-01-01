<?php  

namespace App\Enums;  

enum UserRole: string  
{  
    case SUPER_ADMIN = 'super_admin';  
    case STATE_ADMIN = 'state_admin';  
    case DISTRICT_ADMIN = 'district_admin';  
    case MANDALAM_ADMIN = 'mandalam_admin';  
    case LOCALBODY_ADMIN = 'localbody_admin';  
    case UNIT_ADMIN = 'unit_admin';  
    case COLLECTOR = 'collector';  

    public function getLevel(): OrganizationLevel  
    {  
        return match($this) {  
            self::SUPER_ADMIN => OrganizationLevel::STATE,  
            self::STATE_ADMIN => OrganizationLevel::STATE,  
            self::DISTRICT_ADMIN => OrganizationLevel::DISTRICT,  
            self::MANDALAM_ADMIN => OrganizationLevel::MANDALAM,  
            self::LOCALBODY_ADMIN => OrganizationLevel::LOCALBODY,  
            self::UNIT_ADMIN, self::COLLECTOR => OrganizationLevel::UNIT,  
        };  
    }  

    public function label(): string  
    {  
        return match($this) {  
            self::SUPER_ADMIN => 'Super Admin',  
            self::STATE_ADMIN => 'State Admin',  
            self::DISTRICT_ADMIN => 'District Admin',  
            self::MANDALAM_ADMIN => 'Mandalam Admin',  
            self::LOCALBODY_ADMIN => 'Local Body Admin',  
            self::UNIT_ADMIN => 'Unit Admin',  
            self::COLLECTOR => 'Collector',  
        };  
    }  

    public static function getAdminRoles(): array  
    {  
        return [  
            self::SUPER_ADMIN,  
            self::STATE_ADMIN,  
            self::DISTRICT_ADMIN,  
            self::MANDALAM_ADMIN,  
            self::LOCALBODY_ADMIN,  
            self::UNIT_ADMIN  
        ];  
    }  

    public function isAdmin(): bool  
    {  
        return in_array($this, self::getAdminRoles());  
    }  

    public function isSuperAdmin(): bool  
    {  
        return $this === self::SUPER_ADMIN;  
    }  

    public function isStateAdmin(): bool  
    {  
        return $this === self::STATE_ADMIN;  
    }  

    public function isDistrictAdmin(): bool  
    {  
        return $this === self::DISTRICT_ADMIN;  
    }  

    public function isMandalamAdmin(): bool  
    {  
        return $this === self::MANDALAM_ADMIN;  
    }  

    public function isLocalBodyAdmin(): bool  
    {  
        return $this === self::LOCALBODY_ADMIN;  
    }  

    public function isUnitAdmin(): bool  
    {  
        return $this === self::UNIT_ADMIN;  
    }  

    public function isCollector(): bool  
    {  
        return $this === self::COLLECTOR;  
    }  
} 