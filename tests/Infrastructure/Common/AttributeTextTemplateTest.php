<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Common/namespace.php');

/**
 * Readonly text custom attributes escape their value and turn plain URLs into links.
 */
class AttributeTextTemplateTest extends TestBase
{
    public function testSingleLineUrlIsRenderedAsLink()
    {
        $output = $this->render('SingleLineTextbox.tpl', 'Screening link', 'https://ls.example/12/token/REF-1');

        $this->assertStringContainsString('<a href="https://ls.example/12/token/REF-1"', $output);
        $this->assertStringContainsString('target="_blank"', $output);
    }

    public function testSingleLineValueIsEscaped()
    {
        $output = $this->render('SingleLineTextbox.tpl', 'Note', '<img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img src=x', $output);
        $this->assertStringContainsString('&lt;img', $output);
    }

    public function testMultiLineUrlIsRenderedAsLinkWithLineBreaks()
    {
        $output = $this->render('MultiLineTextbox.tpl', 'Notes', "first line\nhttps://ls.example/x");

        $this->assertStringContainsString('<a href="https://ls.example/x"', $output);
        $this->assertStringContainsString('first line<br', $output);
    }

    public function testPlainTextIsNotLinkified()
    {
        $output = $this->render('SingleLineTextbox.tpl', 'Screening', 'Screening Complete');

        $this->assertStringContainsString('Screening Complete', $output);
        $this->assertStringNotContainsString('<a href', $output);
    }

    private function render(string $template, string $label, string $value): string
    {
        $attribute = new class ($label, $value) {
            public function __construct(private string $label, private string $value)
            {
            }

            public function Id()
            {
                return 1;
            }

            public function Label()
            {
                return $this->label;
            }

            public function Value()
            {
                return $this->value;
            }

            public function Required()
            {
                return false;
            }
        };

        $page = new SmartyPage();
        $page->assign('attribute', $attribute);
        $page->assign('attributeId', 'attribute-1');
        $page->assign('attributeName', 'attribute[1]');
        $page->assign('class', '');
        $page->assign('readonly', true);
        $page->assign('searchmode', false);

        return $page->fetch('Controls/Attributes/' . $template);
    }
}
