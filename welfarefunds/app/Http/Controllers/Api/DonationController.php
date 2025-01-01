<?php  

namespace App\Http\Controllers\Api;  

use App\Http\Controllers\Controller;  
use App\Models\Donation;  
use App\Models\User;  
use App\Enums\PaymentType;  
use App\Enums\UserRole;  
use Illuminate\Http\Request;  
use Illuminate\Validation\ValidationException;  
use Illuminate\Validation\Rules\Enum;  
use Laravel\Sanctum\PersonalAccessToken; 
use Illuminate\Support\Facades\Log;  

class DonationController extends Controller  
{  
    /**  
     * Create a new donation  
     */  

    public function store(Request $request) 
    {  
        // Check authentication  
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
        
        // Check if user is a collector  
        if ($user->role !== UserRole::COLLECTOR) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'Only collectors can create donations',  
                'debug' => [  
                    'user_role' => $user->role->value ?? 'null'  
                ]  
            ], 403);  
        }  

        try {  
            $validated = $request->validate([  
                'name' => 'required|string|max:255',  
                'house_name' => 'required|string|max:255',  
                'mobile_number' => 'required|string|max:20',  
                'amount' => 'required|integer|min:1',  
                'payment_type' => 'required|string|in:CASH,ONLINE',  
                'transaction_id' => 'nullable|string|required_if:payment_type,ONLINE'  
            ]);  

            $donation = new Donation($validated);  
            $donation->collector_id = $user->id;  
            $donation->unit_id = $user->unit_id;  
            $donation->payment_type = PaymentType::from($validated['payment_type']);  
            $donation->save();  

            return response()->json([  
                'status' => 'success',  
                'message' => 'Donation created successfully',  
                'data' => [  
                    'donation' => $donation->load('collector', 'unit'),  
                    'receipt_number' => $donation->receipt_number  
                ]  
            ], 201);  

        } catch (\Exception $e) {  
            Log::error('Failed to create donation', [  
                'error' => $e->getMessage(),  
                'trace' => $e->getTraceAsString()  
            ]);  

            return response()->json([  
                'status' => 'error',  
                'message' => 'Failed to create donation',  
                'debug' => config('app.debug') ? [  
                    'message' => $e->getMessage(),  
                    'file' => $e->getFile(),  
                    'line' => $e->getLine()  
                ] : null  
            ], 500);  
        }  
    }  

    // public function store(Request $request)             
    // {  
    //    // Check authentication first  
    //     if (!auth()->check()) {  
    //         return response()->json([  
    //             'status' => 'error',  
    //             'message' => 'Unauthenticated'  
    //         ], 401);  
    //     } 

    //     $user = auth()->user();


    //     // Verify user is a collector  
    //     if ($user->role !== UserRole::COLLECTOR) {  
    //         return response()->json([  
    //             'status' => 'error',  
    //             'message' => 'Only collectors can create donations'  
    //         ], 403);  
    //     }  

    //     $request->validate([  
    //         'name' => 'required|string|max:255',  
    //         'house_name' => 'required|string|max:255',  
    //         'mobile_number' => 'required|string|max:15',  
    //         'amount' => 'required|integer|min:1',  
    //         'payment_type' => ['required', new Enum(PaymentType::class)],  
    //         'transaction_id' => 'nullable|string|max:255',  
    //         'collector_id' => 'required|exists:users,id',  
    //         'unit_id' => 'required|exists:units,id'  
    //     ]);  

    //     // Convert payment_type to enum  
    //     $paymentType = PaymentType::from($request->payment_type);  

    //     // Validate transaction_id requirement  
    //     if ($paymentType->requiresTransactionId() && empty($request->transaction_id)) {  
    //         throw ValidationException::withMessages([  
    //             'transaction_id' => ['Transaction ID is required for online payments.'],  
    //         ]);  
    //     }  

    //     // Verify collector is creating donation for themselves  
    //     if ($request->collector_id != $user->id) {  
    //         throw ValidationException::withMessages([  
    //             'collector_id' => ['You can only create donations for yourself.'],  
    //         ]);  
    //     }  

    //     // Verify collector belongs to the specified unit  
    //     if ($user->unit_id !== $request->unit_id) {  
    //         throw ValidationException::withMessages([  
    //             'unit_id' => ['You can only create donations for your assigned unit.'],  
    //         ]);  
    //     }  

    //     // Create donation  
    //     $donation = Donation::create([  
    //         'name' => $request->name,  
    //         'house_name' => $request->house_name,  
    //         'mobile_number' => $request->mobile_number,  
    //         'amount' => $request->amount,  
    //         'payment_type' => $paymentType,  
    //         'transaction_id' => $request->transaction_id,  
    //         'collector_id' => $user->id,  
    //         'unit_id' => $user->unit_id  
    //     ]);  

    //     return response()->json([  
    //         'status' => 'success',  
    //         'message' => 'Donation recorded successfully',  
    //         'data' => [  
    //             'donation_id' => $donation->id,  
    //             'amount' => $donation->formatted_amount,  
    //             'payment_type' => $donation->payment_type->value,  
    //             'transaction_id' => $donation->transaction_id,  
    //             'masked_mobile' => $donation->masked_mobile,  
    //             'created_at' => $donation->created_at->format('Y-m-d H:i:s')  
    //         ]  
    //     ]);  
    // }  
}  