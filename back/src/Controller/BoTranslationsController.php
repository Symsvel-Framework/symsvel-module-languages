<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Languages\Service\TranslationCatalog;
use SymSvel\Module\Languages\Service\TranslationCsvTransfer;

/** BO only: language dictionaries are owned by Languages, never by Back/Core. */
final readonly class BoTranslationsController
{
    public function __construct(
        private BoAccessGate $gate,
        private TranslationCatalog $translations,
        private TranslationCsvTransfer $csv,
        #[Autowire('%kernel.environment%')] private string $environment,
        #[Autowire('%kernel.project_dir%')] private string $backendDir,
    ) {
    }

    #[Route('/apibo/languages/translations', name: 'symsvel_languages_translations_list', methods: ['GET'], format: 'json')]
    public function list(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'list')) !== null) return $error;
        try {
            return self::reply(['data' => $this->translations->entries(
                (string) $request->query->get('scope', ''),
                (string) $request->query->get('language', ''),
            )]);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
    }

    #[Route('/apibo/languages/translations/keys', name: 'symsvel_languages_translations_key_create', methods: ['POST'], format: 'json')]
    public function createKey(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 6000) return self::error(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error(400);
        }
        if (!is_array($data) || array_is_list($data)
            || !is_string($data['scope'] ?? null) || !is_string($data['key'] ?? null)
            || (array_key_exists('description', $data) && !is_string($data['description']) && $data['description'] !== null)
            || count($data) > 3 || count($data) < 2) return self::error(400);
        try {
            return self::reply(['data' => $this->translations->registerKey(
                $data['scope'], $data['key'], $data['description'] ?? null,
            )], 201);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
    }

    #[Route('/apibo/languages/translations', name: 'symsvel_languages_translations_save', methods: ['PUT'], format: 'json')]
    public function save(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 12000) return self::error(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error(400);
        }
        if (!is_array($data) || array_is_list($data)
            || count($data) !== 4 || !is_string($data['scope'] ?? null)
            || !is_string($data['language'] ?? null) || !is_string($data['key'] ?? null)
            || !is_string($data['value'] ?? null)) return self::error(400);
        try {
            return self::reply(['data' => $this->translations->save(
                $data['scope'], $data['language'], $data['key'], $data['value'],
            )]);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
    }

    #[Route('/apibo/languages/translations', name: 'symsvel_languages_translations_delete', methods: ['DELETE'], format: 'json')]
    public function delete(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        $scope = $request->query->get('scope');
        $language = $request->query->get('language');
        $key = $request->query->get('key');
        if (!is_string($scope) || !is_string($language) || !is_string($key)) return self::error(400);
        try {
            $this->translations->delete($scope, $language, $key);
            return self::reply(['status' => 'deleted']);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
    }

    #[Route('/apibo/languages/translations/export', name: 'symsvel_languages_translations_export', methods: ['GET'], format: 'json')]
    public function export(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        $scope = $request->query->get('scope');
        $language = $request->query->get('language');
        if (!is_string($scope) || !is_string($language)) return self::error(400);
        try {
            return self::reply(['data' => $this->translations->export($scope, $language)]);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
    }

    #[Route('/apibo/languages/translations/csv-capabilities', name: 'symsvel_languages_translations_csv_capabilities', methods: ['GET'], format: 'json')]
    public function csvCapabilities(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        return self::reply(['data' => [
            'can_export' => true,
            'can_import' => in_array($this->environment, ['dev', 'test'], true) && $this->isLocal($request),
            'can_publish' => $this->environment === 'dev' && $this->isLocal($request)
                && class_exists(\SymSvel\Tooling\I18n\TranslationFilePublisher::class),
        ]]);
    }

    /** Exports the entire module dictionary, including untranslated keys and disabled languages. */
    #[Route('/apibo/languages/translations/export/csv', name: 'symsvel_languages_translations_csv_export', methods: ['GET'], format: 'json')]
    public function exportCsv(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        return self::reply(['data' => $this->csv->export()]);
    }

    /** Import is a development-only operation. Production may only export CSV. */
    #[Route('/apibo/languages/translations/import/csv', name: 'symsvel_languages_translations_csv_import', methods: ['POST'], format: 'json', env: ['dev', 'test'])]
    public function importCsv(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        if (!in_array($this->environment, ['dev', 'test'], true) || !$this->isLocal($request)) return self::error(403);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 2200000) return self::error(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error(400);
        }
        if (!is_array($data) || array_is_list($data) || !is_string($data['csv'] ?? null)
            || !in_array($data['mode'] ?? null, ['preview', 'apply'], true)
            || (isset($data['sha256']) && !is_string($data['sha256']))
            || count($data) > 3 || count($data) < 2) return self::error(400);
        try {
            return self::reply(['data' => $this->csv->import(
                $data['csv'], $data['mode'] === 'apply', $data['sha256'] ?? null,
            )]);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        }
    }

    /**
     * Local-only source publication. The module renders; Tooling writes the
     * generated file after verifying the destination and ownership signature.
     */
    #[Route('/apibo/languages/translations/publish', name: 'symsvel_languages_translations_publish', methods: ['POST'], format: 'json', env: 'dev')]
    public function publish(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'manage')) !== null) return $error;
        if ($this->environment !== 'dev' || !$this->isLocal($request)) return self::error(403);
        if (!class_exists(\SymSvel\Tooling\I18n\TranslationFilePublisher::class)) return self::error(503);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 1024) return self::error(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error(400);
        }
        if (!is_array($data) || array_is_list($data) || count($data) !== 2
            || !is_string($data['scope'] ?? null) || !is_string($data['language'] ?? null)) return self::error(400);
        try {
            $dictionary = $this->translations->export($data['scope'], $data['language']);
            $result = (new \SymSvel\Tooling\I18n\TranslationFilePublisher())->publish(
                $this->backendDir, $data['scope'], $data['language'], $dictionary['content'],
            );
            return self::reply(['data' => [
                'file' => $data['scope'].'/src/lib/i18n/generated/'.$data['language'].'.ts',
                'changed' => $result['changed'],
                'entries' => $dictionary['entries'],
            ]]);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException | \RuntimeException) {
            return self::error(409);
        }
    }

    private function isLocal(Request $request): bool
    {
        return in_array($request->getClientIp(), ['127.0.0.1', '::1'], true);
    }

    private function authorize(Request $request, string $action): ?JsonResponse
    {
        $decision = $this->gate->check($request, 'languages', $action);
        return $decision->allowed() ? null : self::error($decision->status);
    }

    private static function error(int $status): JsonResponse
    {
        return self::reply(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'forbidden',
            409 => 'conflict', default => 'unavailable',
        }]], $status);
    }

    private static function reply(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
