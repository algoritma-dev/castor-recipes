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

        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('product', $content);
    }

    public function testExtractsClassName(): void
    {
        $code = '<?php class UserManager {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('manager', $content);
    }

    public function testExtractsInterfaceName(): void
    {
        $code = '<?php interface UserInterface {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('interface', $content);
    }

    public function testExtractsMethodNames(): void
    {
        $code = '<?php function getUserData() {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('get', $content);
        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('data', $content);
    }

    public function testSkipsMagicMethods(): void
    {
        $code = '<?php function __construct() {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringNotContainsString('construct', $content);
    }

    public function testExtractsCommentsWords(): void
    {
        $code = '<?php // This is a comment with misspeling';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('comment', $content);
        $this->assertStringContainsString('misspeling', $content);
    }

    public function testExtractsDocComments(): void
    {
        $code = '<?php /** This is documentation with erors */';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('documentation', $content);
        $this->assertStringContainsString('erors', $content);
    }

    public function testSkipsPhpReservedWords(): void
    {
        $code = '<?php // This function returns an array of iterable objects';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringNotContainsString('array', $content);
        $this->assertStringNotContainsString('iterable', $content);
        $this->assertStringContainsString('function', $content);
        $this->assertStringContainsString('returns', $content);
        $this->assertStringContainsString('objects', $content);
    }

    public function testSplitsCamelCaseInComments(): void
    {
        $code = '<?php // MisspellingInterface handles errors';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('misspelling', $content);
        $this->assertStringContainsString('interface', $content);
        $this->assertStringContainsString('handles', $content);
        $this->assertStringContainsString('errors', $content);
    }

    public function testHandlesSnakeCase(): void
    {
        $code = '<?php $user_name_data = "test";';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('data', $content);
    }

    public function testSkipsShortVariables(): void
    {
        $code = '<?php $i = 0; $id = 1;';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        // Short variables (2 chars or less) should be skipped
        $this->assertStringNotContainsString('id', $content);
    }

    public function testExtractsEnumNames(): void
    {
        $code = '<?php enum UserStatus {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('status', $content);
    }

    public function testExtractsTraitNames(): void
    {
        $code = '<?php trait UserHelper {}';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('helper', $content);
    }

    public function testRemovesPhpDocTags(): void
    {
        $code = '<?php /** @param string $name @return void */';
        $text = new Text($code, []);

        $processedText = $this->processor->process($text);
        $content = $processedText->getContent();

        $this->assertStringNotContainsString('@param', $content);
        $this->assertStringNotContainsString('@return', $content);
        $this->assertStringNotContainsString('string', $content); // PHP reserved word
        $this->assertStringNotContainsString('void', $content); // PHP reserved word
    }
}
