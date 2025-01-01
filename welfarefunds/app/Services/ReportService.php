<?php  

namespace App\Services\Reports;  

use App\Enums\OrganizationLevel;  
use App\Models\User;  
use App\Models\Donation;  
use Illuminate\Support\Facades\DB;   
use Carbon\Carbon;  

class ReportService  
{  
    public function getCollectionSummary(  
        User $user,  
        ?string $startDate = null,  
        ?string $endDate = null  
    ): array {  
        $level = $user->getOrganizationLevel();  
        $query = Donation::query();  

        // Apply organization level filters  
        switch ($level) {  
            case OrganizationLevel::STATE:  
                $query->whereHas('unit.localBody.mandalam.district', function ($q) use ($user) {  
                    $q->where('state_id', $user->state_id);  
                });  
                break;  
            case OrganizationLevel::DISTRICT:  
                $query->whereHas('unit.localBody.mandalam', function ($q) use ($user) {  
                    $q->where('district_id', $user->district_id);  
                });  
                break;  
            case OrganizationLevel::MANDALAM:  
                $query->whereHas('unit.localBody', function ($q) use ($user) {  
                    $q->where('mandalam_id', $user->mandalam_id);  
                });  
                break;  
            case OrganizationLevel::LOCALBODY:  
                $query->whereHas('unit', function ($q) use ($user) {  
                    $q->where('localbody_id', $user->localbody_id);  
                });  
                break;  
            case OrganizationLevel::UNIT:  
                $query->where('unit_id', $user->unit_id);  
                break;  
        }  

        // Apply date filters  
        if ($startDate) {  
            $query->whereDate('created_at', '>=', $startDate);  
        }  
        if ($endDate) {  
            $query->whereDate('created_at', '<=', $endDate);  
        }  

        return [  
            'summary' => $this->getSummaryStats($query),  
            'target_achievement' => $this->calculateTargetAchievement($level, $user),  
            'level_wise_summary' => $this->getLevelWiseSummary($query, $level)  
        ];  
    }  

    private function getLevelWiseSummary($query, OrganizationLevel $level): array  
    {  
        $groupBy = match($level) {  
            OrganizationLevel::STATE => 'unit.localBody.mandalam.district_id',  
            OrganizationLevel::DISTRICT => 'unit.localBody.mandalam_id',  
            OrganizationLevel::MANDALAM => 'unit.localbody_id',  
            OrganizationLevel::LOCALBODY => 'unit_id',  
            OrganizationLevel::UNIT => 'collector_id',  
        };  

        return $query->select([  
            DB::raw("$groupBy as group_id"),  
            DB::raw('COUNT(*) as total_donations'),  
            DB::raw('SUM(amount) as total_amount')  
        ])  
        ->groupBy($groupBy)  
        ->get()  
        ->toArray();  
    }  


