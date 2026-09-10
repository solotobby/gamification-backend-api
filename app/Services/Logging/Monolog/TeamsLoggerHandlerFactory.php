<?php

namespace App\Services\Logging\Monolog;

use Monolog\Logger;

class TeamsLoggerHandlerFactory
{
    /**
     * Create a custom Monolog instance for Microsoft Teams.
     *
     * @param array $config
     * @return Logger
     */
    public function __invoke(array $config): Logger
    {
        $level = $config['level'] ?? config('teams.level', 'info');
        $handler = new TeamsLogHandler($level);

        return new Logger('teams', [$handler]);
    }
}
