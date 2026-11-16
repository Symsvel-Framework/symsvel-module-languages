<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SymSvel\Module\Settings\Service\SettingManager;

/** Native reference catalog; only modules manage its data, not Back/Core. */
final readonly class LanguageCatalog
{
    public function __construct(
        private Connection $db,
        private SettingManager $settings,
    ) {
    }

    /** @return list<array{iso_code:string,name:string,active:bool,pre_open:bool,primary:bool}> */
    public function allForAdministration(string $locale = ''): array
    {
        return $this->localizedRows($locale);
    }

    /** @return list<array{iso_code:string,name:string,active:bool,pre_open:bool,primary:bool}> */
    public function availableForAdministration(string $locale = ''): array
    {
        return array_values(array_filter($this->localizedRows($locale),
            static fn (array $row): bool => $row['active'] || $row['pre_open']));
    }

    /** @return list<array{iso_code:string,name:string,active:bool,pre_open:bool,primary:bool}> */
    public function publicLanguages(string $locale = ''): array
    {
        $multilingual = $this->settings->get('languages.multilingual') === '1';
        return array_values(array_filter($this->localizedRows($locale),
            static fn (array $row): bool => $row['active'] && ($multilingual || $row['primary'])));
    }

    /** @return list<array{iso_code:string,name:string,active:bool,pre_open:bool,primary:bool}> */
    private function localizedRows(string $locale): array
    {
        $primary = $this->settings->get('languages.primary_language');
        $language = strtolower(explode('-', str_replace('_', '-', trim($locale)))[0]);
        $rows = $this->db->fetchAllAssociative(
            'SELECT iso_code, name, active, pre_open FROM symsvel_languages ORDER BY iso_code',
        );
        $collator = class_exists(\Collator::class) ? new \Collator($language !== '' ? $language : 'en') : null;
        $normalized = array_map(static function (array $row) use ($language, $primary): array {
            $code = (string) $row['iso_code'];
            $native = (string) $row['name'];
            $localized = $language !== '' && class_exists(\Locale::class)
                ? \Locale::getDisplayLanguage($code, $language) : null;
            $display = is_string($localized) && $localized !== '' && $localized !== $code
                ? $localized : $native;
            return [
                'iso_code' => $code,
                'name' => $display,
                'active' => self::databaseBoolean($row['active']),
                'pre_open' => self::databaseBoolean($row['pre_open']),
                'primary' => $code === $primary,
            ];
        }, $rows);

        usort($normalized, static function (array $a, array $b) use ($collator): int {
            $order = $collator !== null
                ? $collator->compare($a['name'], $b['name'])
                : strcasecmp($a['name'], $b['name']);
            return $order !== 0 ? (int) $order : strcmp($a['iso_code'], $b['iso_code']);
        });

        return $normalized;
    }

    private static function databaseBoolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true', 'TRUE'], true);
    }

    /** @return array{iso_code:string,active:bool,pre_open:bool} */
    public function update(string $code, bool $active, bool $preOpen): array
    {
        $primary = $this->settings->get('languages.primary_language');
        if ($code === $primary && !$active) {
            throw new \DomainException('The primary language cannot be disabled.');
        }
        if ($this->db->fetchOne('SELECT iso_code FROM symsvel_languages WHERE iso_code = ?', [$code]) === false) {
            throw new \InvalidArgumentException('Unknown language.');
        }
        $this->db->executeStatement(
            'UPDATE symsvel_languages SET active = ?, pre_open = ? WHERE iso_code = ?',
            [$active, $preOpen, $code],
            [ParameterType::BOOLEAN, ParameterType::BOOLEAN, ParameterType::STRING],
        );
        return ['iso_code' => $code, 'active' => $active, 'pre_open' => $preOpen];
    }

    /** Setup-only, idempotent for the same value; never reassign the primary language. */
    public function initializePrimary(string $code): void
    {
        if (!preg_match('/^[a-z]{2,4}$/D', $code)
            || $this->db->fetchOne('SELECT iso_code FROM symsvel_languages WHERE iso_code = ?', [$code]) === false) {
            throw new \InvalidArgumentException('Unknown primary language.');
        }
        $current = $this->settings->get('languages.primary_language');
        if ($current !== null && $current !== $code) {
            throw new \DomainException('The primary language has already been configured.');
        }

        $this->db->transactional(function () use ($current, $code): void {
            if ($current === null) {
                $this->settings->set('languages.primary_language', $code);
                if ($this->settings->get('languages.multilingual') === null) {
                    $this->settings->set('languages.multilingual', '0');
                }
                $this->settings->flush();
            }
            $this->db->executeStatement('UPDATE symsvel_languages SET active = ? WHERE iso_code = ?',
                [true, $code], [ParameterType::BOOLEAN, ParameterType::STRING]);
        });
    }
}
