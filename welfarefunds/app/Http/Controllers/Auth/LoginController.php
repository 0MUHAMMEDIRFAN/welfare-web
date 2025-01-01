<?php  

namespace App\Http\Controllers\Auth;  

use App\Http\Controllers\Controller;  
use App\Models\User;  
use Illuminate\Http\Request;  
use Illuminate\Support\Facades\Auth;  
use Illuminate\Validation\ValidationException;  

class LoginController extends Controller  
{  
    public function showLoginForm()  
    {  
        if (Auth::check()) {  
            return redirect()->route('dashboard');  
        }  
        return view('auth.login');  
    }  

    public function login(Request $request)  
    {  
        $credentials = $request->validate([  
            'phone' => ['required', 'string'],  
            'password' => ['required', 'string'],  
        ]);  

        if (Auth::attempt($credentials)) {  
            $request->session()->regenerate();  
            
            return redirect()->intended('dashboard');  
        }  

        throw ValidationException::withMessages([  
            'phone' => ['The provided credentials are incorrect.'],  
        ]);  
    }  

    public function logout(Request $request)  
    {  
        Auth::logout();  
        $request->session()->invalidate();  
        $request->session()->regenerateToken();  
        
        return redirect()->route('login');  
    }  
}  