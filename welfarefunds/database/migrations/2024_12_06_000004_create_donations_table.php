<?php  

use Illuminate\Database\Migrations\Migration;  
use Illuminate\Database\Schema\Blueprint;  
use Illuminate\Support\Facades\Schema;  

return new class extends Migration  
{  
    public function up()  
    {  
        Schema::create('donations', function (Blueprint $table) {  
            $table->id();  
            $table->string('receipt_number')->unique();  
            $table->string('name');  
            $table->string('house_name');  
            $table->string('phone', 15);  
            $table->integer('amount');  
            $table->string('payment_type');  
            $table->string('transaction_id')->nullable();  
            
            // Foreign keys  
            $table->foreignId('collector_id')->constrained('users')->onDelete('restrict');  
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');  
            
            $table->timestamps();  
            $table->softDeletes();  
            
            // Indexes  
            $table->index('receipt_number');  
            $table->index('payment_type');  
            $table->index('created_at');  
            $table->index(['collector_id', 'unit_id']);  
            $table->index(['unit_id', 'created_at']);  
            $table->fullText(['name', 'house_name']);  
        });  
    }  

    public function down()  
    {  
        Schema::dropIfExists('donations');  
    }  
};  