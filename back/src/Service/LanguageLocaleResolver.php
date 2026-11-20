<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Service;

use Symfony\Component\HttpFoundation\Request;

/** Locale of translation is independent of BO display language. */
final class LanguageLocaleResolver
{
    public function resolve(Request $request): string
    {
        foreach (['translation_lang', 'locale', 'ui_lang'] as $key) {
            $language = $request->query->get($key);
            if (is_string($language) && $this->valid($language)) {
                return strtolower(str_replace('_', '-', $language));
            }
        }
        $browser = $request->getPreferredLanguage();
        return is_string($browser) && $this->valid($browser)
            ? strtolower(str_replace('_', '-', $browser)) : '';
    }

    private function valid(string $language): bool
    {
        return preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})*$/iD', $language) === 1;
    }
}
