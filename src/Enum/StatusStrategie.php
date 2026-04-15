<?php

namespace App\Enum;

enum StatusStrategie: string
{
    case En_cours = 'En_cours';
    case Acceptée = 'Acceptée';
    case Refusée = 'Refusée';
    case En_attente = 'En_attente';
    case Non_affectée = 'Non_affectée';
}