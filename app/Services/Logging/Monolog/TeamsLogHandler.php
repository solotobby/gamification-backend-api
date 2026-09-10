<?php

namespace App\Services\Logging\Monolog;

use App\Services\Logging\TeamsLoggerService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

class TeamsLogHandler extends AbstractProcessingHandler
{
    /**
     * @var TeamsLoggerService
     */
    protected TeamsLoggerService $teamsService;

    /**
     * @param int|string $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @param TeamsLoggerService|null $teamsService
     */
    public function __construct($level = 'info', bool $bubble = true, ?TeamsLoggerService $teamsService = null)
    {
        parent::__construct($level, $bubble);
        $this->teamsService = $teamsService ?: app(TeamsLoggerService::class);
    }

    /**
     * Writes the record down to the log of the implementing handler.
     *
     * @param LogRecord|array $record
     */
    protected function write($record): void
    {
        try {
            // Support both Monolog 3 LogRecord object and Monolog 2 array structure
            if ($record instanceof LogRecord) {
                $level = strtolower($record->level->name);
                $message = (string) $record->message;
                $context = (array) $record->context;
            } else {
                $level = strtolower($record['level_name'] ?? 'info');
                $message = (string) ($record['message'] ?? '');
                $context = (array) ($record['context'] ?? []);
            }

            // If an exception object is passed in the context
            if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
                $this->teamsService->sendError($context['exception'], $context);
                return;
            }

            // For error / critical / alert levels
            if (in_array($level, ['error', 'critical', 'alert', 'emergency'])) {
                $this->teamsService->sendError($message, $context);
                return;
            }

            // For info, notice, warning, debug levels
            $this->teamsService->sendLog($level, $message, $context);
        } catch (Throwable $e) {
            // Suppress handler errors to prevent crashing the application
        }
    }
}
