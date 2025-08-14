<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml\Exception;

use RuntimeException;
use Throwable;

/**
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class ParseException extends RuntimeException
{
    public function __construct(string $message = '', int $line = 0, ?string $file = null, ?Throwable $previous = null)
    {
        $dot = false;
        if (str_ends_with($message, '.')) {
            $message = substr($message, 0, -1);
            $dot     = true;
        }

        if (null !== $file) {
            $message .= sprintf(' in %s', json_encode($file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        if ($line >= 0) {
            $message .= sprintf(' at line %d', $line);
        }

        if ($dot) {
            $message .= '.';
        }

        parent::__construct($message, 0, $previous);
    }
}
