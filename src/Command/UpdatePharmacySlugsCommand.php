<?php

// php bin/console app:update-pharmacy-slugs : commande à exécuter

namespace App\Command;

use App\Entity\Pharmacy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:update-pharmacy-slugs',
    description: 'Génère un slug pour toutes les pharmacies et met à jour la table.',
)]
class UpdatePharmacySlugsCommand extends Command
{
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->em = $em;
        $this->slugger = $slugger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pharmacies = $this->em->getRepository(Pharmacy::class)->findAll();

        foreach ($pharmacies as $pharmacy) {
            $name = $pharmacy->getName();
            if ($name) {
                // Générer le slug
                $slug = strtolower($this->slugger->slug($name));
                $pharmacy->setSlug($slug);
                $output->writeln("Pharmacy '{$name}' => slug '{$slug}'");
            }
        }

        $this->em->flush();
        $output->writeln("Tous les slugs ont été mis à jour !");

        return Command::SUCCESS;
    }
}
