<?php

namespace App\Controller;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use App\Security\ApiResponseService;

final class CountryController extends AbstractController
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

    #[Route('/api/country/list', name: 'app_list_country', methods: ['GET'])]
    #[OA\Get(
        path: '/api/country/list',
        summary: 'Liste des pays actifs',
        tags: ['Country'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 14),
                            new OA\Property(property: 'name', type: 'string', example: 'Côte d\'Ivoire'),
                            new OA\Property(property: 'iso_code', type: 'string', example: 'CI'),
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non autorisé'
            )
        ]
    )]
    public function list(EntityManagerInterface $entityManager): Response
    {
        // /** @var \App\Entity\User|null $user */
        // $user = $this->getUser();

        // if (!$user) {
        //     return $this->json(
        //         $this->apiResponse->error(
        //             message: 'Utilisateur non authentifié'
        //         ),
        //         Response::HTTP_UNAUTHORIZED
        //     );
        // }

        $countries = $entityManager->getRepository(Country::class)
            ->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Placer la Côte d'Ivoire en premier
        usort($countries, function ($a, $b) {
            if ($a->getName() === "Côte d'Ivoire") return -1;
            if ($b->getName() === "Côte d'Ivoire") return 1;
            return strcmp($a->getName(), $b->getName());
        });

        return $this->json(
            $this->apiResponse->success(
                data: $countries,
                message: 'Countries retrieved successfully'
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'countries']
        );
    }
}
