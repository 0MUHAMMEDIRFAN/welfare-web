<?php  

namespace App\Http\Controllers\Api;  

use App\Http\Controllers\Controller;  
use App\Models\State;
use App\Models\District;
use App\Models\Mandalam;
use App\Models\LocalBody;
use App\Models\Unit;
use App\Models\User;  
use App\Models\Donation; 
use Illuminate\Http\Request;  
use Illuminate\Support\Facades\Hash;  
use Illuminate\Validation\ValidationException;  
use Illuminate\Validation\Rules\Enum;  
use Laravel\Sanctum\PersonalAccessToken; 
use Illuminate\Support\Facades\Log;  

class MpinController extends Controller  
{  
    /**  
     * Set or Update MPIN  
     */  
    public function setMpin(Request $request)  
    {  
        $request->validate([  
            'phoneNo' => 'required|string',  
            'mpin' => 'required|string|size:4',  
        ]);  

        $user = User::where('phone', $request->phoneNo)->first();  

        if (!$user) {  
            throw ValidationException::withMessages([  
                'phoneNo' => ['User not found with this phone number.'],  
            ]);  
        }  

        // Update MPIN  
        $user->mpin = Hash::make($request->mpin);  
        $user->save();  

        // Revoke all existing tokens  
        $user->tokens()->delete();  

        // Create new token  
        $token = $user->createToken('mobile-app')->plainTextToken;  

        return response()->json([  
            'status' => 'success',  
            'message' => 'MPIN set successfully',  
            'data' => $this->getUserDetails($user),   
            'token' => $token  
        ]);  
    }  

    /**  
     * Validate MPIN  
     */  
    public function validateMpin(Request $request)  
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

        try { 

            $request->validate([  
                'phoneNo' => 'required|string',  
                'mpin' => 'required|string|size:4',  
            ]); 
                             
            if ($user->phone != $request->phoneNo)
                throw ValidationException::withMessages([  
                    'phone' => ['Invalid login phone number'],  
            ]); 

            if (!$user || !$user->mpin || !Hash::check($request->mpin, $user->mpin)) {  
                throw ValidationException::withMessages([  
                    'mpin' => ['Invalid MPIN or token'],  
                ]);  
            }  
            // Verify current token belongs to user  
            $tokenExists = $user->tokens()  
                ->where('token', hash('sha256', $request->bearerToken()))  
                ->exists();    

            // Revoke current token  
            $user->tokens()  
                ->where('token', hash('sha256', $request->bearerToken()))  
                ->delete();  

            // Create new token  
            $newToken = $user->createToken('mobile-app')->plainTextToken;  

            return response()->json([  
                'status' => 'success',  
                'message' => 'MPIN validated',  
                'data' => $this->getUserDetails($user), 
                'newToken' => $newToken  
            ]);  
        } catch (\Exception $e) {  
            Log::error('Failed to validate mpin', [  
                'error' => $e->getMessage(),  
                'trace' => $e->getTraceAsString()  
            ]);  

            return response()->json([  
                'status' => 'error',  
                'message' => 'Failed to validate mpin',  
                'debug' => config('app.debug') ? [  
                    'message' => $e->getMessage(),  
                    'file' => $e->getFile(),  
                    'line' => $e->getLine()  
                ] : null  
            ], 500);  
        }  

    }  

    /**  
     * Forgot MPIN  
     */  
    public function forgotMpin(Request $request)  
    {  
        $request->validate([  
            'phoneNo' => 'required|string',  
        ]);  

        $user = User::where('phone', $request->phoneNo)->first();  

        if (!$user) {  
            throw ValidationException::withMessages([  
                'phoneNo' => ['User not found with this phone number.'],  
            ]);  
        }  

        // Reset MPIN  
        $user->mpin = null;  
        $user->save();  

        // Revoke all tokens  
        $user->tokens()->delete();  

        return response()->json([  
            'status' => 'success',  
            'message' => 'Session invalidated. Please log in again to set a new MPIN.'  
        ]);  
    }  

    /**  
     * Check MPIN Status  
     */  
    public function checkMpinStatus(Request $request)  
    {  
        $request->validate([  
            'phoneNo' => 'required|string',  
        ]);  

        $user = User::where('phone', $request->phoneNo)->first();  

        if (!$user) {  
            throw ValidationException::withMessages([  
                'phoneNo' => ['User not found with this phone number.'],  
            ]);  
        }  

        $hasMpin = !is_null($user->mpin);  

        return response()->json([  
            'status' => 'success',  
            'hasMpin' => $hasMpin,  
            'message' => $hasMpin ? 'MPIN is already set.' : 'MPIN is not set.'  
        ]);  
    }  

        /**  
     * Get detailed user information  
     */  
    private function getUserDetails(User $user): array  
    {  
        // Eager load relationships to avoid N+1 queries  
        $user->load(['unit.localBody.mandalam']);  

        return [  
            'user' => [  
                'id' => $user->id,  
                'name' => $user->name,  
                'phone' => $user->phone,  
                'role' => $user->role->value  
            ],  
            'unit' => [  
                'id' => $user->unit_id,  
                'name' => $user->unit->name  
            ],  
            'local_body' => [  
                'id' => $user->unit->localBody->id,  
                'name' => $user->unit->localBody->name,  
                'type' => $user->unit->localBody->type->value  
            ],  
            'mandalam' => [  
                'id' => $user->unit->localBody->mandalam->id,  
                'name' => $user->unit->localBody->mandalam->name  
            ],  
            'collection' => [  
                'last_receipt_number' => Donation::getLastReceiptNumber($user->id),  
                'total_collected' => $this->getUserCollectionStats($user)  
            ]  
        ];  
    }  

    /**  
     * Get user's collection statistics  
     */  
    private function getUserCollectionStats(User $user): array  
    {  
        $stats = Donation::where('collector_id', $user->id)  
            ->selectRaw('  
                COUNT(*) as total_donations,  
                SUM(amount) as total_amount,  
                COUNT(DISTINCT DATE(created_at)) as collection_days  
            ')  
            ->first();  

        return [  
            'total_donations' => $stats->total_donations,  
            'total_amount' => [  
                'value' => $stats->total_amount ?? 0,  
                'formatted' => '₹ ' . number_format($stats->total_amount ?? 0, 2)  
            ],  
            'collection_days' => $stats->collection_days  
        ];  
    }  
 
} 