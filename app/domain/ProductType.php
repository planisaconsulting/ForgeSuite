<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * SIMPLE is a single material or labour rate.
 * ASSEMBLY is reserved for a future recipe, such as a printed Chromadek sign
 * made of board, vinyl, laminate, print, and labour. Do not build that yet.
 */
enum ProductType: string
{
    case Simple = 'SIMPLE';
    case Assembly = 'ASSEMBLY';
}
