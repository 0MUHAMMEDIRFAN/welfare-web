<?php  

namespace App\Http\Controllers\Api;  

use App\Http\Controllers\Controller;  
use App\Models\Unit;  
use App\Models\User;  
use App\Enums\PaymentType;  
use App\Enums\UserRole;  
use App\Enums\OrganizationLevel;  
use Illuminate\Http\Request;  
use Carbon\Carbon;  
use Illuminate\Support\Facades\DB;  

class UnitStatsController extends Controller  
{  
    /**  
     * Get unit statistics  
     */  
    public function show(Request $request)  
    {  
        if (!auth('sanctum')->check()) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'Unauthenticated',  
                'debug' => [  
                    'token_present' => $request->bearerToken() ? 'yes' : 'no',  
                    'auth_header' => $request->header('Authorization')  
                ]  
            ], 401);  
        } 
        $user = auth('sanctum')->user(); 
        $request->validate([  
            'unit_id' => 'required|exists:units,id',  
            'date' => 'nullable|date_format:d/m/Y',  
        ]);

        // Find the unit and verify access  
        $unit = Unit::with(['localBody.mandalam'])  
            ->findOrFail($request->unit_id);  
        // Check if user has access to this unit  
        if (!$this->canAccessUnit($user, $unit)) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'You are not authorized to view this unit\'s statistics'  
            ], 403);  
        }  
         
        // Get donation statistics  
        $stats = $unit->donations()  
            ->select([  
                DB::raw('COUNT(DISTINCT house_name) as total_houses'),  
                DB::raw('SUM(amount) as total_collected'),  
                DB::raw('SUM(CASE WHEN payment_type = \'' . PaymentType::CASH->value . '\' THEN amount ELSE 0 END) as cash_amount'),  
                DB::raw('SUM(CASE WHEN payment_type = \'' . PaymentType::ONLINE->value . '\' THEN amount ELSE 0 END) as online_amount'),  
            ]) 
            ->when($request->date, function ($query) use ($request) {  
                $fromDate = Carbon::createFromFormat('d/m/Y', $request->date)->startOfDay();  
                $toDate = Carbon::createFromFormat('d/m/Y', $request->date)->endOfDay();  
                return $query->whereBetween('created_at', [$fromDate, $toDate]);  
            })  
            ->first();  

        return response()->json([  
            'status' => 'success',  
            'data' => [  
                'unit' => [  
                    'name' => $unit->name,  
                    'mandalam' => $unit->localBody->mandalam->name,  
                    'local_body' => [  
                        'name' => $unit->localBody->name,  
                        'type' => $unit->localBody->type->value  
                    ]  
                ],  
                'target' => [  
                    'amount' => $unit->target_amount,  
                    'formatted' => '₹ ' . number_format($unit->target_amount, 2),  
                    'percentage_achieved' => $unit->target_amount > 0   
                        ? round(($stats->total_collected / $unit->target_amount) * 100, 2)   
                        : 0  
                ],  
                'collection' => [  
                    'total' => [  
                        'amount' => $stats->total_collected ?? 0,  
                        'formatted' => '₹ ' . number_format($stats->total_collected ?? 0, 2)  
                    ],  
                    'cash' => [  
                        'amount' => $stats->cash_amount ?? 0,  
                        'formatted' => '₹ ' . number_format($stats->cash_amount ?? 0, 2)  
                    ],  
                    'online' => [  
                        'amount' => $stats->online_amount ?? 0,  
                        'formatted' => '₹ ' . number_format($stats->online_amount ?? 0, 2)  
                    ]  
                ],  
                'houses' => [  
                    'count' => $stats->total_houses ?? 0,  
                    'average_donation' => $stats->total_houses > 0   
                        ? round($stats->total_collected / $stats->total_houses, 2)   
                        : 0  
                ],  
                'last_updated' => now()->format('Y-m-d H:i:s')  
            ]  
        ]);  
    }  

    /**  
     * Check if user has access to view unit's statistics  
     */  
    private function canAccessUnit(User $user, Unit $unit): bool  
    {  
        $userLevel = $user->role->getLevel();  
        
        return match($userLevel) {  
            OrganizationLevel::STATE => true,  
            OrganizationLevel::DISTRICT => $unit->localBody->mandalam->district_id === $user->district_id,  
            OrganizationLevel::MANDALAM => $unit->localBody->mandalam_id === $user->mandalam_id,  
            OrganizationLevel::LOCALBODY => $unit->localbody_id === $user->localbody_id,  
            OrganizationLevel::UNIT => $unit->id === $user->unit_id,  
        };  
    }  
}  