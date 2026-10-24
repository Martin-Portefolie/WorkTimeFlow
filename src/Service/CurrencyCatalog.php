<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

final class CurrencyCatalog
{
    // Selector grouping follows the currency's home region, not every place using it.
    // Country associations in the translation catalogues remain unchanged.
    private const PRIMARY_REGIONS = [
        'DKK' => 'europe',
        'EUR' => 'europe',
        'USD' => 'north_america',
        'AUD' => 'oceania',
    ];

    private ?array $regions = null;
    private array $homeRegions = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** @return array<string, list<string>> */
    public function getRegions(): array
    {
        if ($this->regions !== null) {
            return $this->regions;
        }

        $catalogue = Yaml::parseFile($this->projectDir . '/translations/currencies.en.yaml');
        $this->regions = [];
        foreach ($catalogue['regions'] as $region => $data) {
            $codes = [];
            foreach ($data['countries'] as $country) {
                foreach (array_keys($country['currencies'] ?? []) as $code) {
                    if (isset(self::PRIMARY_REGIONS[$code]) && self::PRIMARY_REGIONS[$code] !== $region) {
                        continue;
                    }
                    $this->homeRegions[$code] = $region;
                    if (($catalogue['currencies'][$code]['kind'] ?? null) === 'fund') {
                        continue;
                    }
                    $codes[$code] = true;
                }
            }
            $this->regions[$region] = array_keys($codes);
            sort($this->regions[$region]);
        }

        return $this->regions;
    }

    public function contains(string $currency, ?string $region = null): bool
    {
        $regions = $this->getRegions();
        if ($region !== null) {
            return in_array($currency, $regions[$region] ?? [], true);
        }
        foreach ($regions as $codes) {
            if (in_array($currency, $codes, true)) {
                return true;
            }
        }

        return false;
    }

    public function getRegion(string $currency, ?string $preferred = null): string
    {
        if ($preferred !== null && $this->contains($currency, $preferred)) {
            return $preferred;
        }
        foreach ($this->getRegions() as $region => $codes) {
            if (in_array($currency, $codes, true)) {
                return $region;
            }
        }

        // Keep previously saved fund codes renderable so the user can replace them.
        return $this->homeRegions[$currency] ?? throw new \InvalidArgumentException('company.currency_invalid');
    }
}
