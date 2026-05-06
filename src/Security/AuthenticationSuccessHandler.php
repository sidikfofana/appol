<?php
namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request; // Requis pour l'interface
use Symfony\Component\HttpFoundation\Response; // Requis pour l'interface
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface; // Requis pour l'interface
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface; // 💡 L'interface requise
use App\Security\ApiResponseService;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private ApiResponseService $apiResponse
    ) {}

    /**
     * Implémentation de l'interface AuthenticationSuccessHandlerInterface.
     * Cette méthode sera appelée en cas de succès de l'authentification JSON.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            return new JsonResponse(
                $this->apiResponse->error(
                    'User not found in token',
                    Response::HTTP_INTERNAL_SERVER_ERROR
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Génération du JWT
        $jwt = $this->jwtManager->create($user);

        // On récupère ici un ARRAY !
        $responseArray = $this->apiResponse->success(
            [
                'token' => $jwt,
                'expires_in' => 3600
            ],
            'Login successful',
            Response::HTTP_OK
        );

        // On crée maintenant une vraie JsonResponse
        $jsonResponse = new JsonResponse($responseArray, Response::HTTP_OK);

        // Cookie sécurisé
        $cookie = Cookie::create('token')
            ->withValue($jwt)
            ->withHttpOnly(true)
            ->withSecure($_ENV['APP_ENV'] === 'prod')
            ->withSameSite('Strict')
            ->withPath('/')
            ->withExpires(time() + 3600);

        $jsonResponse->headers->setCookie($cookie);

        return $jsonResponse;
    }
}
