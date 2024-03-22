<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\DefaultSettings;
use App\Mail\LoginVerificationCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class EmailLoginChallengeCode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $attributes;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($attributes)
    {

        $settings = DefaultSettings::where('default_setting_category','email')->get();
        if ($settings) {
            foreach ($settings as $setting) {
                if ($setting->default_setting_subcategory == "support_email") {
                    $attributes['support_email'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "email_company_address") {
                    $attributes['company_address'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "email_company_name") {
                    $attributes['company_name'] = $setting->default_setting_value;
                }
            }
        }
        $this->attributes = $attributes;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('email'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 2 emails every 1 second
        Redis::throttle('emails')->allow(2)->every(1)->then(function () {

		    Mail::to($this->attributes['email'])->send(new LoginVerificationCode($this->attributes));
		    // Log::info('Emailed credentials');

        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });

    }
}
