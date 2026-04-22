<?php

namespace App\Doctrine;

use Gedmo\Mapping\Driver\AttributeReader;
use Gedmo\Translatable\TranslatableListener;

final class AttributeTranslatableListener extends TranslatableListener
{
    public function setAnnotationReader($reader)
    {
        if (!$reader instanceof AttributeReader) {
            $reader = new AttributeReader();
        }

        parent::setAnnotationReader($reader);
    }
}