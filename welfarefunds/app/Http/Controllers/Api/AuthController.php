<?php  

namespace App\Http\Controllers\Api;  

use App\Http\Controllers\Controller;  
use App\Models\User;  
use Illuminate\Http\Request;  
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;  
use Illuminate\Validation\ValidationException;  
use Laravel\Sanctum\PersonalAccessToken; 

class AuthController extends Controller  
{  
    /**  
     * Web app authentication - verify both phone number and password  
     */  
    public function login(Request $request)  
    {  
        $request->validate([  
            'phone_number' => 'required|string',  
            'password' => 'required|string',  
        ]);  

        $user = User::where('phone', $request->phone_number)->first();  

        if (!$user || !Hash::check($request->password, $user->password)) {  
            throw ValidationException::withMessages([  
                'phone_number' => ['The provided credentials are incorrect.'],  
            ]);  
        }  

        // For web login, create a token with different name  
        $token = $user->createToken('web-app')->plainTextToken;  

        return response()->json([  
            'status' => 'success',  
            'message' => 'Login successful',  
            'data' => [  
                'user' => [  
                    'id' => $user->id,  
                    'name' => $user->name,  
                    'phone' => $user->phone,  
                    'role' => $user->role->value,  
                    'organization_level' => $user->getOrganizationLevel()->value,  
                ],  
                'token' => $token  
            ]  
        ]);  
    } 
    
    public function verifyPhone(Request $request)  
    {  
        $request->validate([  
            'phone_number' => 'required|string',   
        ]);  

        $user = User::where('phone', $request->phone_number)->first();  

        if (!$user) {  
            throw ValidationException::withMessages([  
                'phone_number' => ['The provided phone number not exist.'],  
            ]);  
        }  

        return response()->json([  
            'status' => 'success',  
            'message' => 'Phone number exists',  
            'data' => [  
                'user' => [  
                    'id' => $user->id,  
                    'name' => $user->name,  
                    'phone' => $user->phone,  
                ]
            ]  
        ]);  
    } 

    public function logout(Request $request) 
    {  
        try {  
            // Check if we have a bearer token  
            $bearerToken = $request->bearerToken();  
            
            if (!$bearerToken) {  
                return response()->json([  
                    'status' => 'error',  
                    'message' => 'No token provided'  
                ], 401);  
            }  

            // Find and delete the token  
            $token = PersonalAccessToken::findToken($bearerToken);  
            
            if ($token) {  
                // Get the user before deleting the token  
                $user = $token->tokenable;  
                
                // Delete the token  
                $token->delete();  
                
                // Delete all tokens for this user (optional)  
                // $user->tokens()->delete();  
                
                return response()->json([  
                    'status' => 'success',  
                    'message' => 'Logged out successfully'  
                ]);  
            }  

            return response()->json([  
                'status' => 'error',  
                'message' => 'Invalid token'  
            ], 401);  

        } catch (\Exception $e) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'Failed to logout',  
                'debug' => config('app.debug') ? $e->getMessage() : null  
            ], 500);  
        }  
    }  

}  