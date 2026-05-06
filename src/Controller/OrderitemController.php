<?php

namespace App\Controller;

use App\Entity\OrderItem;
use App\Entity\Order;
use App\Entity\User;
use App\Security\ApiResponseService;
use App\Services\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Helpers\Helpers;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use OpenApi\Attributes as OA;
use App\Enum\OrderStatus;


final class OrderitemController extends AbstractController
{
    private $entityManager;
    private $serializer;
    private $validator;
    private $Helpers;
    private $apiResponse;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        Helpers $Helpers,
        ApiResponseService $apiResponse,
        SerializerInterface $serializer,
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->Helpers = $Helpers;
        $this->apiResponse = $apiResponse;
    }

    #[Route('/api/orderitem/create', name: 'app_orderitem_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
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
            $requiredFields = ['order_id'];
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


            // Vérification du tableau d'items
            if (empty($data['items']) || !is_array($data['items'])) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: 'Le champ "items" est requis et doit être un tableau.',
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // ============================
            //  Gestion du order_id
            // ============================
            $order = null;
            $isNewOrder = false;

            if (!empty($data['order_id'])) {
                // On récupère une commande existante
                $order = $this->entityManager->getRepository(Order::class)->find($data['order_id']);

                if (!$order) {
                    return new JsonResponse(
                        $this->apiResponse->error(
                            message: "La commande avec l'ID {$data['order_id']} n'existe pas.",
                            statusCode: Response::HTTP_NOT_FOUND
                        ),
                        Response::HTTP_NOT_FOUND
                    );
                }
            } else {
                // On crée une nouvelle commande
                $order = new Order();
                $order->setUser($currentUser);
                $order->setCreatedAt(new \DateTimeImmutable());
                $order->setTotalAmount(0);
                $this->entityManager->persist($order);
                $isNewOrder = true;
            }

            $totalToAdd = 0;

            // ============================
            //  Traitement des items
            // ============================
            foreach ($data['items'] as $index => $itemData) {

                // Vérification des champs requis
                if (empty($itemData['title']) || !isset($itemData['price'])) {
                    return new JsonResponse(
                        $this->apiResponse->error(
                            message: "Item $index : les champs 'title' et 'price' sont requis.",
                            statusCode: Response::HTTP_BAD_REQUEST
                        ),
                        Response::HTTP_BAD_REQUEST
                    );
                }

                // Création d'un item
                $item = new OrderItem();
                $item->setTitle($itemData['title']);
                $item->setPrice((float)$itemData['price']);
                $item->setUser($currentUser);
                $item->setCreatedAt(new \DateTimeImmutable());
                $item->setCommande($order);

                $order->addOrderItem($item);
                $this->entityManager->persist($item);

                $totalToAdd += (float)$itemData['price'];
            }

            // ============================
            //  Mise à jour du total
            // ============================
            if ($isNewOrder) {
                // Si nouvelle commande : total = montant ajouté
                $order->setTotalAmount($totalToAdd);
            } else {
                // Si commande existante : on ajoute au total
                $order->setTotalAmount($order->getTotalAmount() + $totalToAdd);
                $order->setUpdatedDate(new \DateTime());
                $order->setStatus(OrderStatus::BILLED);
            }

            // Sauvegarde finale
            $this->entityManager->flush();

            // Réponse des items
            $itemsResponse = [];
            foreach ($order->getOrderItems() as $singleItem) {
                $itemsResponse[] = [
                    'id' => $singleItem->getId(),
                    'title' => $singleItem->getTitle(),
                    'price' => $singleItem->getPrice(),
                ];
            }

            return new JsonResponse(
                $this->apiResponse->success(
                    message: "Commande enregistrée avec succès.",
                    statusCode: Response::HTTP_CREATED,
                    extra: [
                        'order_id' => $order->getId(),
                        'item_count' => count($order->getOrderItems()),
                        'total_amount' => $order->getTotalAmount(),
                        'items' => $itemsResponse
                    ]
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
                    message: 'Erreur interne : ' . $e->getMessage(),
                    statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
                ),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    //items par order id
    #[Route('/api/order/items/{orderId}', name: 'api_items_by_order', methods: ['GET'])]
    public function listItemsByOrder(int $orderId): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Récupérer la commande par ID
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            return $this->json(
                $this->apiResponse->error(message: "Commande introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer les items liés à cette commande
        $items = $this->entityManager->getRepository(OrderItem::class)
            ->createQueryBuilder('i')
            ->where('i.commande = :order')
            ->setParameter('order', $order)
            ->orderBy('i.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(
            $this->apiResponse->success(
                data: $this->formatOrderItems($items),
                message: "Order items retrieved successfully"
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'order_items']
        );
    }

    //Order items by uidn
    #[Route('/api/order/itemsdetails/{uidn}', name: 'api_order_itemsdetails_by_uidn', methods: ['GET'])]
    public function listItemsByUidn(string $uidn): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(
                $this->apiResponse->error(message: "Utilisateur non authentifié"),
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Récupérer la commande par uidn
        $order = $this->entityManager->getRepository(Order::class)
            ->findOneBy(['uidn' => $uidn]);

        if (!$order) {
            return $this->json(
                $this->apiResponse->error(message: "Commande introuvable"),
                Response::HTTP_NOT_FOUND
            );
        }

        // Récupérer les items liés à cette commande
        $items = $this->entityManager->getRepository(OrderItem::class)
            ->createQueryBuilder('i')
            ->where('i.commande = :order')
            ->setParameter('order', $order)
            ->orderBy('i.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(
            $this->apiResponse->success(
                data: $this->formatOrderItems($items),
                message: "Order items retrieved successfully"
            ),
            Response::HTTP_OK,
            [],
            ['groups' => 'order_items']
        );
    }



    /**
     * Formater les items d'une commande
     */
    private function formatOrderItems(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[] = [
                'id'        => $item->getId(),
                'title'     => $item->getTitle(),
                'price'     => $item->getPrice(),
                'user_id'   => $item->getUser()?->getId(),
                'created_at'=> $item->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }

        return $result;
    }




}