    private function calculateTargetAchievement(OrganizationLevel $level, User $user): array  
    {  
        // Start with units query to get target amounts  
        $unitsQuery = Unit::query();  
        $donationsQuery = Donation::query();  

        // Apply filters based on organization level  
        switch ($level) {  
            case OrganizationLevel::STATE:  
                $unitsQuery->whereHas('localBody.mandalam.district', function ($q) use ($user) {  
                    $q->where('state_id', $user->state_id);  
                });  
                $donationsQuery->whereHas('unit.localBody.mandalam.district', function ($q) use ($user) {  
                    $q->where('state_id', $user->state_id);  
                });  
                break;  

            case OrganizationLevel::DISTRICT:  
                $unitsQuery->whereHas('localBody.mandalam', function ($q) use ($user) {  
                    $q->where('district_id', $user->district_id);  
                });  
                $donationsQuery->whereHas('unit.localBody.mandalam', function ($q) use ($user) {  
                    $q->where('district_id', $user->district_id);  
                });  
                break;  

            case OrganizationLevel::MANDALAM:  
                $unitsQuery->whereHas('localBody', function ($q) use ($user) {  
                    $q->where('mandalam_id', $user->mandalam_id);  
                });  
                $donationsQuery->whereHas('unit.localBody', function ($q) use ($user) {  
                    $q->where('mandalam_id', $user->mandalam_id);  
                });  
                break;  

            case OrganizationLevel::LOCALBODY:  
                $unitsQuery->where('localbody_id', $user->localbody_id);  
                $donationsQuery->whereHas('unit', function ($q) use ($user) {  
                    $q->where('localbody_id', $user->localbody_id);  
                });  
                break;  

            case OrganizationLevel::UNIT:  
                $unitsQuery->where('id', $user->unit_id);  
                $donationsQuery->where('unit_id', $user->unit_id);  
                break;  
        }  

        // Get total target amount  
        $totalTarget = $unitsQuery->sum('target_amount');  

        // Get collection statistics  
        $collectionStats = $donationsQuery  
            ->select([  
                DB::raw('SUM(amount) as total_collected'),  
                DB::raw('SUM(CASE WHEN payment_type = "CASH" THEN amount ELSE 0 END) as cash_collected'),  
                DB::raw('SUM(CASE WHEN payment_type = "ONLINE" THEN amount ELSE 0 END) as online_collected'),  
                DB::raw('COUNT(*) as total_donations'),  
                DB::raw('COUNT(DISTINCT collector_id) as total_collectors'),  
                DB::raw('AVG(amount) as average_donation')  
            ])  
            ->first();  

        // Get daily collection trend for the last 7 days  
        $dailyTrend = $donationsQuery  
            ->select([  
                DB::raw('DATE(created_at) as date'),  
                DB::raw('SUM(amount) as amount'),  
                DB::raw('COUNT(*) as count')  
            ])  
            ->where('created_at', '>=', Carbon::now()->subDays(7))  
            ->groupBy('date')  
            ->orderBy('date')  
            ->get();  

        // Get top performing units  
        $topUnits = $donationsQuery  
            ->select([  
                'unit_id',  
                DB::raw('SUM(amount) as collected_amount'),  
                DB::raw('COUNT(*) as donation_count')  
            ])  
            ->with('unit:id,name,target_amount,localbody_id')  
            ->groupBy('unit_id')  
            ->orderByDesc('collected_amount')  
            ->limit(5)  
            ->get()  
            ->map(function ($unit) {  
                $targetAmount = $unit->unit->target_amount;  
                return [  
                    'unit_name' => $unit->unit->name,  
                    'collected_amount' => $unit->collected_amount,  
                    'target_amount' => $targetAmount,  
                    'achievement_percentage' => $targetAmount > 0   
                        ? round(($unit->collected_amount / $targetAmount) * 100, 2)  
                        : 0,  
                    'donation_count' => $unit->donation_count  
                ];  
            });  

        // Calculate time-based achievements  
        $timeBasedStats = $this->calculateTimeBasedStats($donationsQuery);  

        return [  
            'overall' => [  
                'target_amount' => $totalTarget,  
                'total_collected' => $collectionStats->total_collected ?? 0,  
                'achievement_percentage' => $totalTarget > 0   
                    ? round(($collectionStats->total_collected / $totalTarget) * 100, 2)  
                    : 0,  
                'remaining_amount' => $totalTarget - ($collectionStats->total_collected ?? 0)  
            ],  
            'collection_stats' => [  
                'cash_collected' => $collectionStats->cash_collected ?? 0,  
                'online_collected' => $collectionStats->online_collected ?? 0,  
                'total_donations' => $collectionStats->total_donations ?? 0,  
                'total_collectors' => $collectionStats->total_collectors ?? 0,  
                'average_donation' => round($collectionStats->average_donation ?? 0, 2)  
            ],  
            'daily_trend' => $dailyTrend->map(function ($day) {  
                return [  
                    'date' => $day->date,  
                    'amount' => $day->amount,  
                    'count' => $day->count  
                ];  
            }),  
            'top_units' => $topUnits,  
            'time_based_stats' => $timeBasedStats  
        ];  
    }  

    private function calculateTimeBasedStats($query): array  
    {  
        $today = Carbon::today();  
        $thisWeekStart = Carbon::now()->startOfWeek();  
        $thisMonthStart = Carbon::now()->startOfMonth();  

        return [  
            'today' => [  
                'amount' => $query->clone()  
                    ->whereDate('created_at', $today)  
                    ->sum('amount'),  
                'count' => $query->clone()  
                    ->whereDate('created_at', $today)  
                    ->count()  
            ],  
            'this_week' => [  
                'amount' => $query->clone()  
                    ->where('created_at', '>=', $thisWeekStart)  
                    ->sum('amount'),  
                'count' => $query->clone()  
                    ->where('created_at', '>=', $thisWeekStart)  
                    ->count()  
            ],  
            'this_month' => [  
                'amount' => $query->clone()  
                    ->where('created_at', '>=', $thisMonthStart)  
                    ->sum('amount'),  
                'count' => $query->clone()  
                    ->where('created_at', '>=', $thisMonthStart)  
                    ->count()  
            ]  
        ];  
    }  
 
}  