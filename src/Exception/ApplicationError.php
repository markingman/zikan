<?php declare(strict_types=1);

namespace Zikan\Exception;

enum ApplicationError: string
{
	case CALL_ERR = 'APPLICATION_CALL_ERR';
//     case CONFIG_ERR = 'APPLICATION_CONFIG_ERR';
//     case BOOTSTRAP_ERR = 'APPLICATION_BOOTSTRAP_ERR';
}
