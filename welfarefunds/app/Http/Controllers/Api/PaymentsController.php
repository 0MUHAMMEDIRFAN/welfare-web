<?php  

namespace App\Http\Controllers\Api;  

use App\Http\Controllers\Controller;  
use App\Models\Donation;  
use App\Models\Unit;  
use App\Models\User;  
use App\Enums\PaymentType;  
use App\Enums\UserRole;  
use App\Enums\OrganizationLevel;  
use Carbon\Carbon;  
use Illuminate\Http\Request;  
use Illuminate\Support\Facades\DB;  
use Illuminate\Database\Eloquent\Builder;  

class PaymentsController extends Controller  
{  
    /**  
     * List payments with filters  
     */  
    public function index(Request $request)  
    {  
        $request->validate([  
            'unit_id' => 'required|exists:units,id',  
            'fromDate' => 'nullable|date_format:d/m/Y',  
            'toDate' => 'nullable|date_format:d/m/Y',  
            'userId' => 'nullable|exists:users,id',  
            'search' => 'nullable|string|max:255',
            'payType' => 'nullable|string|in:CASH,ONLINE',  
        ]);  

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
        $unit = Unit::findOrFail($request->unit_id);  

        // Check if user has access to this unit based on their role  
        if (!$this->canAccessUnit($user, $unit)) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'You are not authorized to view this unit\'s payments'  
            ], 403);  
        }  

        $query = Donation::query()  
            ->where('unit_id', $request->unit_id)  
            ->select([  
                'id',  
                'name',  
                'house_name',  
                'amount',  
                'payment_type',  
                'transaction_id',
                'mobile_number',
                'receipt_number',
                'created_at',  
                'collector_id'  
            ])  
            ->with(['collector:id,name']);  

        // Apply date filters  
        if ($request->fromDate) {  
            $fromDate = Carbon::createFromFormat('d/m/Y', $request->fromDate)->startOfDay();  
            $query->where('created_at', '>=', $fromDate);  
        }  

        if ($request->toDate) {  
            $toDate = Carbon::createFromFormat('d/m/Y', $request->toDate)->endOfDay();  
            $query->where('created_at', '<=', $toDate);  
        }  

        // Apply collector filter  
        if ($request->userId) {  
            $query->where('collector_id', $request->userId);  
        }  

        // Apply search filter  
        if ($request->search) {  
            $query->where(function (Builder $query) use ($request) {  
                $query->where('name', 'like', '%' . $request->search . '%')  
                    ->orWhere('house_name', 'like', '%' . $request->search . '%');  
            });  
        }

        // Apply payType filter  
        if ($request->payType) {  
            $paymentType = PaymentType::from($request->payType);
            $query->where('payment_type', $paymentType);  
        }  

        // Get paginated results  
        $donations = $query->latest()  
            ->paginate(20);  

        // Calculate total amount and count  
        $totals = $query->getQuery()->cloneWithout(['orders', 'limit', 'offset'])  
            ->select([  
                DB::raw('COUNT(*) as total_transactions'),  
                DB::raw('SUM(amount) as total_amount')  
            ])  
            ->first();  

        return response()->json([  
            'status' => 'success',  
            'data' => [  
                'payments' => $donations->map(function ($donation) {  
                    return [  
                        'id' => $donation->id,  
                        'name' => $donation->name,  
                        'house_name' => $donation->house_name,  
                        'amount' => [  
                            'value' => $donation->amount,  
                            'formatted' => $donation->formatted_amount  
                        ],  
                        'payment_type' => [  
                            'type' => $donation->payment_type->value,  
                            'label' => $donation->payment_type->value  
                        ],  
                        'transaction_id' =>  $donation->transaction_id,
                        'mobile_number' =>  $donation->mobile_number,
                        'receipt_number' =>  $donation->receipt_number,
                        'date' => [  
                            'formatted' => $donation->created_at->format('d M, Y H:i:s'),  
                            'timestamp' => $donation->created_at->timestamp  
                        ],  
                        'collector' => [  
                            'id' => $donation->collector->id,  
                            'name' => $donation->collector->name  
                        ]  
                    ];  
                }),  
                'summary' => [  
                    'total_transactions' => $totals->total_transactions,  
                    'total_amount' => [  
                        'value' => $totals->total_amount,  
                        'formatted' => '₹ ' . number_format($totals->total_amount, 2)  
                    ]  
                ],  
                'pagination' => [  
                    'current_page' => $donations->currentPage(),  
                    'total_pages' => $donations->lastPage(),  
                    'per_page' => $donations->perPage(),  
                    'total_records' => $donations->total(),  
                    'has_more' => $donations->hasMorePages()  
                ]  
            ]  
        ]);  
    }  

    /**  
     * Check if user has access to view unit's payments  
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