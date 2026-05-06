<?php

namespace App\Helpers;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
#use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
// use App\Message\SendEmailMessage;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Helpers extends AbstractController
{
    private $mailer;
    private $params;

    public function __construct(private FileUploader $fileUploader, MailerInterface $mailer, private MessageBusInterface $bus, ParameterBagInterface $params)
    {
        $this->mailer = $mailer;
        $this->params = $params;
    }

    public function formatDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function validateUserData(array $data): array
    {
        $errors = [];

        // Add your validation logic here
        if (empty($data['nom'])) {
            $errors[] = 'Nom is required';
        }
        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        }

        return $errors;
    }

    function validateRequiredFields(array $data, array $requiredFields): array
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    /**
     * Validate required fields in an entity.
     *
     * @param object $entity
     * @param array $requiredFields
     * @return array
     */
    public function validateRequiredFieldsentity(object $entity, array $requiredFields): array
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            $getter = 'get' . ucfirst($field);

            if (method_exists($entity, $getter)) {
                if (empty($entity->$getter())) {
                    $missingFields[] = $field;
                }
            } else {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    function returnResponse(string $message, int $status = Response::HTTP_OK, $data = [], $errors = []): array
    {
        return [
            "message" => $message == "" ? Response::$statusTexts['200'] : $message,
            "status_code" => $status,
            "data" => $data,
            "errors" => $errors
        ];
    }

    function returnErrorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, $data = [], $errors = []): array
    {
        return [
            "message" => $message == "" ? Response::$statusTexts['400'] : $message,
            "status_code" => $status,
            "data" => $data,
            "errors" => $errors
        ];
    }

    function array_except($data, $key)
    {
        unset($data["$key"]);

        return $data;
    }


    /**
     * Summary of checkNotEmpty
     * @param mixed $elements
     * @param mixed $required_values
     * @return array
     */
    function checkNotEmpty($elements = [], $required_values = [])
    {
        $all = [];

        if (count($required_values) >= 1) {

            foreach ($required_values as $value) {

                if (isset($elements[$value])) {
                    if ($elements[$value] === "" || is_null($elements[$value])) {
                        $error = ['message' => sprintf('Champ requis non renseigné : %s ', $value)];
                        array_push($all, $error);
                    }
                } else {
                    $error = ['message' => sprintf('Champ réquis manquant dans le payload  : %s ', $value)];
                    array_push($all, $error);
                }
            }
        } else {

            foreach ($elements as $key => $element) {

                if ($element === "" || is_null($element)) {
                    $error = ['message' => sprintf('Champ non renseigné : %s ', $key)];
                    array_push($all, $error);
                }
            }
        }

        if (count($all) > 0) {
            return returnResponse("Paramètre requis manquant", Response::HTTP_UNPROCESSABLE_ENTITY, errors: array_merge($all));
        }
    }

    function isDigits(string $s, int $minDigits = 9, int $maxDigits = 14): bool
    {
        return preg_match('/^[0-9]{' . $minDigits . ',' . $maxDigits . '}\z/', $s);
    }

    function isValidTelephoneNumber(string $telephone, int $minDigits = 9, int $maxDigits = 14): bool
    {
        if (preg_match('/^[+][0-9]/', $telephone)) { //is the first character + followed by a digit
            $count = 1;
            $telephone = str_replace(['+'], '', $telephone, $count); //remove +
        }

        //remove white space, dots, hyphens and brackets
        $telephone = str_replace([' ', '.', '-', '(', ')'], '', $telephone);

        //are we left with digits only?
        return isDigits($telephone, $minDigits, $maxDigits);
    }

    /**
     * Déchiffre les données chiffrées
     *
     * @param string $encryptedDataWithIv Les données chiffrées avec l'IV
     * @param string $encryptionKey La clé secrète utilisée pour le chiffrement
     * @return string|null Les données déchiffrées ou null en cas d'erreur
     */
    public function decryptData($encryptedDataWithIv, $encryptionKey = 'secure')
    {
        try {
            // Décoder les données chiffrées de base64
            $encryptedDataWithIv = base64_decode($encryptedDataWithIv);

            // Séparer les données chiffrées et l'IV
            list($encryptedData, $iv) = explode('::', $encryptedDataWithIv, 2);

            // Méthode de chiffrement utilisée (doit être la même que pour le chiffrement)
            $cipherMethod = 'AES-256-CBC';

            // Déchiffrement
            $decryptedData = openssl_decrypt($encryptedData, $cipherMethod, $encryptionKey, 0, $iv);

            if ($decryptedData === false) {
                throw new \Exception('Decryption failed.');
            }

            return $decryptedData;
        } catch (\Exception $e) {
            // Gestion des erreurs
            throw new \Exception('Decryption error: ' . $e->getMessage());
        }
    }

    public function generateOrderQr($uidn)
    {
        try {
            $qrCode = Builder::create()
                ->writer(new PngWriter())
                ->data($uidn)
                ->encoding(encoding: new Encoding('UTF-8'))
                ->size(300)
                ->build();

            $filePath = 'qrcode/qrcode-' . $uidn . '.png';
            $qrCode->saveToFile($filePath);

            return $filePath;

        } catch (\Exception $e) {
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }

    public function generateEncrypt($visitorId, $uidn, $host_name)
    {
        try {
            // 1. Création de la chaîne de données pour le QR code
            $data = [
                'visitor_id' => $visitorId,
                'uidn' => $uidn,
                'host' => $host_name,
            ];

            $url = $this->getParameter('domain_front') . "/scan/" . $uidn;
            // $jsonData = json_encode($data);

            // 2. Chiffrement des données
            // $encryptionKey = 'secure'; // Remplacez par une clé secrète
            // $cipherMethod = 'AES-256-CBC';
            // $ivLength = openssl_cipher_iv_length($cipherMethod);
            // $iv = openssl_random_pseudo_bytes($ivLength);

            // $encryptedData = openssl_encrypt($jsonData, $cipherMethod, $encryptionKey, 0, $iv);
            // if ($encryptedData === false) {
            //     throw new \Exception('Encryption failed.');
            // }

            // $encryptedDataWithIv = base64_encode($encryptedData . '::' . $iv); // Ajout de l'IV à la fin

            // 3. Génération du QR code avec les données chiffrées
            $qrCode = Builder::create()
                ->writer(new PngWriter())
                ->data($url)
                // ->data($jsonData)
                // ->data($encryptedDataWithIv)
                ->encoding(encoding: new Encoding('UTF-8'))
                ->size(300)
                // ->validateResult(1)
                ->build();

            // 4. Sauvegarde ou retour de l'image générée
            // Vous pouvez enregistrer l'image localement ou la renvoyer au client
            $filePath = 'qrcode/qrcode-' . $uidn . '.png';

            // $this->fileUploader->upload();

            $qrCode->saveToFile($filePath);
            return $filePath;
        } catch (\Exception $e) {
            // Gestion des erreurs
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }

    public function generateEncryptEvent($visitorId, $uidn)
    {
        try {
            // 1. Création de la chaîne de données pour le QR code
            $data = [
                'visitor_id' => $visitorId,
                'uidn' => $uidn,
            ];

            //$url = $this->getParameter('domain_name_no_auth') . "/api/get-qr-data/" . $uidn;
            $url = $this->getParameter('domain_front') . "/scan/" . $uidn;

            $qrCode = Builder::create()
                ->writer(new PngWriter())
                ->data($url)
                ->encoding(encoding: new Encoding('UTF-8'))
                ->size(300)
                ->build();
            $filePath = 'qrcode/qrcode-' . $uidn . '.png';

            $qrCode->saveToFile($filePath);
            return $filePath;
        } catch (\Exception $e) {
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }


    public function generateEncryptLink($lien, $slug)
    {
        try {

            $qrCode = Builder::create()
                ->writer(new PngWriter())
                ->data($lien)
                ->encoding(encoding: new Encoding('UTF-8'))
                ->size(300)
                ->build();

            $filePath = 'qrcode-link/qrcode-' . $slug . '.png';
            $qrCode->saveToFile($filePath);

            return $filePath;

        } catch (\Exception $e) {

            throw new \Exception('QR code generation failed: ' . $e->getMessage());

        }
    }

    public function generateEncryptQR($type, $data, $uidn)
    {
        try {
            $datas = [
                'type' => $type,
                'uidn' => $uidn,
                'data' => $data,
            ];

            $url = $this->getParameter('domain_front') . "/scan-user-qrcode/" . $uidn;

            $qrCode = Builder::create()
                ->writer(new PngWriter())
                ->data($url)
                ->encoding(encoding: new Encoding('UTF-8'))
                ->size(300)
                ->build();
            if($type == 'permanent'){
                $filePath = 'qrcode-user/qrcode-' . $uidn . '.png';
            }else{
                $filePath = 'qrcode-user/qrcode-' . $uidn . '.png';
            }

            $qrCode->saveToFile($filePath);

            return $filePath;

        } catch (\Exception $e) {

            throw new \Exception('QR code generation failed: ' . $e->getMessage());

        }
    }

    public function generateMembersQR($type, $data, $uidn)
    {
        try {
            // Construction d'un texte brut sans l'email
            //dd($data);
            $qrText = "Type|{$type}|UIDN|{$uidn}|Prenoms|{$data['firstname']}|Nom|{$data['lastname']}|Contact|{$data['contact']}|Email|{$data['email']}|Title|{$data['title']}";

            // Création du QR Code
            $qrCode = Builder::create()
                ->writer(new PngWriter())
                ->data($qrText)
                ->encoding(new Encoding('UTF-8'))
                ->size(300)
                ->build();

            // Chemin d'enregistrement du fichier PNG
            $filePath = 'qrcode-user/qrcode-' . $uidn . '.png';
            $qrCode->saveToFile($filePath);

            return $filePath;

        } catch (\Exception $e) {
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }

    public function generateCompanyQR($slug, $data)
    {
        try {
            $baseUrl = rtrim($this->params->get('BASE_URL'), '/');
            $qrCode = Builder::create()
                ->writer(new PngWriter())
                //->data("https://www.securecheck.info/$slug")
                ->data("{$baseUrl}/{$slug}")
                ->encoding(encoding: new Encoding('UTF-8'))
                ->size(300)
                ->build();


                $filePath = 'qrcode-company/qrcode-' . $slug . '.png';

            $qrCode->saveToFile($filePath);

            return $filePath;

        } catch (\Exception $e) {

            throw new \Exception('QR code generation failed: ' . $e->getMessage());

        }
    }

    // public function generateCompanyQR($slug, $data)
    // {
    //     try {
    //         $baseUrl = rtrim($this->params->get('BASE_URL'), '/');
    //         $qrCode = Builder::create()
    //             ->writer(new PngWriter())
    //             ->data("{$baseUrl}/{$slug}")
    //             ->encoding(new Encoding('UTF-8'))
    //             ->size(300)
    //             ->build();

    //         // Chemin absolu vers public/qrcode-company
    //         $directory = $this->params->get('kernel.project_dir') . '/public/qrcode-company/';
    //         if (!is_dir($directory)) {
    //             mkdir($directory, 0755, true); // Crée le dossier s’il n’existe pas
    //         }

    //         $filePath = $directory . 'qrcode-' . $slug . '.png';
    //         $qrCode->saveToFile($filePath);

    //         // Chemin relatif à retourner (pour accès web)
    //         return 'qrcode-company/qrcode-' . $slug . '.png';

    //     } catch (\Exception $e) {
    //         throw new \Exception('QR code generation failed: ' . $e->getMessage());
    //     }
    // }

    public function sendEmail(
        $to,
        $subject,
        $body,
        $data

    ) {
        // Valider l'adresse e-mail
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        // Envoyer l'email avec l'adresse correcte
        $uidn = $data['uidn'];
        $qrCodeUrl = $this->getParameter('domain_name') . "/qrcode/qrcode-$uidn.png";
        $email = (new Email())
            ->from('noreply@express54.org')
            ->to($to)
            ->subject($subject)
            ->html($body)
            ->attachFromPath($qrCodeUrl, 'qrcode.png', 'image/png');

        //->attachFromPath("http://192.168.1.3:9999/qrcode/qrcode-$uidn.png", 'qrc-code', 'image/png');

        $message = new SendEmailMessage($email);
        $this->bus->dispatch($message);

        try {
            $this->mailer->send($email);
            return new JsonResponse(['status' => 'success', 'message' => 'Email sent successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
