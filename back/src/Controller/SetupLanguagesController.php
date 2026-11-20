<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Languages\Service\LanguageCatalog;

/** Read-only choices during the local, DB-ready wizard; no public setup API. */
final readonly class SetupLanguagesController
{
    public function __construct(
        private LanguageCatalog $languages,
        #[Autowire('%kernel.project_dir%')] private string $backend,
    ) {
    }

    #[Route('/setup/languages', name: 'symsvel_languages_setup_choices', methods: ['GET'], format: 'json')]
    public function __invoke(Request $request): JsonResponse
    {
        if (!in_array($request->getClientIp(), ['127.0.0.1', '::1'], true)) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        $path = dirname($this->backend).'/.symsvel/setup.json';
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            return new JsonResponse(['error' => 'unavailable'], 404);
        }
        try {
            $state = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'unavailable'], 404);
        }
        if (($state['schema'] ?? null) !== 1 || ($state['status'] ?? null) !== 'database_ready') {
            return new JsonResponse(['error' => 'unavailable'], 404);
        }
        $response = new JsonResponse(['data' => $this->languages->allForAdministration()]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
