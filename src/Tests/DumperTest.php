<?php

/*
 * (c) Georgijs Kļaviņš <georgijs.klavins@proton.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Splxnter\Yaml\Tests;

use PHPUnit\Framework\TestCase;
use Splxnter\Yaml\Dumper;
use Splxnter\Yaml\Token;

/**
 * Dumper test.
 *
 * @author Georgijs Kļaviņš <georgijs.klavins@proton.me>
 */
class DumperTest extends TestCase
{
    private string $filename;

    protected function setUp(): void
    {
        $this->filename = __DIR__ . '/Fixtures/config.yaml';
    }

    public function testTokensAreDumpedCorrectly(): void
    {
        $expected = file_get_contents($this->filename);

        $this->assertSame($expected, Dumper::getInstance()->dump([
            Token::new(name: 'services'),
            Token::new(2, name: 'router'),
            Token::new(4, name: 'class', value: 'App\Routing\Router'),
        ]));
    }

    public function testByDefaultCommentsAreOmmited(): void
    {
        $this->assertSame('', Dumper::getInstance()->dump([
            Token::new(comment: 'test comment'),
        ]));
    }

    public function testByDefaultEmptyLinesAreOmmited(): void
    {
        $this->assertSame('', Dumper::getInstance()->dump([
            Token::new(),
        ]));
    }

    public function testByDefaultCommentsAndEmptyLinesAreOmmited(): void
    {
        $this->assertSame('', Dumper::getInstance()->dump([
            Token::new(comment: 'test comment'),
            Token::new(),
        ]));
    }

    public function testCommentsArePreservedCorrectly(): void
    {
        $this->assertSame("# test comment\r\n", Dumper::getInstance()->dump([
            Token::new(comment: 'test comment'),
        ], Dumper::DUMP_COMMENTS));
    }

    public function testEmptyLinesArePreservedCorrectly(): void
    {
        $this->assertSame("\r\n", Dumper::getInstance()->dump([
            Token::new(),
        ], Dumper::DUMP_EMPTY_LINES));
    }

    public function testEmptyLinesAndCommentsArePreservedCorrectly(): void
    {
        $this->assertSame("# test comment\r\n\r\n", Dumper::getInstance()->dump([
            Token::new(comment: 'test comment'),
            Token::new(),
        ], Dumper::DUMP_EMPTY_LINES | Dumper::DUMP_COMMENTS));
    }
}
