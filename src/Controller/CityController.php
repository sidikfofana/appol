<?php

namespace App\Controller;

use App\Entity\City;
use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use App\Security\ApiResponseService;

final class CityController extends AbstractController
{
    private $entityManager;
    private $apiResponse;

    public function __construct(
        EntityManagerInterface $entityManager,
        ApiResponseService $apiResponse,
    ) {
        $this->entityManager = $entityManager;
        $this->apiResponse = $apiResponse;
    }

    #[Route('/api/city/list/{countryId}', name: 'app_list_city', methods: ['GET'])]
    #[OA\Get(
        path: '/api/city/list/{countryId}',
        summary: 'Liste des villes d’un pays',
        tags: ['City'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'countryId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'ID du pays'
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
                            new OA\Property(property: 'id', type: 'integer', example: 6),
                            new OA\Property(property: 'name', type: 'string', example: 'Cocody')
                        ]
                    )
                )
            ),
            new OA\Response(response: 404, description: 'Pays introuvable'),
            new OA\Response(response: 401, description: 'Non autorisé')
        ]
    )]
    public function list(int $countryId): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        // if (!$user) {
        //     return $this->json(
        //         $this->apiResponse->error(
        //             message: 'Utilisateur non authentifié'
        //         ),
        //         Response::HTTP_UNAUTHORIZED
        //     );
        // }

        // Vérifier si le pays existe
        $country = $this->entityManager->getRepository(Country::class)->find($countryId);

        if (!$country) {
            return $this->json(
                $this->apiResponse->error(
                    message: "Le pays avec l’ID $countryId est introuvable"
                ),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupération des villes liées au pays
        $cities = $this->entityManager->getRepository(City::class)
            ->createQueryBuilder('c')
            ->where('c.country = :country')
            ->setParameter('country', $country)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Vérifier si le pays n’a aucune ville
        if (!$cities || count($cities) === 0) {
            return $this->json(
                $this->apiResponse->success(
                    data: [],
                    message: "Ce pays n'a pas de villes enregistrées"
                ),
                Response::HTTP_OK
            );
        }

        return $this->json(
            $this->apiResponse->success(
                data: $cities,
                message: 'Cities retrieved successfully'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'cities'] // ou un groupe dédié si tu veux
        );
    }
}
