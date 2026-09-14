<?php declare(strict_types=1);

namespace Zikan\Exception;

enum ObjectCacheError: string
{
	case FILE_READ = 'OBJECT_CACHE_FILE_READ';
	case FILE_WRITE = 'OBJECT_CACHE_FILE_WRITE';
	case INVALID_PATH = 'OBJECT_CACHE_INVALID_PATH';
	case UNLINK_FILE = 'OBJECT_CACHE_UNLINK_FILE';
	case UNSERIALIZE = 'OBJECT_CACHE_UNSERIALIZE';
}
