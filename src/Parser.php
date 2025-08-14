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
    /**
     * @var Parser|null
     */
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
     * @var array<int|string, string>
     */
    private array $blocks = [];

    /**
     * @var int
     */
    private int $currentIndentation = 0;

    /**
     * @param int $indentation
     *
     * @return Parser
     */
    public static function getInstance(int $indentation = 2): Parser
    {
        return self::$instance ??= new self($indentation);
    }

    /**
     * @param int $indent
     */
    private function __construct(private readonly int $indent = 2)
    {
        if ($this->indent < 1) {
            throw new InvalidArgumentException('Indentation must be greater than zero');
        }
    }

    /**
     * @return Token[]
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

        if (false === ($contents = file_get_contents($filename))) {
            return $this;
        }

        $this->filename = $filename;

        try {
            return $this->parse($contents);
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

            if (
                $length > 0 &&
                !preg_match('/^[a-z0-9_' . Token::COMMENT . preg_quote(Token::DASH, '/') . ' ]/', $line)
            ) {
                throw new ParseException(
                    sprintf('Unexpected character "%s"', substr($line, 0, 1)),
                    $lineNumber,
                    $this->filename
                );
            }

            $token  = $this->createToken($line);
            $indent = $token->getIndent();

            if (!$unexpectedIndent = $indent % $this->indent !== 0) {
                $lastToken = $this->findLastBlockToken();

                if ($token->isSequence()) {
                    $lastToken ??= $this->findLastToken();

                    if (!$lastToken || $indent - $this->indent !== $lastToken->getIndent()) {
                        throw new ParseException('Invalid list item indent', $lineNumber, $this->filename);
                    }
                } else {
                    if (
                        $indent > 0 &&
                        (!$lastToken || ($lastToken->isBlock() && $indent !== $lastToken->getIndent() + $this->indent))
                    ) {
                        $unexpectedIndent = true;
                    }
                }
            }

            if ($unexpectedIndent) {
                throw new ParseException('Unexpected indentation', $lineNumber, $this->filename);
            }

            $this->currentIndentation = $indent;

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

                /** @var string $name */
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
            if ($this->findLastBlockToken()?->getIndent() < $indent) {
                break;
            }

            array_pop($this->blocks);
        }
    }

    /**
     * @param string $value
     * @param int    $lineNumber
     *
     * @return mixed
     * @throws Exception
     */
    private function parseValue(string $value, int $lineNumber): mixed
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

            $value = trim($value, ' ' . Token::SQUARE_BRACKETS);
            /** @var list<string> $values */
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
     * @param string $value
     * @param int    $lineNumber
     *
     * @return array<int, mixed>
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
     * @param string $line
     *
     * @return Token
     */
    private function createToken(string $line): Token
    {
        $lengthBefore = strlen($line);
        $line         = trim($line);
        $indent       = $lengthBefore - strlen($line);
        $prefix       = str_starts_with($line, Token::DASH) ? Token::DASH : null;

        return $this->tokens[] = Token::new($indent, $prefix);
    }

    /**
     * @return Token|null
     */
    private function findLastBlockToken(): ?Token
    {
        return array_find($this->tokens, fn(Token $token) => $token->getName() === $this->findLastBlock());
    }

    /**
     * @return string|null
     */
    private function findLastBlock(): ?string
    {
        return $this->blocks[array_key_last($this->blocks)] ?? null;
    }

    /**
     * @return Token|null
     */
    private function findLastToken(): ?Token
    {
        return $this->tokens[array_key_last($this->tokens)] ?? null;
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
