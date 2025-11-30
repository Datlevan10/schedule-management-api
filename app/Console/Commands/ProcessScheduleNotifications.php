<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ScheduleNotificationService;

class ProcessScheduleNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schedule:process-notifications 
                            {--limit=100 : Maximum notifications to process}
                            {--dry-run : Show what would be processed without sending}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending schedule notifications and send reminders';

    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(ScheduleNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Starting schedule notification processing...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No notifications will actually be sent');
        }

        try {
            // Create reminders for new slots
            $this->info('Creating reminders for new schedule slots...');
            $createdReminders = $isDryRun ? 0 : $this->notificationService->createSlotReminders();
            $this->info("Created {$createdReminders} new reminders");

            // Process pending notifications
            $this->info('Processing pending notifications...');
            $processedCount = $isDryRun ? 0 : $this->notificationService->processPendingNotifications();
            $this->info("Processed {$processedCount} notifications");

            $this->info('Schedule notification processing completed successfully');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error processing notifications: ' . $e->getMessage());
            return 1;
        }
    }
}