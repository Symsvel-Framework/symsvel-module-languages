<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Service;

use Doctrine\DBAL\Connection;
use SymSvel\Module\Settings\Service\SettingManager;

/**
 * Stores application dictionary values. Project source generation remains a
 * tooling responsibility; HTTP exports immutable TS text for an administrator.
 */
final readonly class TranslationCatalog
{
    public function __construct(
        private Connection $db,
        private LanguageCatalog $languages,
        private SettingManager $settings,
    ) {
    }

    /**
     * Full key inventory, including entries missing in the selected locale.
     * The BO can display the French/site fallback without silently saving it.
     *
     * @return list<array{scope:string,translation_key:string,description:?string,language:string,value:string,fallback_value:string,missing_translation:bool}>
     */
    public function entries(string $scope, string $language): array
    {
        $this->validateScope($scope);
        $this->validateLanguage($language);
        $keys = $this->db->fetchAllAssociative(
            'SELECT scope, translation_key, description FROM symsvel_translation_keys '
            .'WHERE scope = ? ORDER BY translation_key',
            [$scope],
        );
        $primary = $this->settings->get('languages.primary_language');
        if (!is_string($primary) || $primary === '') {
            throw new \DomainException('Primary language is not initialized.');
        }
        $texts = $this->db->fetchAllAssociative(
            'SELECT translation_key, language, text_value FROM symsvel_translations '
            .'WHERE scope = ? AND language IN (?, ?, ?)',
            [$scope, $language, 'fr', $primary],
        );
        $byKey = [];
        foreach ($texts as $text) {
            $byKey[(string) $text['translation_key']][(string) $text['language']] = (string) $text['text_value'];
        }
        $entries = [];
        foreach ($keys as $key) {
            $name = (string) $key['translation_key'];
            $values = $byKey[$name] ?? [];
            $translated = (string) ($values[$language] ?? '');
            $fallback = (string) ($values['fr'] ?? '');
            if (trim($fallback) === '') $fallback = (string) ($values[$primary] ?? '');
            $entries[] = [
                'scope' => $scope,
                'translation_key' => $name,
                'description' => $key['description'] === null ? null : (string) $key['description'],
                'language' => $language,
                'value' => $translated,
                'fallback_value' => $fallback,
                'missing_translation' => trim($translated) === '',
            ];
        }

        return $entries;
    }

    /**
     * Register a durable translation key independently of its translated texts.
     *
     * @return array{scope:string,translation_key:string,description:?string}
     */
    public function registerKey(string $scope, string $key, ?string $description = null): array
    {
        $this->validateScope($scope);
        self::validateKey($key);
        if ($description !== null && (strlen($description) > 4096 || str_contains($description, "\\0"))) {
            throw new \InvalidArgumentException('Invalid translation description.');
        }
        $this->db->transactional(function () use ($scope, $key, $description): void {
            if ($this->db->fetchOne(
                'SELECT 1 FROM symsvel_translation_keys WHERE scope = ? AND translation_key = ?',
                [$scope, $key],
            ) !== false) {
                throw new \DomainException('Translation key already registered.');
            }
            $this->db->insert('symsvel_translation_keys', [
                'scope' => $scope, 'translation_key' => $key, 'description' => $description,
            ]);
        });
        return ['scope' => $scope, 'translation_key' => $key, 'description' => $description];
    }

    /** @return array{scope:string,translation_key:string,language:string,value:string} */
    public function save(string $scope, string $language, string $key, string $value): array
    {
        $this->validateScope($scope);
        $this->validateLanguage($language);
        self::validateKey($key);
        if (strlen($value) > 8192 || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Invalid translation value.');
        }

        $this->db->transactional(function () use ($scope, $language, $key, $value): void {
            // Keep source keys when translated values are subsequently removed.
            if ($this->db->fetchOne(
                'SELECT 1 FROM symsvel_translation_keys WHERE scope = ? AND translation_key = ?',
                [$scope, $key],
            ) === false) {
                $this->db->insert('symsvel_translation_keys', [
                    'scope' => $scope, 'translation_key' => $key, 'description' => null,
                ]);
            }
            $updated = $this->db->update('symsvel_translations', ['text_value' => $value], [
                'scope' => $scope, 'translation_key' => $key, 'language' => $language,
            ]);
            if ($updated === 0 && !$this->db->fetchOne(
                'SELECT 1 FROM symsvel_translations WHERE scope = ? AND translation_key = ? AND language = ?',
                [$scope, $key, $language],
            )) {
                $this->db->insert('symsvel_translations', [
                    'scope' => $scope, 'translation_key' => $key, 'language' => $language, 'text_value' => $value,
                ]);
            }
        });

        return ['scope' => $scope, 'translation_key' => $key, 'language' => $language, 'value' => $value];
    }

    public function delete(string $scope, string $language, string $key): void
    {
        $this->validateScope($scope);
        $this->validateLanguage($language);
        self::validateKey($key);
        $this->db->delete('symsvel_translations', [
            'scope' => $scope, 'translation_key' => $key, 'language' => $language,
        ]);
    }

    /** @return array{filename:string,content:string,entries:int} */
    public function export(string $scope, string $language): array
    {
        $this->validateScope($scope);
        $this->validateLanguage($language);
        $primary = $this->settings->get('languages.primary_language');
        if (!is_string($primary) || $primary === '') {
            throw new \DomainException('Primary language is not initialized.');
        }

        // Fallback is resolved key by key in the generated static artifact.
        // Prefer French when available, then the configured site primary,
        // finally the requested language. The requested language always wins.
        $values = [];
        foreach (array_unique([$primary, 'fr', $language]) as $code) {
            $rows = $this->db->fetchAllAssociative(
                'SELECT translation_key, text_value FROM symsvel_translations WHERE scope = ? AND language = ? '
                .'ORDER BY translation_key',
                [$scope, $code],
            );
            foreach ($rows as $row) {
                $value = (string) $row['text_value'];
                if (trim($value) !== '') {
                    $values[(string) $row['translation_key']] = $value;
                }
            }
        }
        ksort($values, SORT_STRING);

        // HP's TypeScript export uses a nested dictionary (auth.signIn => auth.signIn).
        // Build it deterministically without ever executing translation content.
        $dictionary = [];
        foreach ($values as $key => $value) {
            $segments = explode('.', $key);
            $cursor = &$dictionary;
            foreach ($segments as $index => $segment) {
                if ($index === count($segments) - 1) {
                    if (isset($cursor[$segment]) && is_array($cursor[$segment])) {
                        throw new \DomainException('Conflicting translation key prefix: '.$key);
                    }
                    $cursor[$segment] = $value;
                } else {
                    if (isset($cursor[$segment]) && !is_array($cursor[$segment])) {
                        throw new \DomainException('Conflicting translation key prefix: '.$key);
                    }
                    $cursor[$segment] ??= [];
                    $cursor = &$cursor[$segment];
                }
            }
            unset($cursor);
        }
        $sortNested = static function (array &$node) use (&$sortNested): void {
            ksort($node, SORT_STRING);
            foreach ($node as &$item) {
                if (is_array($item)) $sortNested($item);
            }
            unset($item);
        };
        $sortNested($dictionary);
        $json = json_encode($dictionary === [] ? new \stdClass() : $dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
            | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        return [
            'filename' => 'translations.'.$scope.'.'.$language.'.ts',
            'content' => '// Generated by SymSvel Languages. Do not edit manually.'."\n"
                .'export default '.$json.' as const;'."\n",
            'entries' => count($values),
        ];
    }

    private function validateScope(string $scope): void
    {
        if (!in_array($scope, ['bo', 'app', 'front'], true)) {
            throw new \InvalidArgumentException('Invalid translation scope.');
        }
    }

    private function validateLanguage(string $language): void
    {
        if (!preg_match('/^[a-z]{2,4}$/D', $language)) {
            throw new \InvalidArgumentException('Invalid ISO language code.');
        }
        foreach ($this->languages->availableForAdministration() as $row) {
            if ($row['iso_code'] === $language) {
                return;
            }
        }
        throw new \DomainException('Language must be active or pre-open for translations.');
    }

    private static function validateKey(string $key): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,189}$/D', $key)) {
            throw new \InvalidArgumentException('Invalid translation key.');
        }
    }
}
