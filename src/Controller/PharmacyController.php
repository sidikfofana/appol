<?php

namespace App\Controller;

use App\Entity\City;
use App\Entity\Pharmacy;
use App\Entity\Country;
use App\Entity\User;
use App\Entity\Company;
use App\Services\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Helpers\Helpers;
use OpenApi\Attributes as OA;
use App\Security\ApiResponseService;
use Symfony\Component\String\Slugger\SluggerInterface;

final class PharmacyController extends AbstractController
{
    private $entityManager;
    private $apiResponse;
    private $Helpers;
    private $validator;
    private $slugger;
    private $fileUploader;

    public function __construct(
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
        Helpers $Helpers,
        ValidatorInterface $validator,
        SluggerInterface $slugger,
        FileUploader $fileUploader,
    ) {
        $this->entityManager = $entityManager;
        $this->apiResponse = $apiResponse;
        $this->validator = $validator;
        $this->Helpers = $Helpers;
        $this->apiResponse = $apiResponse;
        $this->slugger = $slugger;
        $this->fileUploader = $fileUploader;
    }

    //Toutes les pharmacies
    #[Route('/api/pharmacy/listAll', name: 'app_list_all_pharmacy', methods: ['GET'])]
    #[OA\Get(
        path: '/api/pharmacy/listAll',
        summary: 'Liste de toutes les pharmacies',
        tags: ['Pharmacy'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 12),
                            new OA\Property(property: 'name', type: 'string', example: 'Pharmacie Sainte Bernadette'),
                            new OA\Property(property: 'address', type: 'string', example: 'Cocody, Abidjan'),
                            new OA\Property(property: 'is_online', type: 'boolean', example: false)
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non autorisé')
        ]
    )]
    public function listAll(): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                $this->apiResponse->error(
                    message: 'Utilisateur non authentifié'
                ),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Récupération de toutes les pharmacies
        $pharmacies = $this->entityManager->getRepository(Pharmacy::class)
            ->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Aucune pharmacie trouvée
        if (!$pharmacies || count($pharmacies) === 0) {
            return $this->json(
                $this->apiResponse->success(
                    data: [],
                    message: "Aucune pharmacie enregistrée"
                ),
                Response::HTTP_OK
            );
        }

        return $this->json(
            $this->apiResponse->success(
                data: $pharmacies,
                message: 'Pharmacies retrieved successfully'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'pharmacies']
        );
    }


    //Pharmacies par ville
    #[Route('/api/pharmacy/list/{cityId}', name: 'app_list_pharmacy_by_city', methods: ['GET'])]
    #[OA\Get(
        path: '/api/pharmacy/list/{cityId}',
        summary: 'Liste des pharmacies d’une ville',
        tags: ['Pharmacy'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'cityId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'ID de la ville'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 12),
                            new OA\Property(property: 'name', type: 'string', example: 'Pharmacie Sainte Bernadette'),
                            new OA\Property(property: 'address', type: 'string', example: 'Cocody, Abidjan'),
                            new OA\Property(property: 'is_online', type: 'boolean', example: false)
                        ]
                    )
                )
            ),
            new OA\Response(response: 404, description: 'Ville introuvable'),
            new OA\Response(response: 401, description: 'Non autorisé')
        ]
    )]
    public function list(int $cityId): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                $this->apiResponse->error(
                    message: 'Utilisateur non authentifié'
                ),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Vérifier si la ville existe
        $city = $this->entityManager->getRepository(City::class)->find($cityId);

        if (!$city) {
            return $this->json(
                $this->apiResponse->error(
                    message: "La ville avec l’ID $cityId est introuvable"
                ),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupération des pharmacies liées
        $pharmacies = $this->entityManager->getRepository(Pharmacy::class)
            ->createQueryBuilder('p')
            ->where('p.city = :city')
            ->setParameter('city', $city)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Vérifier si aucune pharmacie n'est enregistrée pour cette ville
        if (!$pharmacies || count($pharmacies) === 0) {
            return $this->json(
                $this->apiResponse->success(
                    data: [],
                    message: "Cette ville n'a pas de pharmacies enregistrées"
                ),
                Response::HTTP_OK
            );
        }

        return $this->json(
            $this->apiResponse->success(
                data: $pharmacies,
                message: 'Pharmacies retrieved successfully'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'pharmacies']
        );
    }

    //Créer une Pharmacie
    #[Route('/api/pharmacy/create', name: 'app_create_pharmacy', methods: ['POST'])]
    public function createPharmacy(Request $request): Response
    {
        try {
            $data = $request->request->all();
            $file = $request->files->get('logo');  

            //dd($data["opening_day"]);

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
            $requiredFields = ['company_id', 'country_id', 'city_id', 'owner_id', 'name', 'address', 'contact'];
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

            // Vérifier country_id
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

            // Vérifier city_id
            $city = $this->entityManager->getRepository(City::class)->find($data['city_id']);
            if (!$city) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Invalide city_id',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            //Vérifier le company_id
            $company = $this->entityManager->getRepository(Company::class)->find($data["company_id"]);
            if (!$company) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Invalide company_id',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérifier owner_id
            $owner = $this->entityManager->getRepository(User::class)->find($data['owner_id']);
            if (!$owner) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Invalide owner_id',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérifier si le owner a déjà une pharmacy
            if ($owner->getPharmacy()) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Ce propriétaire possède déjà une pharmacie.',
                        statusCode: Response::HTTP_CONFLICT
                    ),
                    Response::HTTP_CONFLICT
                );
            }

            // Création de la pharmacie
            $pharmacy = new Pharmacy();
            $pharmacy->setCountry($country);
            $pharmacy->setCity($city);
            $pharmacy->setCompany($company);
            $pharmacy->setOwner($owner);
            $pharmacy->setName($data['name']);
            $pharmacy->setAddress($data['address']);
            $pharmacy->setContact($data['contact']);
            $pharmacy->setOpeningDay($data['opening_day'] ?? ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi']);
            $pharmacy->setIsOnline($data['is_online'] ?? false);

            // Génération du slug à partir du name
            $slug = strtolower($this->slugger->slug($data['name']));
            $pharmacy->setSlug($slug);

            $words = explode('-', $slug);

            // Prendre 1 lettre par mot (max 3 mots)
            $short = '';
            foreach ($words as $word) {
                if (strlen($short) < 3) {
                    $short .= substr($word, 0, 1);
                }
            }

            // Sécurité si nom trop court
            $short = str_pad($short, 3, 'X');

            // Générer un suffixe unique (7 caractères)
            $unique = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));

            // Code final = 3 + 7 = 10 caractères
            $code = $short . $unique;

            // Enregistrer
            $pharmacy->setCode($code);

            // Relation inverse obligatoire : mettre le pharmacy_id à jour dans la table user
            $owner->setPharmacy($pharmacy);

            // Upload du fichier (optionnel)
            if ($file) {
                $uploadedFilePath = $this->fileUploader->upload($file, "logo");
                $pharmacy->setLogo($uploadedFilePath);
            }

            // Validation
            $errors = $this->validator->validate($pharmacy);
            if (count($errors) > 0) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: (string) $errors,
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->entityManager->persist($pharmacy);
            $this->entityManager->flush();

            return new JsonResponse(
                $this->apiResponse->success(
                    message: 'Pharmacie créée avec succès !',
                    statusCode: Response::HTTP_CREATED,
                    extra: ['pharmacy_id' => $pharmacy->getId()]
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

    //Update Pharamacie
    #[Route('/api/pharmacy/update/{id}', name: 'app_update_pharmacy', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/pharmacy/update/{id}',
        summary: 'Mettre à jour une pharmacie',
        tags: ['Pharmacy'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Pharmacie Santé+'),
                    new OA\Property(property: 'address', type: 'string', example: 'Cocody Angré'),
                    new OA\Property(property: 'country_id', type: 'integer', example: 14),
                    new OA\Property(property: 'city_id', type: 'integer', example: 6),
                    new OA\Property(property: 'owner_id', type: 'integer', example: 12),
                    new OA\Property(
                        property: 'opening_day',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['Lundi','Mardi','Mercredi']
                    ),
                    new OA\Property(property: 'is_online', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pharmacie mise à jour'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 404, description: 'Pharmacie introuvable'),
            new OA\Response(response: 401, description: 'Non autorisé')
        ]
    )]

    // Update Pharmacie (multipart/form-data)
    #[Route('/api/pharmacy/update/{id}', name: 'app_update_pharmacy', methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Autorisation
        $allowedRoles = ['Admin', 'Apollo'];
        $currentUserRole = $currentUser->getRole()?->getName();

        if (!in_array($currentUserRole, $allowedRoles)) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Accès interdit'
            ], Response::HTTP_FORBIDDEN);
        }

        $pharma = $this->entityManager->getRepository(Pharmacy::class)->find($id);
        if (!$pharma) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Pharmacie introuvable'
            ], Response::HTTP_NOT_FOUND);
        }

        // ✅ multipart/form-data
        $data = $request->request->all();
        $file = $request->files->get('logo');

        if (empty($data) && !$file) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Aucune donnée reçue'
            ], Response::HTTP_BAD_REQUEST);
        }

        // =====================
        // Mise à jour des champs
        // =====================

        if (!empty($data['name'])) {
            $pharma->setName($data['name']);
            $slug = strtolower($this->slugger->slug($data['name']));
            $pharma->setSlug($slug);
        }

        if (!empty($data['address'])) {
            $pharma->setAddress($data['address']);
        }

        if (!empty($data['country_id'])) {
            $country = $this->entityManager->getRepository(Country::class)->find($data['country_id']);
            if (!$country) {
                return $this->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Pays invalide'
                ], Response::HTTP_BAD_REQUEST);
            }
            $pharma->setCountry($country);
        }

        if (!empty($data['city_id'])) {
            $city = $this->entityManager->getRepository(City::class)->find($data['city_id']);
            if (!$city) {
                return $this->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Ville invalide'
                ], Response::HTTP_BAD_REQUEST);
            }
            $pharma->setCity($city);
        }

        if (!empty($data['owner_id'])) {
            $owner = $this->entityManager->getRepository(User::class)->find($data['owner_id']);
            if (!$owner) {
                return $this->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Propriétaire invalide'
                ], Response::HTTP_BAD_REQUEST);
            }
            $pharma->setOwner($owner);
        }

        if (isset($data['opening_day'])) {
            // multipart => souvent string JSON
            $openingDays = is_string($data['opening_day'])
                ? json_decode($data['opening_day'], true)
                : $data['opening_day'];

            $pharma->setOpeningDay($openingDays ?? []);
        }

        if (isset($data['is_online'])) {
            $pharma->setIsOnline(filter_var($data['is_online'], FILTER_VALIDATE_BOOLEAN));
        }

        // =====================
        // Upload logo (optionnel)
        // =====================
        if ($file) {
            $logoPath = $this->fileUploader->upload($file, 'logo');
            $pharma->setLogo($logoPath);
        }

        // Validation
        $errors = $this->validator->validate($pharma);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Pharmacie mise à jour avec succès',
            'data' => $pharma
        ], Response::HTTP_OK, [], ['groups' => 'pharmacies']);
    }

    //Delete Pharmacie
    #[Route('/api/pharmacy/delete/{id}', name: 'app_delete_pharmacy', methods: ['DELETE'])]
    public function delete(int $id): Response
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

        // Autorisation : certains rôles seulement
        $allowedRoles = ['Admin', 'Apollo'];
        $currentUserRole = $currentUser->getRole()?->getName();

        $pharma = $this->entityManager->getRepository(Pharmacy::class)->find($id);
        if (!$pharma) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Pharmacie introuvable'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($currentUserRole, $allowedRoles)) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Accès interdit'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($pharma);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Pharmacie supprimée avec succès'
        ], Response::HTTP_OK);
    }

    //Avoir les détails d'une entreprise par son ID
    #[Route('/api/pharmacy/details/{id}', name: 'pharmacy_details', methods: ['GET'])]
    public function showdetails(?Pharmacy $pharmacy): JsonResponse
    {
        if (!$pharmacy) {
            return $this->json([
                'success' => false,
                'error' => 'Company not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $pharmacy->getId(),
                'name' => $pharmacy->getName(),
                'company_id' => $pharmacy->getCompany()->getId(),
                'address' => $pharmacy->getAddress(),
                'contact' => $pharmacy->getContact(),
                'country_id' => $pharmacy->getCountry()->getId(),
                'owner_id' => $pharmacy->getOwner()->getId(),
                'opening_day' => $pharmacy->getOpeningDay(),
                'city_id' => $pharmacy->getCity()->getId(),
                'is_online' => $pharmacy->isOnline(),
            ],
        ]);
    }



}
