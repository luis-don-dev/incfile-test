<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class SendTestRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:test-request {mode}'; //mode=once|multiple

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $url = 'https://atomic.incfile.com/fakepost';
        $mode = $this->argument('mode');

        if (!in_array($mode, ['once', 'multiple'])) {
            $this->warn('You specified a non recognized option!');
            return Command::INVALID;
        }

        if ($mode === 'once') {
            return $this->runRequestOnce($url);
        } else if ($mode === 'multiple') {
            return $this->runRequestMultiple($url);
        }
    }

    /**
     * Run a single request to specified url
     * This process handle question 4
     *
     * @param string $url
     * @return int
     */
    public function runRequestOnce(string $url): int
    {
        try {
            // we can retry two times and wait 50 miliseconds between each request
            $response = Http::retry(2, 50)->post($url);
        } catch (\Exception $exception) {
            $this->warn('Single request failed');

            /**
             * @todo
             * We would possibly want to create a log table, so we can store additional information
             * and the error message so dev team can debug any issues. For example if this request is meant to
             * be used to store users information we would link each request to a user.
             */

            return Command::INVALID;
        }

        if ($response->successful()) {
            $this->info('Single request successful');
            return Command::SUCCESS;
        } else {
            $this->warn('Single request failed');

            /**
             * @todo
             * We would possibly want to create a log table, so we can store additional information
             * and a custom error message so dev team can debug any issues. For example if this request is meant to
             * be used to store users information we would link each request to a user.
             */

            return Command::INVALID;
        }
    }

    /**
     * Run multiple requests to specified url
     * This process handle question 5
     *
     * @param string $url
     * @return int
     */
    public function runRequestMultiple(string $url): int
    {
        // we can run up to n number, testing with 100 for testing purposes
        $totalRequestsToRun = 100;

        $responses = Http::pool(function (Pool $pool) use ($totalRequestsToRun, $url) {
            for ($i=0; $i<$totalRequestsToRun; $i++) {
                // we can retry two times and wait 50 miliseconds between each request
                $pool->retry(2, 50)->post($url);
            }
        });

        $successfulResponses = [];
        $failedResponses = [];

        foreach ($responses as $response) {
            if ($response->successful()) {
                $successfulResponses[] = $response;
            } else {
                $failedResponses[] = $response;
            }
        }

        $this->info('Successful responses: ' . count($successfulResponses));
        $this->warn('Failed responses: ' . count($failedResponses));

        if (count($successfulResponses) > 0) {
            return Command::SUCCESS;
        }

        return Command::INVALID;

        /**
         * @todo
         *
         * We can store successful responses and failed responses in a log table.
         * Based on a given context we can be more descriptive on which information
         * we are storing on the log table. For example if this request is meant to
         * be used to send notifications to users, then we would link each request to a user
         * and later we can retry for failed notifications using a recurrent task that retries it.
         */
    }
}
