<?php  

use App\Http\Controllers\Api\AuthController;  
use App\Http\Controllers\Api\MpinController;  
use App\Http\Controllers\Api\DonationController;  
use App\Http\Controllers\Api\UnitStatsController;  
use App\Http\Controllers\Api\PaymentsController;
use Illuminate\Support\Facades\Route;  

/*  
|--------------------------------------------------------------------------  
| API Routes  
|--------------------------------------------------------------------------  
*/  

// MPIN Routes  
Route::prefix('auth')->group(function () {  
    // verify phone of user exist
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone']); 
    // Set or Update MPIN  
    Route::post('/mpin', [MpinController::class, 'setMpin']);  
       
    // Forgot MPIN  
    Route::post('/forgot-mpin', [MpinController::class, 'forgotMpin']);  
    
    // Check MPIN Status  
    Route::get('/mpin/status', [MpinController::class, 'checkMpinStatus']);  
});  

// Protected routes  
Route::middleware('auth.sanctum')->group(function () {  
        // Validate MPIN  
    Route::post('/mpin/validate', [MpinController::class, 'validateMpin']);  
    Route::post('/auth/logout', [AuthController::class, 'logout']);  
    Route::post('/donation/create', [DonationController::class, 'store']);  
    Route::get('/unit-stats', [UnitStatsController::class, 'show']);  
    Route::get('/payments-list', [PaymentsController::class, 'index']);  
    
    Route::get('/dashboard', function () {  
        return response()->json([  
            'status' => 'success',  
            'message' => 'Welcome to dashboard',  
            'data' => [  
                'user' => auth()->user()  
            ]  
        ]);  
    });  
}); 
