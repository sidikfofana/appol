<?php

namespace App\Controller;

use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Security\ApiResponseService;
use App\Services\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Helpers\Helpers;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentMethodController extends AbstractController
{

    private $entityManager;
    private $serializer;
    private $validator;
    private $Helpers;
    private $apiResponse;
    private $tokenStorage;
    private $fileUploader;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        Helpers $Helpers,
        ApiResponseService $apiResponse,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage,
        FileUploader $fileUploader,
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->Helpers = $Helpers;
        $this->apiResponse = $apiResponse;
        $this->fileUploader = $fileUploader;
    }


    #[Route('/api/payment-method/list', name: 'api_payment_method_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $paymentMethods = $this->entityManager->getRepository(PaymentMethod::class)
            ->createQueryBuilder('pm')
            ->where('pm.status = :status')
            ->setParameter('status', true)
            ->orderBy('pm.libelle', 'ASC')
            ->getQuery()
            ->getResult();

        $formatted = array_map(fn($pm) => [
            "id" => $pm->getId(),
            "libelle" => $pm->getLibelle(),
            "logo" => $pm->getImage(),
            "status" => $pm->isStatus(),
        ], $paymentMethods);

        return $this->json(
            $this->apiResponse->success(
                data: $formatted,
                message: "Liste des moyens de paiement récupérée"
            )
        );
    }


    #[Route('/api/payment-method/create', name: 'api_payment_method_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {

            /** @var \App\Entity\User|null $user */
            $connectedUser = $this->getUser();

            if (!$connectedUser) {
                return $this->json(
                    $this->apiResponse->error(
                        message: 'Utilisateur non authentifié'
                    ),
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $data = $request->request->all();
            $imageFile = $request->files->get('logo');

            // Validate required fields
            if (!isset($data['libelle'])) {
                return $this->json(
                    $this->apiResponse->error("Le libellé est obligatoire", Response::HTTP_BAD_REQUEST),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $paymentMethod = new PaymentMethod();
            $paymentMethod->setLibelle($data['libelle']);
            $paymentMethod->setStatus(isset($data['status']) ? (bool)$data['status'] : true);

            // Upload image if provided
            if ($imageFile) {
                $imageName = $this->fileUploader->upload($imageFile, "payment_methods");
                $paymentMethod->setImage($imageName);
            }

            // Validate entity
            $errors = $this->validator->validate($paymentMethod);
            if (count($errors) > 0) {
                return $this->json(
                    $this->apiResponse->error((string)$errors, Response::HTTP_BAD_REQUEST),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->entityManager->persist($paymentMethod);
            $this->entityManager->flush();

            return $this->json(
                $this->apiResponse->success(
                    message: "Méthode de paiement créée avec succès",
                    statusCode: Response::HTTP_CREATED,
                    extra: [
                        "id" => $paymentMethod->getId(),
                        "libelle" => $paymentMethod->getLibelle(),
                        "logo" => $paymentMethod->getImage(),
                        "status" => $paymentMethod->isStatus()
                    ]
                ),
                Response::HTTP_CREATED
            );

        } catch (\Exception $e) {
            return $this->json(
                $this->apiResponse->error("Erreur : ".$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /** UPDATE */
    #[Route('/api/payment-method/update/{id}', name: 'api_payment_method_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var \App\Entity\User|null $user */
        $connectedUser = $this->getUser();

        if (!$connectedUser) {
            return $this->json(
                $this->apiResponse->error(
                    message: 'Utilisateur non authentifié'
                ),
                Response::HTTP_UNAUTHORIZED
            );
        }

        $paymentMethod = $this->entityManager->getRepository(PaymentMethod::class)->find($id);

        if (!$paymentMethod) {
            return $this->json(
                $this->apiResponse->error("Méthode de paiement introuvable", Response::HTTP_NOT_FOUND),
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $request->request->all();
        $imageFile = $request->files->get('image');

        if (isset($data['libelle'])) {
            $paymentMethod->setLibelle($data['libelle']);
        }

        if (isset($data['status'])) {
            $paymentMethod->setStatus((bool) $data['status']);
        }

        if ($imageFile) {
            $imageName = $this->fileUploader->upload($imageFile, "payment_methods");
            $paymentMethod->setImage($imageName);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->apiResponse->success(
                message: "Méthode mise à jour",
                extra: [
                    "id" => $paymentMethod->getId(),
                    "libelle" => $paymentMethod->getLibelle(),
                    "logo" => $paymentMethod->getImage(),
                    "status" => $paymentMethod->isStatus()
                ]
            ),
            Response::HTTP_OK
        );
    }

    /** DELETE */
    #[Route('/api/payment-method/delete/{id}', name: 'api_payment_method_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var \App\Entity\User|null $user */
        $connectedUser = $this->getUser();

        if (!$connectedUser) {
            return $this->json(
                $this->apiResponse->error(
                    message: 'Utilisateur non authentifié'
                ),
                Response::HTTP_UNAUTHORIZED
            );
        }

        $paymentMethod = $this->entityManager->getRepository(PaymentMethod::class)->find($id);

        if (!$paymentMethod) {
            return $this->json(
                $this->apiResponse->error("Méthode introuvable", Response::HTTP_NOT_FOUND),
                Response::HTTP_NOT_FOUND
            );
        }

        $this->entityManager->remove($paymentMethod);
        $this->entityManager->flush();

        return $this->json(
            $this->apiResponse->success(message: "Méthode de paiement supprimée"),
            Response::HTTP_OK
        );
    }
}
