<?php  

namespace Database\Seeders;  

use Illuminate\Database\Seeder;  
use App\Models\District;  
use App\Models\Mandalam;  
use App\Models\LocalBody;  
use App\Models\Unit;  
use App\Models\User;  
use App\Models\Donation;  
use App\Enums\LocalBodyType;  
use App\Enums\UserRole;  
use App\Enums\PaymentType;  
use Illuminate\Support\Facades\Hash;  

class DatabaseSeeder extends Seeder  
{  
    public function run(): void  
    {  
        // Create Super Admin  
        User::create([  
            'name' => 'Super Admin',  
            'phone' => '9876543210',  
            'mpin' => Hash::make('123456'),  
            'role' => UserRole::SUPER_ADMIN,  
            'is_active' => true  
        ]);  

        // Create State Admin  
        User::create([  
            'name' => 'State Admin',  
            'phone' => '9876543211',  
            'mpin' => Hash::make('123456'),  
            'role' => UserRole::STATE_ADMIN,  
            'is_active' => true  
        ]);  

        // Create Districts  
        $districts = ['Malappuram', 'Kozhikode', 'Kannur'];  
        
        foreach ($districts as $districtName) {  
            $district = District::create([  
                'name' => $districtName  
            ]);  

            // Create District Admin  
            User::create([  
                'name' => "District Admin {$district->name}",  
                'phone' => "97865" . str_pad($district->id, 5, '0', STR_PAD_LEFT),  
                'mpin' => Hash::make('123456'),  
                'role' => UserRole::DISTRICT_ADMIN,  
                'district_id' => $district->id,  
                'is_active' => true  
            ]);  

            // Create Mandalams  
            $mandalams = [  
                $districtName . ' North',  
                $districtName . ' South',  
                $districtName . ' East',  
                $districtName . ' West'  
            ];  

            foreach ($mandalams as $mandalamName) {  
                $mandalam = Mandalam::create([  
                    'name' => $mandalamName,  
                    'district_id' => $district->id  
                ]);  

                // Create Mandalam Admin  
                User::create([  
                    'name' => "Mandalam Admin {$mandalam->name}",  
                    'phone' => "97755" . str_pad($mandalam->id, 5, '0', STR_PAD_LEFT),  
                    'mpin' => Hash::make('123456'),  
                    'role' => UserRole::MANDALAM_ADMIN,  
                    'district_id' => $district->id,  
                    'mandalam_id' => $mandalam->id,  
                    'is_active' => true  
                ]);  

                // Create Local Bodies  
                $localBodies = [  
                    ['name' => $mandalamName . ' Municipality', 'type' => LocalBodyType::MUNICIPALITY],  
                    ['name' => $mandalamName . ' Panchayat 1', 'type' => LocalBodyType::PANCHAYAT],  
                    ['name' => $mandalamName . ' Panchayat 2', 'type' => LocalBodyType::PANCHAYAT]  
                ];  

                foreach ($localBodies as $localBody) {  
                    $lb = LocalBody::create([  
                        'name' => $localBody['name'],  
                        'type' => $localBody['type'],  
                        'mandalam_id' => $mandalam->id  
                    ]);  

                    // Create LocalBody Admin  
                    User::create([  
                        'name' => "LocalBody Admin {$lb->name}",  
                        'phone' => "97645" . str_pad($lb->id, 5, '0', STR_PAD_LEFT),  
                        'mpin' => Hash::make('123456'),  
                        'role' => UserRole::LOCALBODY_ADMIN,  
                        'district_id' => $district->id,  
                        'mandalam_id' => $mandalam->id,  
                        'localbody_id' => $lb->id,  
                        'is_active' => true  
                    ]);  

                    // Create Units  
                    $units = [  
                        ['name' => 'Unit A', 'target' => 100000],  
                        ['name' => 'Unit B', 'target' => 150000],  
                        ['name' => 'Unit C', 'target' => 200000]  
                    ];  

                    foreach ($units as $unit) {  
                        $createdUnit = Unit::create([  
                            'name' => $unit['name'],  
                            'localbody_id' => $lb->id,  
                            'target_amount' => $unit['target']  
                        ]);  

                        // Create Unit Admin  
                        User::create([  
                            'name' => "Unit Admin {$createdUnit->name}",  
                            'phone' => "97535" . str_pad($createdUnit->id, 5, '0', STR_PAD_LEFT),  
                            'mpin' => Hash::make('123456'),  
                            'role' => UserRole::UNIT_ADMIN,  
                            'district_id' => $district->id,  
                            'mandalam_id' => $mandalam->id,  
                            'localbody_id' => $lb->id,  
                            'unit_id' => $createdUnit->id,  
                            'is_active' => true  
                        ]);  

                        // Create Collectors for each Unit  
                        for ($i = 1; $i <= 3; $i++) {  
                            $collector = User::create([  
                                'name' => "Collector {$createdUnit->name}-{$i}",  
                                'phone' => "98765" . str_pad($createdUnit->id . $i, 5, '0', STR_PAD_LEFT),  
                                'mpin' => Hash::make('123456'),  
                                'role' => UserRole::COLLECTOR,  
                                'district_id' => $district->id,  
                                'mandalam_id' => $mandalam->id,  
                                'localbody_id' => $lb->id,  
                                'unit_id' => $createdUnit->id,  
                                'is_active' => true  
                            ]);  

                            // Create Donations for each Collector  
                            $this->createDonations($collector, $createdUnit);  
                        }  
                    }  
                }  
            }  
        }  
    }  

    /**  
     * Create sample donations for a collector  
     */  
    private function createDonations(User $collector, Unit $unit): void  
    {  
        $donors = [  
            ['name' => 'Mohammed', 'house' => 'Al Manzil'],  
            ['name' => 'Abdul', 'house' => 'Rose Villa'],  
            ['name' => 'Rashid', 'house' => 'Green House'],  
            ['name' => 'Fathima', 'house' => 'White House'],  
            ['name' => 'Aysha', 'house' => 'Palm View'],  
            ['name' => 'Safiya', 'house' => 'Sea View'],  
            ['name' => 'Hassan', 'house' => 'Mountain View'],  
            ['name' => 'Zainab', 'house' => 'Garden House']  
        ];  

        $amounts = [1000, 2000, 5000, 10000, 15000, 20000];  
        
        foreach ($donors as $index => $donor) {  
            $amount = $amounts[array_rand($amounts)];  
            $isOnline = rand(0, 1);  

            Donation::create([  
                'receipt_number' => Donation::generateReceiptNumber($collector->id),  
                'name' => $donor['name'],  
                'house_name' => $donor['house'],  
                'phone' => "94965" . str_pad($index + 1, 5, '0', STR_PAD_LEFT),  
                'amount' => $amount,  
                'payment_type' => $isOnline ? PaymentType::ONLINE : PaymentType::CASH,  
                'transaction_id' => $isOnline ? "TXN" . str_pad($index + 1, 6, '0', STR_PAD_LEFT) : null,  
                'collector_id' => $collector->id,  
                'unit_id' => $unit->id  
            ]);  
        }  
    }  
}