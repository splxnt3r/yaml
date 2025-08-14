<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Splxnter\Yaml\Exception\ParseException;
use Splxnter\Yaml\Parser;
use Splxnter\Yaml\Token;

/**
 * Parser test.
 *
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class ParserTest extends TestCase
{
    private string $filename;

    protected function setUp(): void
    {
        $this->filename = __DIR__ . '/Fixtures/config.yaml';
    }

    public function testFileIsParsedCorrectly(): void
    {
        $parser = Parser::getInstance()->parseFile($this->filename);
        $tokens = $parser->getTokens();

        $this->assertEquals($tokens[0], Token::new(name: 'services'));
        $this->assertEquals($tokens[1], Token::new(2, name: 'router'));
        $this->assertEquals($tokens[2], Token::new(4, name: 'class', value: 'App\Routing\Router'));
    }

    public function testExceptionIsThrownWhenFileDoesNotExist(): void
    {
        $exception = null;
        $filename  = __DIR__ . '/Fixtures/foobar.yaml';

        try {
            Parser::getInstance()->parseFile($filename);
        } catch (Exception $exception) {
        }

        $this->assertTrue($exception instanceof Exception);
        $this->assertSame(sprintf('File "%s" does not exist.', $filename), $exception->getMessage());
    }

    public function testExceptionIsThrownWhenTabsAreUsedForIndentation(): void
    {
        $exception = null;

        try {
            Parser::getInstance()->parse("\t");
        } catch (Exception $exception) {
        }

        $this->assertTrue($exception instanceof ParseException);
        $this->assertEquals('A YAML file cannot contain tabs as indentation.', $exception->getMessage());
    }

    public function testExceptionIsThrownForWrongBlockIndentation(): void
    {
        $exception = null;

        try {
            Parser::getInstance()->parse("services:\r\n  router:\r\n      class: App\Routing\Router");
        } catch (Exception $exception) {
        }

        $this->assertTrue($exception instanceof ParseException);
        $this->assertSame('Unexpected indentation at line 3.', $exception->getMessage());
    }

    public function testExceptionIsThrownForWrongSequenceIndentation(): void
    {
        $exception = null;

        try {
            Parser::getInstance()->parse("sequence:\r\n  - item\r\n    - item");
        } catch (Exception $exception) {
        }

        $this->assertTrue($exception instanceof ParseException);
        $this->assertSame('Invalid list item indent at line 3.', $exception->getMessage());
    }
}
