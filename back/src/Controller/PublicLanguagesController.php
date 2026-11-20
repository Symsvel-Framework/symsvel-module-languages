<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Languages\Service\LanguageLocaleResolver;

final readonly class PublicLanguagesController
{
    public function __construct(
        private LanguageCatalog $languages,
        private LanguageLocaleResolver $locales,
    ) {
    }

    /** Account signup choices use active languages regardless of UI translation mode. */
    #[Route('/apiopen/languages/account-options', name: 'symsvel_languages_public_account_options', methods: ['GET'], format: 'json')]
    public function accountOptions(Request $request): JsonResponse
    {
        $response = new JsonResponse(['data' => $this->languages->activeForAccounts($this->locales->resolve($request))]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    #[Route('/apiopen/languages', name: 'symsvel_languages_public_list', methods: ['GET'], format: 'json')]
    #[Route('/api/languages', name: 'symsvel_languages_app_list', methods: ['GET'], format: 'json')]
    public function __invoke(Request $request): JsonResponse
    {
        $response = new JsonResponse(['data' => $this->languages->publicLanguages($this->locales->resolve($request))]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
