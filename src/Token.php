<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml;

/**
 * Token contains parsed information.
 *
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class Token
{
    public const string COMMENT         = '#';
    public const string QUOTES          = '\'"';
    public const string DASH            = '-';
    public const string SQUARE_BRACKETS = '[]';
    public const string CURLY_BRACKETS  = '{}';
    public const string COLON           = ':';
    public const string COMMA           = ',';

    /**
     * @var int
     */
    protected int $indent = 0;

    /**
     * @var string|null
     */
    protected ?string $prefix = null;

    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @var mixed|null
     */
    protected mixed $value = null;

    /**
     * @var string|null
     */
    protected ?string $comment = null;

    /**
     * @param int         $indent
     * @param string|null $prefix
     * @param string|null $name
     * @param mixed|null  $value
     * @param string|null $comment
     *
     * @return self
     */
    public static function new(
        int $indent = 0,
        ?string $prefix = null,
        ?string $name = null,
        mixed $value = null,
        ?string $comment = null,
    ): self {
        return new self()->setIndent($indent)
            ->setPrefix($prefix)
            ->setName($name)
            ->setValue($value)
            ->setComment($comment);
    }

    /**
     * @return int
     */
    public function getIndent(): int
    {
        return $this->indent;
    }

    /**
     * @param int $indent
     *
     * @return self
     */
    public function setIndent(int $indent): self
    {
        $this->indent = $indent;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    /**
     * @param string|null $prefix
     *
     * @return self
     */
    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     *
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     *
     * @return self
     */
    public function setValue(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string|null $comment
     *
     * @return self
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function isEmpty(): bool
    {
        return !$this->name && !$this->value;
    }

    public function isBlock(): bool
    {
        return !empty($this->name);
    }

    public function isSequence(): bool
    {
        return $this->prefix === self::DASH;
    }
}
