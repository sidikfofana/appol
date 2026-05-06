<?php //nous permet de se connecter avec soit l'email ou le téméphone
namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use App\Security\ApiResponseService;
use Symfony\Component\HttpFoundation\Response;

class MultiUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ApiResponseService $apiResponse
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = $this->em->getRepository(User::class)->findOneBy([$field => $identifier]);

        if (!$user) {
            //throw new UserNotFoundException("User not found.");
            throw new CustomUserMessageAuthenticationException(
                $this->apiResponse->error('Utilisateur non trouvé', Response::HTTP_NOT_FOUND)['message']
            );
        }

        // Vérifier si le compte est actif
        if (method_exists($user, 'isStatus') && !$user->isStatus()) {
            // Lève une exception que Symfony affichera dans le flow d'authentification
            //throw new CustomUserMessageAuthenticationException('Compte désactivé.');
            throw new CustomUserMessageAuthenticationException(
                $this->apiResponse->error('Compte désactivé', Response::HTTP_FORBIDDEN)['message']
            );
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }
}
