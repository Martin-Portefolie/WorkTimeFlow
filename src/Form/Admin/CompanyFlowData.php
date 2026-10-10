<?php

namespace App\Form\Admin;

use App\Entity\Rate;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CompanyFlowData
{
    public string $currentStep = 'details';
    public ?string $name = null;
    public ?UploadedFile $logoFile = null;

    /** @var list<Rate> */
    public array $rates = [];
}
