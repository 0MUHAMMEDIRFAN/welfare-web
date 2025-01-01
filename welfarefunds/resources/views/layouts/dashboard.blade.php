<!DOCTYPE html>  
<html lang="en">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>@yield('title', 'Dashboard') - Organization Management</title>  
    <script src="https://cdn.tailwindcss.com"></script>  
</head>  
<body class="bg-gray-100">  
    <div class="min-h-screen">  
        <!-- Navigation -->  
        <nav class="bg-indigo-600">  
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">  
                <div class="flex items-center justify-between h-16">  
                    <div class="flex items-center">  
                        <div class="flex-shrink-0">  
                            <span class="text-white font-bold">{{ config('app.name') }}</span>  
                        </div>  
                    </div>  
                    <div class="flex items-center">  
                        <div class="ml-4 flex items-center md:ml-6">  
                            <div class="ml-3 relative">  
                                <div class="flex items-center">  
                                    <span class="text-white mr-4">{{ auth()->user()->name }}</span>  
                                    <form method="POST" action="{{ route('logout') }}">  
                                        @csrf  
                                        <button type="submit" class="text-white hover:text-gray-200">  
                                            Logout  
                                        </button>  
                                    </form>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </nav>  

        <!-- Page Content -->  
        <main>  
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">  
                @yield('content')  
            </div>  
        </main>  
    </div>  
</body>  
</html>  