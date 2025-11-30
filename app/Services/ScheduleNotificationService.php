<?php

namespace App\Services;

use App\Models\SmartNotification;
use App\Models\OptimizedScheduleSlot;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ScheduleNotificationService
{
    /**
     * Process pending notifications
     */
    public function processPendingNotifications(): int
    {
        $processed = 0;
        $now = Carbon::now();

        // Get notifications that should be sent
        $notifications = SmartNotification::where('status', 'pending')
            ->where('trigger_datetime', '<=', $now)
            ->with(['user', 'event'])
            ->limit(100)
            ->get();

        foreach ($notifications as $notification) {
            try {
                $this->sendNotification($notification);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to send notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);

                $notification->update([
                    'status' => 'failed',
                    'error_details' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    /**
     * Send a notification
     */
    protected function sendNotification(SmartNotification $notification): void
    {
        switch ($notification->delivery_method) {
            case 'email':
                $this->sendEmailNotification($notification);
                break;
            case 'push':
                $this->sendPushNotification($notification);
                break;
            case 'sms':
                $this->sendSmsNotification($notification);
                break;
            case 'in_app':
            default:
                $this->sendInAppNotification($notification);
                break;
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(SmartNotification $notification): void
    {
        $user = $notification->user;
        
        if (!$user->email) {
            throw new \Exception('User has no email address');
        }

        // Use Laravel's mail facade (configure mail driver in .env)
        Mail::send('emails.schedule-notification', [
            'notification' => $notification,
            'user' => $user,
            'event' => $notification->event
        ], function ($message) use ($user, $notification) {
            $message->to($user->email, $user->name)
                   ->subject($notification->title);
        });
    }

    /**
     * Send push notification (implement with FCM/OneSignal)
     */
    protected function sendPushNotification(SmartNotification $notification): void
    {
        // Implement push notification logic here
        // Example with FCM:
        /*
        $user = $notification->user;
        
        if ($user->fcm_token) {
            $fcm = new FCMService();
            $fcm->sendToDevice(
                $user->fcm_token,
                $notification->title,
                $notification->message,
                $notification->action_data
            );
        }
        */
        
        Log::info('Push notification would be sent', [
            'user_id' => $notification->user_id,
            'title' => $notification->title
        ]);
    }

    /**
     * Send SMS notification
     */
    protected function sendSmsNotification(SmartNotification $notification): void
    {
        // Implement SMS logic here (Twilio, Nexmo, etc.)
        /*
        $user = $notification->user;
        
        if ($user->phone_number) {
            $twilio = new TwilioService();
            $twilio->sendSms(
                $user->phone_number,
                $notification->message
            );
        }
        */
        
        Log::info('SMS notification would be sent', [
            'user_id' => $notification->user_id,
            'message' => $notification->message
        ]);
    }

    /**
     * Send in-app notification
     */
    protected function sendInAppNotification(SmartNotification $notification): void
    {
        // In-app notifications are already stored in database
        // Just mark as delivered for in-app display
        $notification->update(['status' => 'delivered']);
    }

    /**
     * Create reminders for optimized schedule slots
     */
    public function createSlotReminders(): int
    {
        $created = 0;
        $slots = OptimizedScheduleSlot::needingNotification()->get();

        foreach ($slots as $slot) {
            if ($this->createSlotReminder($slot)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Create reminder for a specific slot
     */
    protected function createSlotReminder(OptimizedScheduleSlot $slot): bool
    {
        try {
            $triggerTime = $slot->start_datetime->copy()
                ->subMinutes($slot->reminder_minutes_before);

            // Check if notification already exists
            $exists = SmartNotification::where('user_id', $slot->user_id)
                ->where('action_data->slot_id', $slot->id)
                ->exists();

            if ($exists) {
                return false;
            }

            SmartNotification::create([
                'user_id' => $slot->user_id,
                'event_id' => $slot->event_id,
                'type' => 'reminder',
                'subtype' => 'schedule_slot',
                'trigger_datetime' => $triggerTime,
                'scheduled_at' => $triggerTime,
                'title' => $this->generateReminderTitle($slot),
                'message' => $this->generateReminderMessage($slot),
                'action_data' => [
                    'slot_id' => $slot->id,
                    'analysis_id' => $slot->analysis_id
                ],
                'ai_generated' => true,
                'priority_level' => $this->getPriorityLevel($slot->priority),
                'delivery_method' => $this->getDeliveryMethod($slot)
            ]);

            $slot->update(['notification_sent' => true]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create slot reminder', [
                'slot_id' => $slot->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate reminder title
     */
    protected function generateReminderTitle(OptimizedScheduleSlot $slot): string
    {
        $timeUntil = $slot->start_datetime->diffForHumans();
        
        if ($slot->priority === 'critical') {
            return "⚠️ KHẨN CẤP: {$slot->task_title} {$timeUntil}";
        }
        
        if ($slot->priority === 'high') {
            return "🔔 Quan trọng: {$slot->task_title} {$timeUntil}";
        }
        
        return "📅 Nhắc nhở: {$slot->task_title} {$timeUntil}";
    }

    /**
     * Generate reminder message
     */
    protected function generateReminderMessage(OptimizedScheduleSlot $slot): string
    {
        $message = "Bạn có lịch '{$slot->task_title}' ";
        $message .= "từ {$slot->start_time} đến {$slot->end_time}";
        
        if ($slot->location) {
            $message .= " tại {$slot->location}";
        }
        
        if ($slot->task_description) {
            $message .= "\n\nChi tiết: {$slot->task_description}";
        }
        
        if ($slot->ai_reasoning) {
            $message .= "\n\n💡 Lý do sắp xếp: {$slot->ai_reasoning}";
        }
        
        return $message;
    }

    /**
     * Get delivery method based on priority
     */
    protected function getDeliveryMethod(OptimizedScheduleSlot $slot): string
    {
        if ($slot->priority === 'critical') {
            return 'push'; // Critical items get push notifications
        }
        
        if ($slot->priority === 'high') {
            return 'email'; // High priority gets email
        }
        
        return 'in_app'; // Others get in-app notifications
    }

    /**
     * Get priority level
     */
    protected function getPriorityLevel($priority): int
    {
        return match($priority) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 3
        };
    }

    /**
     * Check for schedule conflicts
     */
    public function checkConflicts(User $user, Carbon $date): array
    {
        $conflicts = [];
        
        $slots = OptimizedScheduleSlot::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('status', 'scheduled')
            ->orderBy('start_time')
            ->get();

        for ($i = 0; $i < count($slots) - 1; $i++) {
            $current = $slots[$i];
            $next = $slots[$i + 1];
            
            // Check for time overlap
            if ($current->end_datetime->gt($next->start_datetime)) {
                $conflicts[] = [
                    'type' => 'overlap',
                    'slot1' => $current->id,
                    'slot2' => $next->id,
                    'message' => "'{$current->task_title}' conflicts with '{$next->task_title}'"
                ];
            }
            
            // Check for insufficient break time
            $breakTime = $current->end_datetime->diffInMinutes($next->start_datetime);
            if ($breakTime < 5 && $breakTime >= 0) {
                $conflicts[] = [
                    'type' => 'insufficient_break',
                    'slot1' => $current->id,
                    'slot2' => $next->id,
                    'message' => "No break between '{$current->task_title}' and '{$next->task_title}'"
                ];
            }
        }
        
        return $conflicts;
    }

    /**
     * Send daily schedule summary
     */
    public function sendDailySummary(User $user): void
    {
        $tomorrow = Carbon::tomorrow();
        $slots = OptimizedScheduleSlot::where('user_id', $user->id)
            ->whereDate('date', $tomorrow)
            ->where('status', 'scheduled')
            ->orderBy('start_time')
            ->get();

        if ($slots->isEmpty()) {
            return;
        }

        $message = $this->generateDailySummaryMessage($slots, $tomorrow);

        SmartNotification::create([
            'user_id' => $user->id,
            'type' => 'reminder',
            'subtype' => 'daily_summary',
            'trigger_datetime' => Carbon::today()->setTime(20, 0), // 8 PM
            'scheduled_at' => Carbon::today()->setTime(20, 0),
            'title' => "📋 Lịch ngày mai của bạn",
            'message' => $message,
            'ai_generated' => true,
            'priority_level' => 3,
            'delivery_method' => 'email'
        ]);
    }

    /**
     * Generate daily summary message
     */
    protected function generateDailySummaryMessage($slots, $date): string
    {
        $message = "Lịch của bạn ngày " . $date->format('d/m/Y') . ":\n\n";
        
        $highPriorityCount = $slots->where('priority', 'high')->count();
        $criticalCount = $slots->where('priority', 'critical')->count();
        
        if ($criticalCount > 0) {
            $message .= "⚠️ Bạn có {$criticalCount} công việc khẩn cấp\n";
        }
        
        if ($highPriorityCount > 0) {
            $message .= "🔔 Bạn có {$highPriorityCount} công việc quan trọng\n";
        }
        
        $message .= "\nChi tiết lịch trình:\n";
        
        foreach ($slots as $slot) {
            $priority = $slot->priority === 'critical' ? '🔴' : 
                       ($slot->priority === 'high' ? '🟡' : '🟢');
            
            $message .= "\n{$priority} {$slot->start_time} - {$slot->end_time}: {$slot->task_title}";
            
            if ($slot->location) {
                $message .= " 📍 {$slot->location}";
            }
        }
        
        $totalMinutes = $slots->sum('duration_minutes');
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        
        $message .= "\n\n⏱️ Tổng thời gian làm việc: {$hours}h{$minutes}m";
        
        return $message;
    }
}