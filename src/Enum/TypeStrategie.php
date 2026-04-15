<?php

namespace App\Enum;

enum TypeStrategie: string
{
    case MARKETING = 'MARKETING';
    case FINANCIERE = 'FINANCIERE';
    case OPERATIONNELLE = 'OPERATIONNELLE';
    case DIGITALE = 'DIGITALE';
    case RH = 'RH';
    case CROISSANCE = 'CROISSANCE';
    case COMMERCIALE = 'COMMERCIALE';
    case JURIDIQUE = 'JURIDIQUE';
    case AUTRE = 'AUTRE';
}