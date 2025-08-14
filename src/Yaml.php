<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml;

use Exception;

/**
 * Yaml offers convenience methods to load and dump YAML.
 *
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class Yaml
{
    /**
     * @param string $filename
     * @param int    $indentation
     *
     * @return Parser
     * @throws Exception
     */
    public static function parseFile(string $filename, int $indentation = 2): Parser
    {
        return Parser::getInstance($indentation)->parseFile($filename);
    }

    /**
     * @param string $contents
     * @param int    $indentation
     *
     * @return Parser
     * @throws Exception
     */
    public static function parse(string $contents, int $indentation = 2): Parser
    {
        return Parser::getInstance($indentation)->parse($contents);
    }

    /**
     * @param string $filePath
     * @param array  $tokens
     * @param int    $flags
     *
     * @return false|int
     */
    public static function dumpFile(string $filePath, array $tokens, int $flags = 0): false|int
    {
        return file_put_contents($filePath, self::dump($tokens, $flags));
    }

    /**
     * @param array $tokens
     * @param int   $flags
     *
     * @return string
     */
    public static function dump(array $tokens, int $flags = 0): string
    {
        return new Dumper()->dump($tokens, $flags);
    }
}
