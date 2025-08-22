<?php
namespace App\Security;

use App\Repository\ApiKeyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(private ApiKeyRepository $apiKeyRepo) {}

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/')
            && $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $apiKeyHeader = $request->headers->get('Authorization');
        if (!str_starts_with($apiKeyHeader, 'ApiKey ')) {
            throw new CustomUserMessageAuthenticationException('Invalid header');
        }

        $token = substr($apiKeyHeader, 7); // después de 'ApiKey '
        $apiKey = $this->apiKeyRepo->findOneBy(['token' => $token, 'active' => true]);

        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('Invalid API key');
        }

        return new SelfValidatingPassport(new UserBadge($apiKey->getUser()->getUserIdentifier(), function () use ($apiKey) {
            return $apiKey->getUser();
        }));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // sigue la petición
    }
}
