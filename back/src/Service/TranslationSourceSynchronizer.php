<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Imports only validated Tooling source data after migrations, without
 * overwriting edits made by administrators or legacy/imported translations.
 */
final readonly class TranslationSourceSynchronizer
{
    public function __construct(private Connection $db)
    {
    }

    /** @return array{keys_created:int,texts_created:int,texts_updated:int,protected:int} */
    public function sync(string $file): array
    {
        if (is_link($file) || !is_file($file) || !is_readable($file)
            || filesize($file) > 16777216) {
            throw new \RuntimeException('Translation source manifest unavailable or unsafe.');
        }
        $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema'] ?? null) !== 1
            || !is_array($data['entries'] ?? null) || !array_is_list($data['entries'])) {
            throw new \InvalidArgumentException('Invalid translation source inventory.');
        }
        // Full validation before the first write. Source languages may be
        // inactive: they are supplied for later pre-opening and activation.
        $languages = array_fill_keys(array_map('strval', $this->db->fetchFirstColumn(
            'SELECT iso_code FROM symsvel_languages',
        )), true);
        $entries = [];
        $seen = [];
        foreach ($data['entries'] as $row) {
            if (!is_array($row) || count($row) !== 5
                || !is_string($row['scope'] ?? null) || !in_array($row['scope'], ['bo', 'app', 'front'], true)
                || !is_string($row['module'] ?? null)
                || !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $row['module'])
                || $row['module'] === 'generated'
                || !is_string($row['key'] ?? null) || !is_string($row['language'] ?? null)
                || !isset($languages[$row['language']]) || !is_string($row['value'] ?? null)
                || strlen($row['value']) > 8192 || str_contains($row['value'], "\0")) {
                throw new \InvalidArgumentException('Invalid source translation record.');
            }
            $key = $row['module'].'.'.$row['key'];
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,189}$/D', $key)) {
                throw new \InvalidArgumentException('Invalid namespaced translation key.');
            }
            $identity = implode("\0", [$row['scope'], $key, $row['language']]);
            if (isset($seen[$identity])) {
                throw new \InvalidArgumentException('Duplicate source translation key.');
            }
            $seen[$identity] = true;
            $entries[] = [$row['scope'], $key, $row['language'], $row['value']];
        }
        return $this->db->transactional(function () use ($entries): array {
            $result = ['keys_created' => 0, 'texts_created' => 0, 'texts_updated' => 0, 'protected' => 0];
            $keys = [];
            foreach ($entries as [$scope, $key, $language, $value]) {
                $identity = $scope."\0".$key;
                if (!isset($keys[$identity])) {
                    $keys[$identity] = true;
                    if ($this->db->fetchOne(
                        'SELECT 1 FROM symsvel_translation_keys WHERE scope = ? AND translation_key = ?',
                        [$scope, $key],
                    ) === false) {
                        $this->db->insert('symsvel_translation_keys', [
                            'scope' => $scope, 'translation_key' => $key, 'description' => null,
                        ]);
                        ++$result['keys_created'];
                    }
                }
                if (trim($value) === '') {
                    continue; // A declared but untranslated key still counts as missing.
                }
                $where = ['scope' => $scope, 'translation_key' => $key, 'language' => $language];
                $existing = $this->db->fetchAssociative(
                    'SELECT text_value, customized FROM symsvel_translations '
                    .'WHERE scope = ? AND translation_key = ? AND language = ?',
                    [$scope, $key, $language],
                );
                if ($existing === false) {
                    // Legacy BO component customizations must survive the move
                    // from components.<key> to system.<key> (public bo.system.*).
                    $legacy = $scope === 'bo' && str_starts_with($key, 'system.')
                        ? 'components.'.substr($key, strlen('system.')) : null;
                    // The App host dashboard used to be stored in Auth before
                    // its native system dictionary was introduced.
                    if ($scope === 'app') {
                        $legacy = match ($key) {
                            'system.dashboard.title' => 'auth.dashboard',
                            'system.dashboard.loggedIn' => 'auth.loggedIn',
                            default => null,
                        };
                    }
                    $legacyValue = $legacy === null ? false : $this->db->fetchOne(
                        'SELECT text_value FROM symsvel_translations '
                        .'WHERE scope = ? AND translation_key = ? AND language = ?',
                        [$scope, $legacy, $language],
                    );
                    if ($legacyValue !== false && trim((string) $legacyValue) !== '') {
                        $this->db->insert('symsvel_translations', $where + [
                            'text_value' => (string) $legacyValue, 'customized' => true,
                        ], ['customized' => ParameterType::BOOLEAN]);
                        ++$result['texts_created'];
                        ++$result['protected'];
                        continue;
                    }
                    $this->db->insert('symsvel_translations', $where + [
                        'text_value' => $value, 'customized' => false,
                    ], ['customized' => ParameterType::BOOLEAN]);
                    ++$result['texts_created'];
                } elseif (in_array($existing['customized'], [true, 1, '1', 't', 'true', 'TRUE'], true)) {
                    ++$result['protected'];
                } elseif ((string) $existing['text_value'] !== $value) {
                    $this->db->update('symsvel_translations', ['text_value' => $value], $where);
                    ++$result['texts_updated'];
                }
            }
            return $result;
        });
    }
}
