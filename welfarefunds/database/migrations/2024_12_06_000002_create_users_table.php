<?php  

use Illuminate\Database\Migrations\Migration;  
use Illuminate\Database\Schema\Blueprint;  
use Illuminate\Support\Facades\Schema;  

return new class extends Migration  
{  
    public function up()  
    {  
        Schema::create('users', function (Blueprint $table) {  
            $table->id();  
            $table->string('name');  
            $table->string('phone')->unique();  
            $table->string('mpin')->nullable();  
            $table->string('role');  
            $table->foreignId('district_id')->nullable()->constrained();  
            $table->foreignId('mandalam_id')->nullable()->constrained();  
            $table->foreignId('localbody_id')->nullable()->constrained('localbodies');  
            $table->foreignId('unit_id')->nullable()->constrained();  
            $table->boolean('is_active')->default(true);  
            $table->rememberToken();  
            $table->timestamps();  
            
            // Add indexes  
            $table->index('phone');  
            $table->index('role');  
            $table->index(['district_id', 'mandalam_id', 'localbody_id', 'unit_id']);  
        });  
    }  

    public function down()  
    {  
        Schema::dropIfExists('users');  
    }  
};  