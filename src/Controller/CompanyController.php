<?php

namespace App\Controller;
use App\Entity\QRUser;
use App\Entity\User;
use App\Helpers\Helpers;
use App\Entity\Company;
use App\Services\CompanyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Security\ApiResponseService;

class CompanyController extends AbstractController
{
    private $companyService;
    private $helpers;
    private $apiResponse;

    public function __construct(CompanyService $companyService, Helpers $helpers, ApiResponseService $apiResponse)
    {
        $this->companyService = $companyService;
        $this->helpers = $helpers;
         $this->apiResponse = $apiResponse;
    }

    #[Route('/api/company/create', name: 'company_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {

        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return new JsonResponse(
                $this->apiResponse->error(
                    message: "Utilisateur non authentifié",
                    statusCode: Response::HTTP_UNAUTHORIZED
                ),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Lecture JSON
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(
                $this->apiResponse->error(
                    message: 'Format JSON invalide.',
                    statusCode: Response::HTTP_BAD_REQUEST
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        // Champs requis
        $requiredFields = ['name'];
        $missingFields = $this->helpers->validateRequiredFields($data, $requiredFields);
        if (!empty($missingFields)) {
            return new JsonResponse(
                $this->apiResponse->error(
                    message: 'Champs obligatoires manquants: ' . implode(', ', $missingFields),
                    statusCode: Response::HTTP_BAD_REQUEST
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode($request->getContent(), true);
        $name = $data["name"];
        $description = $data["description"];
        $slugs = strtolower($name);
        $slug = str_replace(' ', '-', $slugs);
        if (empty($data)) {
            $data = $request->request->all();
        }
        return $this->companyService->add($data);
    }

    #[Route('/api/company/infos/{slug}', name: 'company_show', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        return $this->companyService->show($slug);
    }

    #[Route('/api/company/update/{id}', name: 'company_update', methods: ['POST'])]
    public function update(Request $request, Company $company): JsonResponse
    {
         $data = $request->request->all();
         $file = $request->files->get('logo');
         $name = $data["name"];
         $slugs = strtolower($name);
         $slug = str_replace(' ', '-', $slugs);
         //$this->helpers->generateCompanyQR($slug, $company);
        return $this->companyService->update($data, $company ,$file);
    }

    #[Route('/api/company/{id}', name: 'company_delete', methods: ['DELETE'])]
    public function delete(Company $company): JsonResponse
    {
        return $this->companyService->delete($company);
    }

    #[Route('/api/companies', name: 'company_all', methods: ['GET'])]
    public function all(): JsonResponse
    {
        return $this->companyService->all();
    }

    //Avoir les détails d'une entreprise par son ID
    #[Route('/api/company/details/{id}', name: 'company_details', methods: ['GET'])]
    public function showdetails(?Company $company): JsonResponse
    {
        if (!$company) {
            return $this->json([
                'success' => false,
                'error' => 'Company not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'slug' => $company->getSlug(),
                'description' => $company->getDescription(),
            ],
        ]);
    }
}
