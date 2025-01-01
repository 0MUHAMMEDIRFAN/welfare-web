@extends('layouts.dashboard')  

@section('title', 'Dashboard')  

@section('content')  
<div class="bg-white shadow rounded-lg p-6">  
    <div class="border-b border-gray-200 pb-5">  
        <h3 class="text-lg leading-6 font-medium text-gray-900">  
            Welcome, {{ auth()->user()->name }}  
        </h3>  
        <p class="mt-2 max-w-4xl text-sm text-gray-500">  
            You are logged in as {{ auth()->user()->role->label() }}  
            @if(auth()->user()->unit_id)  
                in Unit: {{ auth()->user()->unit->name }}  
            @elseif(auth()->user()->localbody_id)  
                in Local Body: {{ auth()->user()->localBody->name }}  
            @elseif(auth()->user()->mandalam_id)  
                in Mandalam: {{ auth()->user()->mandalam->name }}  
            @elseif(auth()->user()->district_id)  
                in District: {{ auth()->user()->district->name }}  
            @elseif(auth()->user()->state_id)  
                in State: {{ auth()->user()->state->name }}  
            @endif  
        </p>  
    </div>  

    <div class="mt-6">  
        <!-- Add dashboard content here -->  
    </div>  
</div>  
@endsection  