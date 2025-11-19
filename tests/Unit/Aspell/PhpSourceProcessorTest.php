<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\Unit\Aspell;

use Algoritma\CastorRecipes\Aspell\PhpSourceProcessor;
use PhpSpellcheck\Text;
use PHPUnit\Framework\TestCase;

final class PhpSourceProcessorTest extends TestCase
{
    private PhpSourceProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new PhpSourceProcessor();
    }

    public function testExtractsVariableNames(): void
    {
        $code = '<?php $userName = "test"; $productId = 123;';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('name', $content);
        self::assertStringContainsString('product', $content);
    }

    public function testExtractsClassName(): void
    {
        $code = '<?php class UserManager {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('manager', $content);
    }

    public function testExtractsInterfaceName(): void
    {
        $code = '<?php interface UserInterface {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('interface', $content);
    }

    public function testExtractsMethodNames(): void
    {
        $code = '<?php function getUserData() {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('get', $content);
        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('data', $content);
    }

    public function testSkipsMagicMethods(): void
    {
        $code = '<?php function __construct() {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringNotContainsString('construct', $content);
    }

    public function testExtractsCommentsWords(): void
    {
        $code = '<?php // This is a comment with misspeling';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('comment', $content);
        self::assertStringContainsString('misspeling', $content);
    }

    public function testExtractsDocComments(): void
    {
        $code = '<?php /** This is documentation with erors */';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('documentation', $content);
        self::assertStringContainsString('erors', $content);
    }

    public function testSkipsPhpReservedWords(): void
    {
        $code = '<?php // This function returns an array of iterable objects';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringNotContainsString('array', $content);
        self::assertStringNotContainsString('iterable', $content);
        self::assertStringContainsString('function', $content);
        self::assertStringContainsString('returns', $content);
        self::assertStringContainsString('objects', $content);
    }

    public function testSplitsCamelCaseInComments(): void
    {
        $code = '<?php // MisspellingInterface handles errors';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('misspelling', $content);
        self::assertStringContainsString('interface', $content);
        self::assertStringContainsString('handles', $content);
        self::assertStringContainsString('errors', $content);
    }

    public function testHandlesSnakeCase(): void
    {
        $code = '<?php $user_name_data = "test";';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('name', $content);
        self::assertStringContainsString('data', $content);
    }

    public function testSkipsShortVariables(): void
    {
        $code = '<?php $i = 0; $id = 1;';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        // Short variables (2 chars or less) should be skipped
        self::assertStringNotContainsString('id', $content);
    }

    public function testExtractsEnumNames(): void
    {
        $code = '<?php enum UserStatus {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('status', $content);
    }

    public function testExtractsTraitNames(): void
    {
        $code = '<?php trait UserHelper {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringContainsString('user', $content);
        self::assertStringContainsString('helper', $content);
    }

    public function testRemovesPhpDocTags(): void
    {
        $code = '<?php /** @param string $name @return void */';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        self::assertStringNotContainsString('@param', $content);
        self::assertStringNotContainsString('@return', $content);
        self::assertStringNotContainsString('string', $content); // PHP reserved word
        self::assertStringNotContainsString('void', $content); // PHP reserved word
    }
}
