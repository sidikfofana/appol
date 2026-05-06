<?php
namespace App\Controller;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Repository\UserRepository;

class AuthController extends AbstractController
{
    /**
     * Route utilisée par json_login (ne sera jamais exécutée)
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        throw new \RuntimeException('This route is handled by the firewall and should never be executed.');
    }

    /**
     * Rafraîchissement du token JWT
     */
    #[Route('/api/token/refresh', name: 'api_refresh_token', methods: ['POST'])]
    public function refresh(
        Request $request,
        RefreshTokenManagerInterface $refreshTokenManager,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $refreshToken = $request->get('refresh_token');

        if (!$refreshToken) {
            return new JsonResponse([
                'status' => 'error',
                'status_code' => 400,
                'message' => 'Refresh token missing'
            ], 400);
        }

        $tokenObj = $refreshTokenManager->get($refreshToken);

        if (!$tokenObj) {
            return new JsonResponse([
                'status' => 'error',
                'status_code' => 400,
                'message' => 'Invalid refresh token'
            ], 400);
        }

        $user = $tokenObj->getUser();

        if (!$user) {
            return new JsonResponse([
                'status' => 'error',
                'status_code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        // Nouveau JWT
        $newToken = $jwtManager->create($user);

        return new JsonResponse([
            'status' => 'success',
            'status_code' => 200,
            'message' => 'Token refreshed successfully',
            'token' => $newToken,
            'expires_in' => 3600
        ], 200);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse([
            'status' => 'success',
            'status_code' => 200,
            'message' => 'Logged out successfully'
        ], 200);

        // Supprimer le cookie JWT
        $response->headers->clearCookie('token', '/');

        return $response;
    }
}
