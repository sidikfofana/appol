<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\Pharmacy;
use App\Helpers\Helpers;
use App\Enum\OrderStatus;
use App\Enum\WithdrawalType;
use OpenApi\Attributes as OA;
use App\Services\FileUploader;
use App\Repository\OrderRepository;
use App\Security\ApiResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\KernelInterface;



class OrderController extends AbstractController
{
    private $entityManager;
    private $serializer;
    private $validator;
    private $Helpers;
    private $apiResponse;
    private $tokenStorage;
    private $fileUploader;
    private RequestStack $requestStack;
    private KernelInterface $kernel;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        Helpers $Helpers,
        ApiResponseService $apiResponse,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage,
        FileUploader $fileUploader,
        RequestStack $requestStack,
        KernelInterface $kernel,
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->Helpers = $Helpers;
        $this->apiResponse = $apiResponse;
        $this->fileUploader = $fileUploader;
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
    }

    #[Route('/api/orders/create', name: 'api_create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        try {
            //  1. Récupération form-data
            $data = $request->request->all();

            $identityDocument = $request->files->get('identity_document');
            $prescriptionFiles = $request->files->all('prescription_files');
            //dd($prescriptionFiles);

            //  2. Champs requis
            $requiredFields = ['user_id', 'withdrawal_type', 'pharmacy_id'];
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

            // 3. Utilisateur connecté
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

            // 4. Vérification pharmacy_id
            $pharmacy = $this->entityManager->getRepository(Pharmacy::class)->find($data['pharmacy_id']);
            if (!$pharmacy) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'pharmacy_id invalide.',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 5. Vérification du document d'identité
            if (!$identityDocument) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Le fichier identity_document est obligatoire.',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 6. Upload identity_document
            $uploadedIdentity = $this->fileUploader->upload($identityDocument, "identity_document");

            // 7. Upload prescription_files[]
            $prescriptionPaths = [];
            if (!empty($prescriptionFiles)) {
                foreach ($prescriptionFiles as $file) {
                    $prescriptionPaths[] = $this->fileUploader->upload($file, "prescription_files");
                }
            }

            // 8. Générer UIDN unique basé sur le code de la pharmacie
            $year = date('Y');
            $pharmacyCode = $pharmacy->getCode() ?? 'PHR';
            $lastOrder = $this->entityManager->getRepository(Order::class)
                ->createQueryBuilder('o')
                ->where('o.pharmacy = :pharmacy')
                ->setParameter('pharmacy', $pharmacy)
                ->orderBy('o.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($lastOrder) {
                // Extraire le compteur existant
                preg_match('/(\d+)$/', $lastOrder->getUidn(), $matches);
                $counter = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
            } else {
                $counter = 1;
            }

            $uidn = sprintf('ORD-%s-%s%03d', $year, $pharmacyCode, $counter);

            // 9. Création de l'objet Order
            $order = new Order();
            $order->setUidn($uidn);
            $order->setUser($connectedUser);
            $order->setPharmacy($pharmacy);
            $order->setIdentityDocument($uploadedIdentity);
            $order->setPrescriptionFiles($prescriptionPaths);

            // 9. Enum WithdrawalType
            try {
                $order->setWithdrawalType(
                    WithdrawalType::fromInt($data['withdrawal_type'])
                );
            } catch (\InvalidArgumentException $e) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Type de retrait invalide.',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 10. Comment : optionnel
            $order->setComment($data['comment'] ?? null);
            $order->setDescription($data['description'] ?? null);

            // 11. Status Enum → SUBMITTED (par défaut)
            $order->setStatus(OrderStatus::SUBMITTED);

            // 12. Dates
            $order->setCreatedDate(new \DateTime());
            $order->setUpdatedDate(new \DateTime());

            // 13. Validation entité Symfony
            $errors = $this->validator->validate($order);
            if (count($errors) > 0) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: (string) $errors,
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 14. Persistance
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            // 15. Réponse
            return new JsonResponse(
                $this->apiResponse->success(
                    message: 'Commande créée avec succès !',
                    statusCode: Response::HTTP_CREATED,
                    extra: [
                        "id" => $order->getId(),
                        "uidn" => $order->getUidn(),
                        "pharmacy" => $pharmacy->getName(),
                        "withdrawal_type" => $order->getWithdrawalType()->value,
                        "status" => $order->getStatus()->value,
                        "identity_document" => $order->getIdentityDocument(),
                        "prescription_files" => $order->getPrescriptionFiles(),
                        "created_date" => $order->getCreatedDate()->format('Y-m-d H:i:s'),
                    ]
                ),
                Response::HTTP_CREATED
            );

        // 16. Exceptions propres
        // --------------------------
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
                    message: 'Une erreur est survenue : ' . $e->getMessage(),
                    statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    //List des orders by User
    #[Route('/api/order/list', name: 'app_list_orders', methods: ['GET'])]
    #[OA\Get(
        path: '/api/order/list',
        summary: 'Liste des commandes de l’utilisateur',
        tags: ['Order'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 45),
                            new OA\Property(property: 'pharmacy_name', type: 'string', example: 'Pharmacie Sainte Bernadette'),
                            new OA\Property(property: 'identity_document', type: 'string', example: '/uploads/identity_document/abc.jpg'),
                            new OA\Property(
                                property: 'prescription_files',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                example: [
                                    "/uploads/prescription_files/pres1.jpg",
                                    "/uploads/prescription_files/pres2.jpg"
                                ]
                            ),
                            new OA\Property(property: 'status', type: 'string', example: 'pending'),
                            new OA\Property(property: 'created_at', type: 'string', example: '2025-03-10 14:22:00')
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non autorisé')
        ]
    )]
    #[Route('/api/order/list/user/{userId}', name: 'api_order_list_by_user', methods: ['GET'])]
    public function listByUser(int $userId): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Trouver le user
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer ses commandes
        $orders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->leftJoin('o.pharmacy', 'p')
            ->addSelect('p')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.created_date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(
            $this->apiResponse->success(
                data: $this->formatOrders($orders),
                message: "Orders retrieved successfully"
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'orders']
        );
    }

    //Commandes non traitées d'une pharmacie
    #[Route('/api/order/list/pharmacy/{pharmacyId}', name: 'api_order_list_by_pharmacy', methods: ['GET'])]
    public function listByPharmacy(string $slug): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        //dd($pharmacyId);

        // Trouver la pharmacie
        $pharmacy = $this->entityManager->getRepository(Pharmacy::class)->find($pharmacyId);

        if (!$pharmacy) {
            return $this->json(
                $this->apiResponse->error(message: "Pharmacie introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer ses commandes
        $orders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->where('o.pharmacy = :ph')
            ->andWhere('o.status NOT IN (:statuses)')
            ->setParameter('ph', $pharmacy)
            ->setParameter('statuses', [
                OrderStatus::DELIVERED->value,
                OrderStatus::PAID->value
            ])
            ->orderBy('o.created_date', 'DESC')
            ->getQuery()
            ->getResult();


        return $this->json(
            $this->apiResponse->success(
                data: $this->formatOrders($orders),
                message: "Orders retrieved successfully"
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'orders']
        );
    }

    //Historique des commandes d'une pharmacie
    #[Route('/api/order/history/pharmacy/{pharmacyId}', name: 'api_order_historique_by_pharmacy', methods: ['GET'])]
    public function historiqueByPharmacy(int $pharmacyId): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Trouver la pharmacie
        $pharmacy = $this->entityManager->getRepository(Pharmacy::class)->find($pharmacyId);

        if (!$pharmacy) {
            return $this->json(
                $this->apiResponse->error(message: "Pharmacie introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer ses commandes
        $orders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->where('o.pharmacy = :ph')
            ->setParameter('ph', $pharmacy)
            ->orderBy('o.created_date', 'DESC')
            ->getQuery()
            ->getResult();


        return $this->json(
            $this->apiResponse->success(
                data: $this->formatOrders($orders),
                message: "Orders retrieved successfully"
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'orders']
        );
    }

    //Commandes du jour
    #[Route('/api/order/list/pharmacy/jour/{pharmacyId}', name: 'api_order_list_jour_by_pharmacy', methods: ['GET'])]
    public function listByPharmacyDay(int $pharmacyId): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Trouver la pharmacie
        $pharmacy = $this->entityManager->getRepository(Pharmacy::class)->find($pharmacyId);

        if (!$pharmacy) {
            return $this->json(
                $this->apiResponse->error(message: "Pharmacie introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Début et fin du jour courant
        $startOfDay = new \DateTimeImmutable('today'); // 00:00:00
        $endOfDay = new \DateTimeImmutable('tomorrow');

        // Récupérer ses commandes
        // $orders = $this->entityManager->getRepository(Order::class)
        //     ->createQueryBuilder('o')
        //     ->leftJoin('o.user', 'u')
        //     ->addSelect('u')
        //     ->where('o.pharmacy = :ph')

        //     ->setParameter('ph', $pharmacy)
        //     ->orderBy('o.created_date', 'DESC')
        //     ->getQuery()
        //     ->getResult();

        $orders = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->where('o.pharmacy = :ph')
            ->andWhere('o.created_date >= :startOfDay')
            ->andWhere('o.created_date < :endOfDay')
            ->setParameter('ph', $pharmacy)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('o.created_date', 'DESC')
            ->getQuery()
            ->getResult();


        return $this->json(
            $this->apiResponse->success(
                data: $this->formatOrders($orders),
                message: "Orders retrieved successfully"
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'orders']
        );
    }

    private function formatOrders(array $orders): array
    {
        $baseUrl = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
        $result  = [];

        foreach ($orders as $order) {
            //var_dump($order->getPrescriptionFiles());

            // Identity document
            $identityDocPath = $order->getIdentityDocument()
                ? $baseUrl . "/identity_document/" . $order->getIdentityDocument()
                : null;

            // Prescription files
            $prescriptions = [];
            if ($order->getPrescriptionFiles()) {
                $files = $order->getPrescriptionFiles();
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $prescriptions[] = $baseUrl . "/prescription_files/" . $file;
                    }
                }
            }

            // Order items
            $orderItems = [];
            foreach ($order->getOrderItems() as $item) {
                $orderItems[] = [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                    'price' => $item->getPrice(),
                    'created_at' => $item->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'user_id' => $item->getUser()?->getId(),
                ];
            }

            // QR Code
            $qrCodeRelativePath = 'qrcode/qrcode-' . $order->getUidn() . '.png';
            $qrCodeAbsolutePath = $this->kernel->getProjectDir() . '/public/' . $qrCodeRelativePath;

            $qrCodeUrl = file_exists($qrCodeAbsolutePath)
                ? $baseUrl . '/' . $qrCodeRelativePath
                : null;

            $result[] = [
                'id' => $order->getId(),
                'user_id' => $order->getUser()?->getId(),
                'user_name' => $order->getUser()?->getFirstname() . ' ' . $order->getUser()?->getLastname(),
                'pharmacy_name' => $order->getPharmacy()?->getName(),
                'pharmacy_contact' => $order->getPharmacy()?->getContact(),
                'identity_document' => $identityDocPath,
                'prescription_files' => $prescriptions,
                'status' => $order->getStatus(),
                'created_at' => $order->getCreatedDate()->format('Y-m-d H:i:s'),
                'total_amount' => $order->getTotalAmount(),
                'uidn' => $order->getUidn(),
                'qr_code' => $qrCodeUrl,
                'description' => $order->getDescription(),
                'items_count' => $order->getItemsCount(),
                'items' => $orderItems,
                'type_retrait' => $order->getWithdrawalType(),
                'payments' => array_map(function ($payment) use ($baseUrl) {
                    return [
                        'id' => $payment->getId(),
                        'payment_method' => $payment->getPayment()?->getLibelle(),
                        'transaction_reference' => $payment->getTransactionReference(),
                        'transaction_status' => $payment->getTransactionStatus(),
                    ];
                }, $order->getPayment()->toArray()),
            ];
        }

        return $result;
    }

    #[Route('/api/orders/stats', name: 'orders_stats', methods: ['POST'])]
    public function stats(Request $request, OrderRepository $repo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $pharmacyId = $data['pharmacy_id'] ?? null;
        $startDate = isset($data['start_date']) ? new \DateTime($data['start_date']) : null;
        $endDate = isset($data['end_date']) ? new \DateTime($data['end_date']) : null;
        $statusFilter = $data['status'] ?? null;

        $rawData = $repo->getFilteredStats($pharmacyId, $startDate, $endDate, $statusFilter);

        // Stats par défaut
        $stats = [
            'total' => 0,
            'submitted' => 0,
            'billed' => 0,
            'paid' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];

        foreach ($rawData as $row) {

            // Doctrine renvoie un enum → on récupère la valeur
            $status = $row['status'] instanceof \App\Enum\OrderStatus
                ? $row['status']->value
                : (string)$row['status'];

            $count = (int)$row['total_orders'];

            $stats['total'] += $count;

            if (array_key_exists($status, $stats)) {
                $stats[$status] = $count;
            }
        }

        return $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    //Détails d'une commande à partir de l'unid
    #[Route('/api/order/details/{uidn}', name: 'api_order_details_by_uidn', methods: ['GET'])]
    public function getOrderDetailsByUidn(string $uidn): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Recherche de commande
        $order = $this->entityManager->getRepository(Order::class)
            ->findOneBy(['uidn' => $uidn]);

        if (!$order) {
            return $this->json(
                $this->apiResponse->error(message: "Commande introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Items de la commande
        $items = $order->getOrderItems();

        // Paiements associés
        $payments = $order->getPayment();

        // Formatage finale
        $data = [
            'order' => [
                'id' => $order->getId(),
                'uidn' => $order->getUidn(),
                'user' => $order->getUser()?->getFirstname() . ' ' . $order->getUser()?->getLastname(),
                'pharmacy' => $order->getPharmacy()?->getName(),
                'identity_document' => $order->getIdentityDocument(),
                'prescriptions' => $order->getPrescriptionFiles(),
                'comment' => $order->getComment(),
                'withdrawal_type' => $order->getWithdrawalType()?->value,
                'status' => $order->getStatus()?->value,
                'qr_code' => $order->getQrCode(),
                'created_date' => $order->getCreatedDate()?->format('Y-m-d H:i:s'),
                'updated_date' => $order->getUpdatedDate()?->format('Y-m-d H:i:s'),
                'total_amount' => $order->getTotalAmount(),
                'description' => $order->getDescription(),
                'items_count' => $order->getItemsCount(),
            ],

            'items' => array_map(function ($item) {
                return [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                    'price' => $item->getPrice(),
                    'created_at' => $item->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'user' => $item->getUser()?->getFirstname() . ' ' . $item->getUser()?->getLastname(),
                ];
            }, $items->toArray()),

            'payments' => array_map(function ($payment) {
                return [
                    'id' => $payment->getId(),
                    'payment_method' => $payment->getPayment()?->getLibelle(),
                    'transaction_reference' => $payment->getTransactionReference(),
                    'transaction_status' => $payment->getTransactionStatus(),
                ];
            }, $payments->toArray()),
        ];

        return $this->json(
            $this->apiResponse->success(
                data: $data,
                message: "Order details retrieved successfully"
            ),
            Response::HTTP_OK
        );
    }


}
