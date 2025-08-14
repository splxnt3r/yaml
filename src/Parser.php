<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml;

use Exception;
use InvalidArgumentException;
use Splxnter\Yaml\Exception\ParseException;

/**
 * Parser parses YAML strings to convert them to tokens.
 *
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class Parser
{
    private static ?Parser $instance = null;

    /**
     * @var string|null
     */
    private ?string $filename = null;

    /**
     * @var Token[]
     */
    private array $tokens = [];

    /**
     * @var array<int, string>
     */
    private array $blocks = [];

    private int $currentIndentation = 0;

    /**
     * @param int $indentation
     */
    private function __construct(private readonly int $indentation = 2)
    {
        if ($this->indentation < 1) {
            throw new InvalidArgumentException('Indentation must be greater than zero');
        }
    }

    /**
     * @param int $indentation
     *
     * @return Parser|null
     */
    public static function getInstance(int $indentation = 2): ?Parser
    {
        return self::$instance ??= new self($indentation);
    }

    /**
     * @return array
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @throws Exception
     */
    public function parseFile(string $filename): Parser
    {
        if (!is_file($filename)) {
            throw new ParseException(sprintf('File "%s" does not exist.', $filename));
        }

        if (!is_readable($filename)) {
            throw new ParseException(sprintf('File "%s" cannot be read.', $filename));
        }

        $this->filename = $filename;

        try {
            return $this->parse(file_get_contents($filename));
        } finally {
            $this->filename = null;
        }
    }

    /**
     * @param string $contents
     *
     * @return Parser
     * @throws Exception
     */
    public function parse(string $contents): self
    {
        $this->blocks             = [];
        $this->tokens             = [];
        $this->currentIndentation = 0;

        if (str_contains($contents, "\t")) {
            throw new ParseException('A YAML file cannot contain tabs as indentation', 0, $this->filename);
        }

        foreach (array_map('rtrim', explode(PHP_EOL, $contents)) as $i => $line) {
            $lineNumber = $i + 1;
            $length     = strlen($line);

            if ($length > 0 && !preg_match('/^[a-z_' . Token::COMMENT . Token::DASH . ' ]/', $line)) {
                throw new ParseException(
                    sprintf('Unexpected character "%s"', substr($line, 0, 1)),
                    $lineNumber,
                    $this->filename
                );
            }

            /** @var ?Token $lastToken */
            $lastToken = $this->tokens[array_key_last($this->tokens)] ?? null;
            $token     = $this->createToken($line);

            if ($token->getPrefix() === Token::DASH && $lastToken->getPrefix() === Token::DASH) {
                if ($token->getIndent() !== $lastToken->getIndent()) {
                    throw new ParseException('Invalid list item indent', $lineNumber, $this->filename);
                }
            } elseif ($token->getIndent() % $this->indentation !== 0) {
                throw new ParseException('Unexpected indent', $lineNumber, $this->filename);
            }

            $this->currentIndentation = $token->getIndent();

            $line = trim($line);

            $parts = preg_split(
                '/(?<![' . Token::QUOTES . '])' . Token::COMMENT . '(?![' . Token::QUOTES . '])/',
                $line
            );

            if (isset($parts[1])) {
                $token->setComment(trim($parts[1]));

                $line = trim($parts[0]);
            }

            $value = null;

            if (!$token->getPrefix() && str_contains($line, Token::COLON)) {
                $this->rewindToParentBlock($token->getIndent());

                [$name, $value] = $this->parseBlock($line, $lineNumber);

                $token->setName($name);

                $this->blocks[array_key_last($this->tokens)] = $name;
            }

            if ($token->getPrefix() === Token::DASH) {
                $value = $this->parseSequence($line, $lineNumber);
            }

            $token->setValue($value);
        }

        return $this;
    }

    /**
     * @param int $indent
     *
     * @return void
     */
    private function rewindToParentBlock(int $indent): void
    {
        if ($this->currentIndentation < $indent) {
            return;
        }

        while (count($this->blocks) > 0) {
            /** @var ?Token $last */
            $last = array_find(
                $this->tokens,
                fn(Token $token) => $token->getName() === ($this->blocks[array_key_last($this->blocks)] ?? null)
            );

            if ($last?->getIndent() < $indent) {
                break;
            }

            array_pop($this->blocks);
        }
    }

    /**
     * @param string $value
     * @param int    $lineNumber
     *
     * @return array
     * @throws Exception
     */
    private function parseBlock(string $value, int $lineNumber): array
    {
        [$name, $value] = array_map('trim', explode(Token::COLON, $value, 2));

        return [$name, $this->parseValue($value, $lineNumber)];
    }

    /**
     * @param string $line
     * @param int    $lineNumber
     *
     * @return mixed
     * @throws Exception
     */
    private function parseSequence(string $line, int $lineNumber): mixed
    {
        $value = trim($line, Token::DASH);

        return $this->parseValue($value, $lineNumber);
    }

    /**
     * @param string|null $value
     * @param int         $lineNumber
     *
     * @return mixed
     * @throws Exception
     */
    private function parseValue(?string $value, int $lineNumber): mixed
    {
        $value = trim($value);

        if (is_numeric($value)) {
            return str_contains($value, '.') ? floatval($value) : intval($value);
        }

        if (in_array($value, ['true', 'false'])) {
            return $value === 'true';
        }

        if ($this->hasStartingQuote($value)) {
            $startingQuote = substr($value, 0, 1);

            if (!$this->hasEndingQuote($value, $startingQuote)) {
                throw new ParseException('Missing closing quote', $lineNumber, $this->filename);
            }

            return trim($value, $startingQuote);
        }

        if ($this->hasStartOfArray($value)) {
            if (!$this->hasEndOfArray($value)) {
                throw new ParseException('Missing closing bracket', $lineNumber, $this->filename);
            }

            $value  = trim($value, ' ' . Token::SQUARE_BRACKETS);
            $values = $this->hasStartOfObject($value)
                ? preg_split('/' . Token::COMMA . '(?=\s+?' . Token::CURLY_BRACKETS[0] . ')/', $value)
                : explode(Token::COMMA, $value);

            return array_map(fn(string $next) => $this->parseValue($next, $lineNumber), $values);
        }

        if ($this->hasStartOfObject($value)) {
            if (!$this->hasEndOfObject($value)) {
                throw new ParseException('Missing closing bracket', $lineNumber, $this->filename);
            }

            $value  = trim($value, ' ' . Token::CURLY_BRACKETS);
            $blocks = explode(Token::COMMA, $value);

            $result = [];
            foreach ($blocks as $block) {
                [$name, $value] = $this->parseBlock($block, $lineNumber);

                $result = array_merge($result, [$name => $value]);
            }

            return $result;
        }

        if (str_contains($value, Token::COLON)) {
            [$name, $value] = $this->parseBlock($value, $lineNumber);

            return [$name => $value];
        }

        return $value;
    }

    /**
     * @param string $line
     *
     * @return Token
     */
    private function createToken(string $line): Token
    {
        $lengthBefore = strlen($line);
        $line         = trim($line);
        $lengthAfter  = strlen($line);

        $token = new Token();
        $token->setPrefix(str_starts_with($line, Token::DASH) ? Token::DASH : null);

        return $this->tokens[] = $token->setIndent($lengthBefore - $lengthAfter);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function hasStartingQuote(string $value): bool
    {
        return str_starts_with($value, Token::QUOTES[0]) || str_starts_with($value, Token::QUOTES[1]);
    }

    /**
     * @param string      $value
     * @param string|null $startingQuote
     *
     * @return bool
     */
    private function hasEndingQuote(string $value, ?string $startingQuote = null): bool
    {
        if ($startingQuote) {
            return str_ends_with($value, $startingQuote);
        }

        return str_ends_with($value, Token::QUOTES[0]) || str_ends_with($value, Token::QUOTES[1]);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function hasStartOfArray(string $value): bool
    {
        return str_starts_with($value, Token::SQUARE_BRACKETS[0]);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function hasEndOfArray(string $value): bool
    {
        return str_ends_with($value, Token::SQUARE_BRACKETS[1]);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function hasStartOfObject(string $value): bool
    {
        return str_starts_with($value, Token::CURLY_BRACKETS[0]);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function hasEndOfObject(string $value): bool
    {
        return str_ends_with($value, Token::CURLY_BRACKETS[1]);
    }
}
