<?php

namespace App\Twig;

use App\Service\CurrencyCatalog;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CurrencyExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrencyCatalog $catalogue,
        private readonly RequestStack $requests,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('currency_regions', [$this->catalogue, 'getRegions']),
            new TwigFunction('currency_region', [$this, 'getRegion']),
        ];
    }

    public function getRegion(string $currency): string
    {
        $request = $this->requests->getCurrentRequest();
        $preferred = $request?->hasSession()
            ? $request->getSession()->get('company_currency_region')
            : null;

        return $this->catalogue->getRegion($currency, $preferred);
    }
}
