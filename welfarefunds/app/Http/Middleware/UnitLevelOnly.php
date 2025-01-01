<?php  

namespace App\Http\Middleware;  

use Closure;  
use Illuminate\Http\Request;  
use App\Enums\UserRole;  

class UnitLevelOnly  
{  
    public function handle(Request $request, Closure $next)  
    {  
        $phone = $request->input('phone') ?? $request->query('phone');  
        
        if (!$phone) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'Phone number is required'  
            ], 400);  
        }  

        // Find user by phone  
        $user = \App\Models\User::where('phone', $phone)->first();  

        if (!$user) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'User not found'  
            ], 404);  
        }  

        // Check if user is unit level (Unit Admin or Collector)  
        if (!in_array($user->role, [UserRole::UNIT_ADMIN, UserRole::COLLECTOR])) {  
            return response()->json([  
                'status' => 'error',  
                'message' => 'This feature is only available for unit level users'  
            ], 403);  
        }  

        return $next($request);  
    }  
}  