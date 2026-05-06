<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use App\Repository\RefreshTokenRepository;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\InheritanceType("SINGLE_TABLE")]
class RefreshToken extends BaseRefreshToken
{
    // Pas besoin de redéfinir les champs
}
