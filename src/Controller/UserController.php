<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use App\Entity\Country;
use App\Entity\Pharmacy;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Helpers\Helpers;
use App\Security\ApiResponseService;
use Symfony\Component\Security\Core\User\UserInterface;

// Documentation OpenAPI
use OpenApi\Attributes as OA;

final class UserController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    private $serializer;
    private $validator;
    private $Helpers;
    private $apiResponse;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        Helpers $Helpers,
        ApiResponseService $apiResponse,

    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->Helpers = $Helpers;
        $this->apiResponse = $apiResponse;
    }

    //Get User list
    /**
     * @return Response
     **/
    #[Route('/api/user/list', name: 'app_list_user', methods: ['GET'])]
    #[OA\Get(
        path: '/api/user/list',
        summary: 'Liste des utilisateurs',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/User')
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non autorisé'
            )
        ]
    )]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getId();

        $queryBuilder = $entityManager->getRepository(User::class)->createQueryBuilder('u');
        $queryBuilder->where('u.id != :userId')
            ->orderBy('u.created_at', 'DESC')
            ->setParameter('userId', $userId);

        $datas = $queryBuilder->getQuery()->getResult();
        return $this->json(
            $this->apiResponse->success(
                data: $datas,
                message: 'Users retrieved successfully'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'users']
        );
    }

    //List des Owner non liés à une pharmacie
    #[Route('/api/user/owner4/list', name: 'app_list_user_owner', methods: ['GET'])]
    public function indexUsersRole4WithoutPharmacy(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $qb = $entityManager->getRepository(User::class)->createQueryBuilder('u');

        $qb
            ->innerJoin('u.role', 'r')
            ->where('u.id != :userId')
            ->andWhere('r.id = :roleId')
            ->andWhere('u.pharmacy IS NULL')
            ->orderBy('u.created_at', 'DESC')
            ->setParameter('userId', $user->getId())
            ->setParameter('roleId', 4);

        $datas = $qb->getQuery()->getResult();

        return $this->json(
            $this->apiResponse->success(
                data: $datas,
                message: 'Users retrieved successfully'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'users']
        );
    }



    //create user
    #[Route('/api/user/create', name: 'api_create_user', methods: ['POST'])]
    #[OA\Post(
        path: '/api/user/create',
        summary: 'Créer un nouvel utilisateur',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['firstname', 'lastname', 'email', 'phone', 'password'],
                properties: [
                    new OA\Property(property: 'firstname', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'lastname', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+22505050505'),
                    new OA\Property(property: 'password', type: 'string', example: 'strongPassword123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur créé'),
            new OA\Response(response: 400, description: 'Erreur de validation'),
            new OA\Response(response: 409, description: 'Email déjà utilisé'),
        ]
    )]
    public function createUser(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true);
            if ($data === null) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Format JSON Invalide.',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Champs requis
            $requiredFields = ['lastname', 'firstname', 'email', 'phone', 'password', 'country_id'];
            $missingFields = $this->Helpers->validateRequiredFields($data, $requiredFields);
            if (!empty($missingFields)) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Champs obligatoires manquants: ' . implode(', ', $missingFields),
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérifier si l'email existe déjà
            $existingUserEmail = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $data['email']]);
            if ($existingUserEmail) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Cet email est déjà utilisé.',
                        statusCode: Response::HTTP_CONFLICT
                    ),
                    Response::HTTP_CONFLICT
                );
            }

            // Vérifier si le téléphone existe déjà (si fourni)
            if (!empty($data['phone'])) {
                $existingUserPhone = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['phone' => $data['phone']]);
                if ($existingUserPhone) {
                    return new JsonResponse(
                        $this->apiResponse->error(
                            message: 'Ce numéro de téléphone est déjà utilisé.',
                            statusCode: Response::HTTP_CONFLICT
                        ),
                        Response::HTTP_CONFLICT
                    );
                }
            }

            // Role
            // $role = $this->entityManager->getRepository(Role::class)->find($data['role_id']);
            // if (!$role) {
            //     return new JsonResponse(
            //         $this->apiResponse->error(
            //             message: 'Invalide role_id',
            //             statusCode: Response::HTTP_BAD_REQUEST
            //         ),
            //         Response::HTTP_BAD_REQUEST
            //     );
            // }

            // Récupération du rôle
            if (!empty($data['role_id'])) {
                // Si role_id est envoyé, on tente de le récupérer
                $role = $this->entityManager->getRepository(Role::class)->find($data['role_id']);

                if (!$role) {
                    return new JsonResponse(
                        $this->apiResponse->error(
                            message: 'Role_id invalide',
                            statusCode: Response::HTTP_BAD_REQUEST
                        ),
                        Response::HTTP_BAD_REQUEST
                    );
                }

            } else {
                // Aucun role_id -> on prend "User" comme rôle par défaut
                $role = $this->entityManager->getRepository(Role::class)
                    ->findOneBy(['name' => 'User']);

                if (!$role) {
                    return new JsonResponse(
                        $this->apiResponse->error(
                            message: 'Role par défaut "User" introuvable dans la base.',
                            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
                        ),
                        Response::HTTP_INTERNAL_SERVER_ERROR
                    );
                }
            }

            // Country
            $country = $this->entityManager->getRepository(Country::class)->find($data['country_id']);
            if (!$country) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Invalide country_id',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Pharmacy
            $pharmacy = null;
            if (!empty($data['pharmacy_id'])) {
                $pharmacy = $this->entityManager->getRepository(Pharmacy::class)->find($data['pharmacy_id']);
                if (!$pharmacy) {
                    return new JsonResponse(
                        $this->apiResponse->error(
                            message: 'Invalide pharmacy_id',
                            statusCode: Response::HTTP_BAD_REQUEST
                        ),
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $user = new User();
            $user->setFirstname($data['firstname']);
            $user->setLastname($data['lastname']);
            $user->setEmail($data['email']);
            $user->setPhone($data['phone'] ?? '');
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setRole($role);
            $user->setCountry($country);
            $user->setPharmacy($pharmacy);
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setStatus(true);

            // Validation
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: (string) $errors,
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse(
                $this->apiResponse->success(
                    message: 'Utilisateur créé avec succès !',
                    statusCode: Response::HTTP_CREATED,
                    extra: ['user_id' => $user->getId()]
                ),
                Response::HTTP_CREATED
            );

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                $this->apiResponse->error(
                    message: $e->getMessage(),
                    statusCode: Response::HTTP_BAD_REQUEST
                ),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                $this->apiResponse->error(
                    message: 'An error occurred: ' . $e->getMessage(),
                    statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    //le profil de l’utilisateur connecté
    #[Route('/api/user/profile', name: 'api_user_profile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/user/profile',
        summary: 'Récupère le profil de l’utilisateur connecté',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil utilisateur',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
            ),
            new OA\Response(response: 401, description: 'Utilisateur non authentifié'),
        ]
    )]
    public function profile(): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Même logique que pour la liste, mais juste pour l'utilisateur connecté
        return $this->json(
            $this->apiResponse->success(
                data: [$user], // mettre dans un tableau pour suivre la logique "list"
                message: 'Profil utilisateur récupéré avec succès'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'users']
        );
    }


    //le profil de l’utilisateur par ID
    #[Route('/api/user/{id}', name: 'api_user_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/user/{id}',
        summary: 'Récupère un utilisateur par ID',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
            ),
            new OA\Response(response: 404, description: 'Utilisateur introuvable')
        ]
    )]
    public function showUser(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur introuvable'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->apiResponse->success(
                data: [$user], // mettre dans un tableau pour suivre la logique "list"
                message: 'Profil utilisateur récupéré avec succès'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'users']
        );
    }

    //Mise à jour du Pofile
    #[Route('/api/user/{id}/update', name: 'api_user_update', methods: ['PUT'])]
    public function updateUser(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Autorisation : utilisateur lui-même ou certains rôles
        $allowedRoles = ['ProP', 'Admin', 'Apollo']; // noms des rôles dans ta table Role
        $currentUserRole = $currentUser->getRole()?->getName(); // récupère le nom du rôle de l'utilisateur

        if ($currentUser->getId() !== $id && !in_array($currentUserRole, $allowedRoles)) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Accès interdit'
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur introuvable'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Données JSON invalides'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Champs autorisés à mettre à jour
        if (isset($data['lastname'])) {
            $user->setLastname($data['lastname']);
        }
        if (isset($data['firstname'])) {
            $user->setFirstname($data['firstname']);
        }
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        // Mise à jour du mot de passe si fourni
        if (!empty($data['password'])) {
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        if (isset($data['status'])) $user->setStatus((bool)$data['status']);

        $entityManager->flush();

        return $this->json(
            $this->apiResponse->success(
                data: [$user],
                message: 'Profil utilisateur mis à jour avec succès'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'users']
        );
    }

    #[Route('/api/user/details/info', name: 'api_user_info', methods: ['GET'])]
    public function getUserInfo(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof UserInterface) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $userInfo = [
            'id' => $user->getId(),
            'firstname' => $user->getFirstname(), // prénom
            'lastname' => $user->getLastname(),       // nom
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'titre' => $user->getRole()?->getDescription() ?? null,
            'country' => $user->getPharmacy()?->getCountry()->getName() ?? null,
            'city' => $user->getPharmacy()?->getCity()->getName() ?? null,
            'address' => $user->getPharmacy()?->getAddress() ?? null,
            'pharmacy_id' => $user->getPharmacy()?->getId() ?? null,
            'pharmacy' => $user->getPharmacy()?->getName() ?? null,
            //'company' => $user->getPharmacy()?->getSlug() ?? "pharmacie",
            "company_type" => $user->getPharmacy()?->getCompany()?->getName() ?? null,
            'company' => $user->getPharmacy()?->getSlug() ?? null,
        ];
        return $this->json($userInfo);
    }

    //Delete User
    #[Route('/api/user/delete/{id}', name: 'api_delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->json(['message' => 'User deleted successfully'], 200);
    }

    //Change password
    #[Route('/api/user/{id}/change-password', name: 'api_user_change_password', methods: ['PUT'])]
    public function changePassword(
        int $id,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security
    ): JsonResponse {
        $currentUser = $security->getUser();

        // Sécurité : empêcher de modifier un autre utilisateur si non admin
        if ($currentUser->getId() !== $id) {
            return new JsonResponse(['message' => 'Accès non autorisé.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        $user = $userRepository->find($id);

        if (!$user || !$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['message' => 'Mot de passe actuel incorrect.'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        return new JsonResponse(['message' => 'Mot de passe mis à jour avec succès.']);
    }

}
