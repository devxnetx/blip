<?php

namespace Tests\Unit;

use App\Support\ChangelogMarkdown;
use PHPUnit\Framework\TestCase;

class ChangelogMarkdownTest extends TestCase
{
    public function test_markdown_becomes_safe_html_and_a_discord_message(): void
    {
        $body = "**по-бърза** карта\n\n- тъмен режим\n\n[карта](https://kostaa.space/map)\n\n<script>alert(1)</script>";

        $html = ChangelogMarkdown::html($body);

        $this->assertStringContainsString('<strong>по-бърза</strong>', $html);
        $this->assertStringContainsString('<li>тъмен режим</li>', $html);
        $this->assertStringContainsString('<a href="https://kostaa.space/map">карта</a>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $message = ChangelogMarkdown::message('Нова карта', $body, 'https://kostaa.space/changelog#4');

        $this->assertStringStartsWith('**[Нова карта](<https://kostaa.space/changelog#4>)**', $message);
        $this->assertStringContainsString('[карта](<https://kostaa.space/map>)', $message);
        $this->assertStringContainsString('- тъмен режим', $message);
    }

    public function test_a_heading_is_bold_in_discord(): void
    {
        $this->assertSame('**Секция**', ChangelogMarkdown::discord("# Секция\n"));
        $this->assertStringContainsString('<h3>Секция</h3>', ChangelogMarkdown::html('# Секция'));
    }
}
