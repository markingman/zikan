<?php declare(strict_types=1);

namespace Zikan\Logs;

enum LogLevel
{
	case EMERGENCY;
	case ALERT;
	case CRITICAL;
	case ERROR;
	case WARNING;
	case NOTICE;
	case INFO;
	case DEBUG;

	public static function label(int $level): string
	{
		return match ($level) {
			LOG_EMERG => self::EMERGENCY->name,
			LOG_ALERT => self::ALERT->name,
			LOG_CRIT => self::CRITICAL->name,
			LOG_ERR => self::ERROR->name,
			LOG_WARNING => self::WARNING->name,
			LOG_NOTICE => self::NOTICE->name,
			LOG_INFO => self::INFO->name,
			LOG_DEBUG => self::DEBUG->name,
			default => 'UNKNOWN',
		};
	}
}
