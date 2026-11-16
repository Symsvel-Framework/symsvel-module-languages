<?php

declare(strict_types=1);

namespace SymSvel\Module\Languages\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Languages\Service\LanguageLocaleResolver;

/** The module owns its API; security is delegated to the shared BO gate. */
final readonly class BoLanguagesController
{
    public function __construct(
        private BoAccessGate $gate,
        private LanguageCatalog $languages,
        private LanguageLocaleResolver $locales,
    ) {
    }

    #[Route('/apibo/languages', name: 'symsvel_languages_bo_list', methods: ['GET'], format: 'json')]
    public function list(Request $request): JsonResponse
    {
        if (($error = $this->check($request, 'list')) !== null) return $error;
        return self::reply(['data' => $this->languages->allForAdministration($this->locales->resolve($request))]);
    }

    #[Route('/apibo/languages/available', name: 'symsvel_languages_bo_available', methods: ['GET'], format: 'json')]
    public function available(Request $request): JsonResponse
    {
        if (($error = $this->check($request, 'list')) !== null) return $error;
        return self::reply(['data' => $this->languages->availableForAdministration($this->locales->resolve($request))]);
    }

    #[Route('/apibo/languages/{code<[a-z]{2,4}>}', name: 'symsvel_languages_bo_update', methods: ['PATCH'], format: 'json')]
    public function update(Request $request, string $code): JsonResponse
    {
        if (($error = $this->check($request, 'manage')) !== null) return $error;
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 1024) return self::fail(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::fail(400);
        }
        if (!is_array($data) || array_is_list($data)
            || count($data) !== 2 || !array_key_exists('active', $data)
            || !array_key_exists('pre_open', $data)
            || !is_bool($data['active']) || !is_bool($data['pre_open'])) return self::fail(400);
        try {
            return self::reply(['data' => $this->languages->update($code, $data['active'], $data['pre_open'])]);
        } catch (\InvalidArgumentException) {
            return self::fail(404);
        } catch (\DomainException) {
            return self::fail(409);
        }
    }

    private function check(Request $request, string $action): ?JsonResponse
    {
        $decision = $this->gate->check($request, 'languages', $action);
        return $decision->allowed() ? null : self::fail($decision->status);
    }

    private static function fail(int $status): JsonResponse
    {
        return self::reply(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'forbidden',
            404 => 'not_found', 409 => 'conflict',
            default => 'unavailable',
        }]], $status);
    }

    private static function reply(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
