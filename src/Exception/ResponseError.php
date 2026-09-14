<?php declare(strict_types=1);

namespace Zikan\Exception;

enum ResponseError: string
{
	case FILE_SIZE = 'RESPONSE_FILE_SIZE';
	case MIME_TYPE = 'RESPONSE_MIME_TYPE';
	case READFILE_FAIL = 'RESPONSE_READFILE_FAIL';
	case UNLINK_FAIL = 'RESPONSE_UNLINK_FAIL';
}
