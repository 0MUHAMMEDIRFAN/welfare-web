<?php  

use Illuminate\Database\Migrations\Migration;  
use Illuminate\Database\Schema\Blueprint;  
use Illuminate\Support\Facades\Schema;  

return new class extends Migration  
{  
    public function up()  
    {  
        Schema::create('districts', function (Blueprint $table) {  
            $table->id();  
            $table->string('name');  
            $table->timestamps();  
        });  

        Schema::create('mandalams', function (Blueprint $table) {  
            $table->id();  
            $table->string('name');  
            $table->foreignId('district_id')->constrained()->onDelete('cascade');  
            $table->timestamps();  
        });  

        Schema::create('localbodies', function (Blueprint $table) {  
            $table->id();  
            $table->string('name');  
            $table->string('type');  
            $table->foreignId('mandalam_id')->constrained()->onDelete('cascade');  
            $table->timestamps();  
        });  

        Schema::create('units', function (Blueprint $table) {  
            $table->id();  
            $table->string('name');  
            $table->foreignId('localbody_id')->constrained('localbodies')->onDelete('cascade');  
            $table->integer('target_amount')->default(0);  
            $table->timestamps();  
        });  
    }  

    public function down()  
    {  
        Schema::dropIfExists('units');  
        Schema::dropIfExists('localbodies');  
        Schema::dropIfExists('mandalams');  
        Schema::dropIfExists('districts');  
    }  
};  