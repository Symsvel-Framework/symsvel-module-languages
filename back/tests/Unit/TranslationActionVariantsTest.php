<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TranslationActionVariantsTest extends TestCase
{
    public function testTranslationConfirmationsUseSemanticSuccessVariant(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3).'/bo/src/routes/languages/translations/+page.svelte');

        self::assertStringContainsString('variant="success" onclick={() => { void saveInline(entry); }}', $source);
        self::assertStringContainsString('variant="success" onclick={() => importCsv(\'apply\')}', $source);
        self::assertStringContainsString('variant="success" loading={busy} disabled={busy || !key.trim()}', $source);
        self::assertStringContainsString('variant="danger" onclick={() => { void removeKey(entry); }}', $source);
    }
}
