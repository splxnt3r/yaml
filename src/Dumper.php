<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml;

/**
 * Dumper dumps PHP variables to YAML strings.
 *
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class Dumper
{
    public const int DUMP_EMPTY_LINES = 1;
    public const int DUMP_COMMENTS    = 2;

    /**
     * @param Token[] $tokens
     * @param int     $flags
     *
     * @return string
     */
    public function dump(array $tokens, int $flags = 0): string
    {
        $result = '';

        foreach ($tokens as $token) {
            $indent = str_repeat(' ', $token->getIndent());

            if ($token->isEmpty()) {
                if (!$token->getComment() && ($flags & self::DUMP_EMPTY_LINES)) {
                    $result .= PHP_EOL;
                } elseif ($token->getComment() && ($flags & self::DUMP_COMMENTS)) {
                    $result .= $indent . '# ' . $token->getComment() . PHP_EOL;
                }
            } else {
                $result .= $indent;

                if ($token->getPrefix() === Token::DASH) {
                    $result .= '- ' . $this->dumpValue($token->getValue());
                }

                if ($token->getName()) {
                    $result .= $this->dumpBlock($token->getName(), $token->getValue());
                }

                if ($token->getComment() && ($flags & self::DUMP_COMMENTS)) {
                    $result .= ($token->getValue() ? ' ' : '') . '# ' . $token->getComment() . PHP_EOL;
                } else {
                    $result .= PHP_EOL;
                }
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function dumpValue(mixed $value): string
    {
        if (is_string($value)) {
            return preg_match('/\W/', $value)
                ? sprintf("'%s'", str_replace("'", "\'", $value))
                : $value;
        }

        if (is_float($value) || is_int($value)) {
            return strval($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $result = '';

        if (is_array($value)) {
            if (array_any(array_keys($value), fn(mixed $key) => is_string($key))) {
                $count = count($value);

                $result .= $count > 1 ? '{ ' : '';
                if ($value) {
                    foreach ($value as $k => $v) {
                        $result .= $k . ': ' . $this->dumpValue($v) . (array_key_last($value) === $k ? '' : ', ');
                    }
                }
                $result .= $count > 1 ? ' }' : '';
            } else {
                $result .= '[ ' . implode(', ', array_map([$this, 'dumpValue'], $value)) . ' ]';
            }

            return $result;
        }

        return $result;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return string
     */
    private function dumpBlock(string $name, mixed $value): string
    {
        return $name . ':' . ($value ? ' ' . $this->dumpValue($value) : '');
    }
}
