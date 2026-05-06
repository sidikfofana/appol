<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Order;
use App\Entity\PaymentMethod;
use App\Security\ApiResponseService;
use App\Helpers\Helpers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Enum\OrderStatus;

final class PaymentController extends AbstractController
{
    private $entityManager;
    private $apiResponse;
    private $helpers;

    public function __construct(
        EntityManagerInterface $entityManager,
        Helpers $helpers,
        ApiResponseService $apiResponse,
    ) {
        $this->entityManager = $entityManager;
        $this->helpers      = $helpers;
        $this->apiResponse  = $apiResponse;
    }

    /**
     * CREATE PAYMENT
     */
    #[Route('/api/payment/create', name: 'api_payment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();

            if (!$currentUser) {
                return new JsonResponse(
                    $this->apiResponse->error("Utilisateur non authentifié", Response::HTTP_UNAUTHORIZED),
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Lire JSON
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(
                    $this->apiResponse->error("Format JSON invalide", Response::HTTP_BAD_REQUEST),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Champs obligatoires
            $required = ['order_id', 'payment_method_id'];
            $missing  = $this->helpers->validateRequiredFields($data, $required);
            if (!empty($missing)) {
                return new JsonResponse(
                    $this->apiResponse->error(
                        "Champs obligatoires manquants : " . implode(', ', $missing),
                        Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérifier l'ordre
            if (!empty($data['order_id'])) {
                $order = $this->entityManager->getRepository(Order::class)->find($data['order_id']);
                if (!$order) {
                    return new JsonResponse(
                        $this->apiResponse->error("Commande introuvable", Response::HTTP_NOT_FOUND),
                        Response::HTTP_NOT_FOUND
                    );
                }
                //RÉCUPÉRATION DU TOTAL et de l'UIDN
                $uidn = $order->getUidn();
                $totalAmount = $order->getTotalAmount();
                //dd($totalAmount);
            } else {
                return new JsonResponse(
                    $this->apiResponse->error(
                        message: "order_id est requis.",
                        statusCode: Response::HTTP_BAD_REQUEST
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérifier méthode de paiement
            $paymentMethod = $this->entityManager->getRepository(PaymentMethod::class)->find($data['payment_method_id']);
            if (!$paymentMethod) {
                return new JsonResponse(
                    $this->apiResponse->error("Méthode de paiement introuvable", Response::HTTP_NOT_FOUND),
                    Response::HTTP_NOT_FOUND
                );
            }

            // Création du paiement
            $payment = new Payment();
            $payment->setOrder($order);
            $payment->setPayment($paymentMethod);
            $payment->setTransactionReference($this->generateTransactionReference());
            //$payment->setTransactionStatus($data['transaction_status'] ?? null);

            // METTRE À JOUR LE STATUT DE LA COMMANDE
            $order->setStatus(OrderStatus::PAID);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            // SUCCESS → Génération du lien crypté
            $encryptedLink = $this->helpers->generateOrderQr($uidn);

            return new JsonResponse(
                $this->apiResponse->success(
                    message: "Paiement enregistré avec succès",
                    statusCode: Response::HTTP_CREATED,
                    extra: [
                        "payment_id" => $payment->getId(),
                        "order_id"   => $order->getId(),
                        "payment_method" => $paymentMethod->getLibelle(),
                        "transaction_reference" => $payment->getTransactionReference(),
                        "transaction_status"    => $payment->getTransactionStatus(),
                        "montant_total"    => $totalAmount,
                        'encrypted_link' => $encryptedLink
                    ]
                ),
                Response::HTTP_CREATED
            );

        } catch (\Exception $e) {
            return new JsonResponse(
                $this->apiResponse->error("Erreur interne : ".$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * LIST ALL PAYMENTS
     */
    #[Route('/api/payments/list', name: 'api_payment_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return new JsonResponse(
                $this->apiResponse->error("Utilisateur non authentifié", Response::HTTP_UNAUTHORIZED),
                Response::HTTP_UNAUTHORIZED
            );
        }

        $payments = $this->entityManager->getRepository(Payment::class)->findAll();

        $formatted = array_map(function (Payment $p) {
            return [
                "id" => $p->getId(),
                "order_id" => $p->getOrder()?->getId(),
                "payment_method" => $p->getPayment()?->getLibelle(),
                "transaction_reference" => $p->getTransactionReference(),
                "transaction_status" => $p->getTransactionStatus(),
            ];
        }, $payments);

        return new JsonResponse(
            $this->apiResponse->success(
                data: $formatted,
                message: "Liste des paiements récupérée avec succès"
            ),
            Response::HTTP_OK
        );
    }

    private function generateTransactionReference(): string
    {
        return 'PAY-'
            . date('Ymd') . '-'
            . strtoupper(substr(sha1(uniqid('', true)), 0, 8));
    }
}
