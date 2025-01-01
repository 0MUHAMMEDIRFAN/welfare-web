<?php  

namespace App\Models;  

use App\Enums\PaymentType;  
use Illuminate\Database\Eloquent\Model;  
use Illuminate\Database\Eloquent\Relations\BelongsTo;  
use Illuminate\Database\Eloquent\SoftDeletes;  

class Donation extends Model  
{  
    use SoftDeletes;  

    protected $fillable = [  
        'receipt_number',  
        'name',  
        'house_name',  
        'mobile_number',  
        'amount',  
        'payment_type',  
        'transaction_id',  
        'collector_id',  
        'unit_id'  
    ];  

    protected $casts = [  
        'amount' => 'integer',  
        'payment_type' => PaymentType::class,  
        'created_at' => 'datetime',  
        'updated_at' => 'datetime',  
        'deleted_at' => 'datetime'  
    ];  

    // Define the accessor for formatted_amount  
    public function getFormattedAmountAttribute()  
    {  
        return '₹ ' . number_format($this->amount, 2);  
    }   
    /**  
     * Get the last receipt number for a collector  
     */  
    public static function getLastReceiptNumber(int $collectorId): ?string  
    {  
        return static::where('collector_id', $collectorId)  
            ->orderBy('id', 'desc')  
            ->value('receipt_number');  
    }  

    /**  
     * Generate next receipt number for collector  
     */  
    public static function generateReceiptNumber(int $collectorId): string  
    {  
        $prefix = str_pad($collectorId, 4, '0', STR_PAD_LEFT);  
        
        $lastReceiptNumber = static::getLastReceiptNumber($collectorId);  
        
        if (!$lastReceiptNumber) {  
            return $prefix . '0001';  
        }  

        $lastNumber = (int) substr($lastReceiptNumber, -4);  
        $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);  
        
        return $prefix . $nextNumber;  
    }  

    public function collector(): BelongsTo  
    {  
        return $this->belongsTo(User::class, 'collector_id');  
    }  

    public function unit(): BelongsTo  
    {  
        return $this->belongsTo(Unit::class);  
    }  

    /**  
     * Boot the model.  
     */  
    protected static function boot()  
    {  
        parent::boot();  

        static::creating(function ($donation) {  
            if (empty($donation->receipt_number)) {  
                $donation->receipt_number = static::generateReceiptNumber($donation->collector_id);  
            }  
        });  
    }  
}  