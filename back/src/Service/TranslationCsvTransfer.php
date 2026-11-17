<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Service;

use Doctrine\DBAL\Connection;

/**
 * CSV exchange from production to local development. Import never deletes keys
 * or translations. Blank values mean "no change", never a destructive reset.
 */
final readonly class TranslationCsvTransfer
{
    private const HEADER = ['scope', 'key', 'description', 'language', 'value'];
    private const MAX_BYTES = 2097152;
    private const MAX_ROWS = 10000;

    public function __construct(private Connection $db)
    {
    }

    /** @return array{filename:string,content:string,rows:int} */
    public function export(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT k.scope, k.translation_key, k.description, t.language, t.text_value '
            .'FROM symsvel_translation_keys k LEFT JOIN symsvel_translations t '
            .'ON k.scope = t.scope AND k.translation_key = t.translation_key '
            .'ORDER BY k.scope, k.translation_key, t.language',
        );
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) throw new \RuntimeException('CSV stream unavailable.');
        try {
            fputcsv($stream, self::HEADER, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, [
                    (string) $row['scope'], (string) $row['translation_key'],
                    (string) ($row['description'] ?? ''), (string) ($row['language'] ?? ''),
                    (string) ($row['text_value'] ?? ''),
                ], ',', '"', '');
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            if (!is_string($content)) throw new \RuntimeException('CSV stream unreadable.');
        } finally {
            fclose($stream);
        }

        return ['filename' => 'symsvel-translations.csv', 'content' => $content, 'rows' => count($rows)];
    }

    /**
     * The preview and apply paths use the same strict parser and change planner.
     * @return array{rows:int,keys_created:int,keys_updated:int,texts_created:int,texts_updated:int,unchanged:int,sha256:string,applied:bool}
     */
    public function import(string $csv, bool $apply = false, ?string $expectedSha256 = null): array
    {
        $rows = self::parse($csv);
        $digest = hash('sha256', $csv);
        if ($apply && ($expectedSha256 === null || !hash_equals($digest, $expectedSha256))) {
            throw new \InvalidArgumentException('CSV changed since preview.');
        }

        $operation = function () use ($rows, $apply, $digest): array {
            $languages = array_fill_keys(array_map('strval', $this->db->fetchFirstColumn(
                'SELECT iso_code FROM symsvel_languages',
            )), true);
            $summary = [
                'rows' => count($rows), 'keys_created' => 0, 'keys_updated' => 0,
                'texts_created' => 0, 'texts_updated' => 0, 'unchanged' => 0,
                'sha256' => $digest, 'applied' => $apply,
            ];
            foreach ($rows as $row) {
                [$scope, $key, $description, $language, $value] = $row;
                if ($language !== '' && !isset($languages[$language])) {
                    throw new \InvalidArgumentException('Unknown language code: '.$language);
                }
                $existing = $this->db->fetchAssociative(
                    'SELECT description FROM symsvel_translation_keys WHERE scope = ? AND translation_key = ?',
                    [$scope, $key],
                );
                $changed = false;
                if ($existing === false) {
                    ++$summary['keys_created'];
                    $changed = true;
                    if ($apply) $this->db->insert('symsvel_translation_keys', [
                        'scope' => $scope, 'translation_key' => $key,
                        'description' => $description !== '' ? $description : null,
                    ]);
                } elseif ($description !== '' && $description !== (string) ($existing['description'] ?? '')) {
                    ++$summary['keys_updated'];
                    $changed = true;
                    if ($apply) $this->db->update('symsvel_translation_keys',
                        ['description' => $description], ['scope' => $scope, 'translation_key' => $key]);
                }
                // Empty cells preserve local translations. Use the BO deletion action
                // when deletion is intentional, rather than importing an ambiguous blank.
                if ($language !== '' && $value !== '') {
                    $current = $this->db->fetchOne(
                        'SELECT text_value FROM symsvel_translations '
                        .'WHERE scope = ? AND translation_key = ? AND language = ?',
                        [$scope, $key, $language],
                    );
                    if ($current === false) {
                        ++$summary['texts_created'];
                        $changed = true;
                        if ($apply) $this->db->insert('symsvel_translations', [
                            'scope' => $scope, 'translation_key' => $key,
                            'language' => $language, 'text_value' => $value,
                        ]);
                    } elseif ((string) $current !== $value) {
                        ++$summary['texts_updated'];
                        $changed = true;
                        if ($apply) $this->db->update('symsvel_translations', ['text_value' => $value], [
                            'scope' => $scope, 'translation_key' => $key, 'language' => $language,
                        ]);
                    }
                }
                if (!$changed) ++$summary['unchanged'];
            }
            return $summary;
        };

        // Even preview is an isolated transaction, so failed validation never
        // partially applies a batch; the write path is all-or-nothing.
        return $this->db->transactional($operation);
    }

    /** @return list<array{string,string,string,string,string}> */
    private static function parse(string $content): array
    {
        if ($content === '' || strlen($content) > self::MAX_BYTES || preg_match('//u', $content) !== 1
            || str_contains($content, "\0")) {
            throw new \InvalidArgumentException('Invalid CSV content or size.');
        }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) throw new \RuntimeException('CSV stream unavailable.');
        try {
            fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content);
            rewind($stream);
            $header = fgetcsv($stream, 0, ',', '"', '');
            if ($header !== self::HEADER) throw new \InvalidArgumentException('Invalid CSV header.');
            $rows = [];
            $seen = [];
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) continue;
                if (count($row) !== 5 || count($rows) >= self::MAX_ROWS) {
                    throw new \InvalidArgumentException('Invalid CSV row or row count.');
                }
                [$scope, $key, $description, $language, $value] = $row;
                if (!in_array($scope, ['bo', 'app', 'front'], true)
                    || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,189}$/D', $key)
                    || ($language !== '' && !preg_match('/^[a-z]{2,4}$/D', $language))
                    || ($language === '' && $value !== '')
                    || strlen($description) > 4096 || strlen($value) > 8192) {
                    throw new \InvalidArgumentException('Invalid CSV data.');
                }
                $identity = $scope."\0".$key."\0".$language;
                if (isset($seen[$identity])) throw new \InvalidArgumentException('Duplicate CSV row.');
                $seen[$identity] = true;
                $rows[] = [$scope, $key, $description, $language, $value];
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
