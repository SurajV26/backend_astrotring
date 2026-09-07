<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\AiChatApiController;
use Illuminate\Console\Command;
use Throwable;

class ProcessAiChatBilling extends Command
{
    protected $signature = 'ai-chat:process-billing';

    protected $description = 'Process AI chat minute billing and automatically close inactive chats after 3 minutes';

    public function handle(AiChatApiController $controller): int
    {
        $this->info('AI chat billing started...');

        try {
            $stats = $controller->processActiveChatBilling();

            $this->info('AI chat billing completed.');

            $this->line('Sessions checked: ' . ($stats['sessions_checked'] ?? 0));
            $this->line('Sessions billed: ' . ($stats['sessions_billed'] ?? 0));
            $this->line('Sessions stopped: ' . ($stats['sessions_stopped'] ?? 0));
            $this->line('Sessions auto-closed: ' . ($stats['sessions_auto_closed'] ?? 0));
            $this->line('Minutes charged: ' . ($stats['minutes_charged'] ?? 0));
            $this->line(
                'Amount charged: ₹' .
                number_format((float) ($stats['amount_charged'] ?? 0), 2)
            );

            $errors = (int) ($stats['errors'] ?? 0);

            if ($errors > 0) {
                $this->warn(
                    'Billing completed with ' . $errors . ' session error(s). Check Laravel logs.'
                );

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);

            $this->error(
                'AI chat billing failed: ' . $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
