<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProjectTimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SendDailyNotification;

class CheckWorkHourForUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-work-hour-for-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It will check if any user work more then 8 hours today and if user found it will send notification email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        $users = DB::select('
            SELECT user_id, SUM(hours) AS total_hours
            FROM project_time_logs
            WHERE DATE(start_time) = "'. $today .'" 
            GROUP BY user_id
            HAVING total_hours >= 8');

        if (empty($users)) {
            $this->info('No users found who worked more than 8 hours today.');
            return;
        }
        foreach ($users as $user) {
            $cache_key = "notified_{$user->user_id}_{$today}";
            if (!Cache::has($cache_key)) {
                SendDailyNotification::dispatch($user->user_id);
                Cache::put($cache_key, true, now()->endOfDay());
            }
        }
    }
}
