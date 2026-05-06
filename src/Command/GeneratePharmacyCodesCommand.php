<?php

namespace App\Command;

use App\Entity\Pharmacy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:generate-pharmacy-codes',
    description: 'Génère un code unique à 3 lettres pour toutes les pharmacies',
)]
class GeneratePharmacyCodesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pharmacies = $this->em->getRepository(Pharmacy::class)->findAll();
        $usedCodes = [];

        foreach ($pharmacies as $pharmacy) {
            $name = $pharmacy->getName();
            if (!$name) {
                continue;
            }

            // Générer le code à partir des initiales
            $words = preg_split('/\s+/', $name);
            $code = '';
            foreach ($words as $word) {
                $code .= strtoupper(substr($word, 0, 1));
            }

            // Compléter si moins de 3 lettres
            if (strlen($code) < 3) {
                $remaining = 3 - strlen($code);
                $letters = strtoupper(str_replace(' ', '', $name));
                $code .= substr($letters, 1, $remaining); // on ajoute après la première lettre
            }

            $code = substr($code, 0, 3); // s’assurer que c’est exactement 3 lettres

            // Assurer unicité
            $originalCode = $code;
            $suffix = 1;
            while (in_array($code, $usedCodes)) {
                $code = substr($originalCode, 0, 2) . $suffix; // ex: PCE -> PC1
                $suffix++;
            }

            $usedCodes[] = $code;
            $pharmacy->setCode($code);

            $output->writeln("Pharmacy '{$name}' => Code '{$code}'");
        }

        $this->em->flush();
        $output->writeln("✅ Tous les codes ont été générés avec succès !");

        return Command::SUCCESS;
    }
}
