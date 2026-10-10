<?php

namespace App\Tests\Service;

use App\Service\CurrencyCatalog;
use App\Twig\CurrencyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class CurrencySelectorTest extends TestCase
{
    public static function locales(): iterable
    {
        yield ['da', 'Europa', 'Danske kroner (DKK)'];
        yield ['en', 'Europe', 'Danish Krone (DKK)'];
    }

    #[DataProvider('locales')]
    public function testRendersLocalizedRegionAndCurrencyChoices(string $locale, string $regionLabel, string $currencyLabel): void
    {
        $root = dirname(__DIR__, 2);
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['messages', 'currencies'] as $domain) {
            $translator->addResource('yaml', "$root/translations/$domain.$locale.yaml", $locale, $domain);
        }
        $twig = new Environment(new FilesystemLoader($root . '/templates'));
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new CurrencyExtension(new CurrencyCatalog($root), new RequestStack()));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (): string => 'test-token'));
        $html = $twig->render('gui/admin/_company_rates.html.twig', ['currency' => 'DKK', 'rates' => []]);

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($document);
        self::assertSame(9, $xpath->query('//select[@name="region"]/option')->length);
        self::assertSame($regionLabel, $xpath->evaluate('string(//select[@name="region"]/option[@selected])'));
        self::assertSame($currencyLabel, $xpath->evaluate('string(//select[@name="currency"]/option[@selected])'));
        self::assertSame(1, $xpath->query('//select[@name="currency"]/option[@value="EUR"]')->length);
        self::assertSame(0, $xpath->query('//select[@name="currency"]/option[@value="JPY"]')->length);
        self::assertSame(1, $xpath->query('//template[@data-region="asia"]/option[@value="JPY"]')->length);
        self::assertSame(1, $xpath->query('//template[@data-region="asia"]/option[@value="CNY"]')->length);
        self::assertSame(1, $xpath->query('//template[@data-region="north_america"]/option[@value="USD"]')->length);
        self::assertSame(1, $xpath->query('//template[@data-region="north_america"]/option[@value="CAD"]')->length);
        self::assertSame(0, $xpath->query('//template[@data-region="north_america"]/option[@value="EUR" or @value="DKK"]')->length);
        self::assertSame(0, $xpath->query('//template[@data-region="north_america"]/option[@value="USN" or @value="MXV"]')->length);

        // A value saved before filtering was introduced must not break the dashboard.
        $html = $twig->render('gui/admin/_company_rates.html.twig', ['currency' => 'USN', 'rates' => []]);
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($document);
        self::assertSame(1, $xpath->query('//select[@name="currency"]/option[@value="USN" and @selected and @disabled]')->length);
        self::assertSame(1, $xpath->query('//select[@name="currency"]/option[@value="USD" and not(@disabled)]')->length);
    }

    public function testPreviouslySelectedRegionFallsBackToCurrencyHomeRegion(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('company_currency_region', 'south_america');
        $requests = new RequestStack();
        $requests->push($request);
        $extension = new CurrencyExtension(new CurrencyCatalog(dirname(__DIR__, 2)), $requests);

        self::assertSame('north_america', $extension->getRegion('USD'));
        self::assertSame('europe', $extension->getRegion('DKK'));
    }

    public function testCurrenciesAppearOnlyInTheirHomeRegion(): void
    {
        $catalogue = new CurrencyCatalog(dirname(__DIR__, 2));
        $regionsByCode = [];
        foreach ($catalogue->getRegions() as $region => $codes) {
            foreach ($codes as $code) {
                $regionsByCode[$code][] = $region;
            }
        }
        self::assertSame(['europe'], $regionsByCode['EUR']);
        self::assertSame(['europe'], $regionsByCode['DKK']);
        self::assertSame(['north_america'], $regionsByCode['USD']);
        self::assertSame(['oceania'], $regionsByCode['AUD']);
        foreach ($regionsByCode as $regions) {
            self::assertCount(1, $regions);
        }
        self::assertFalse($catalogue->contains('EUR', 'north_america'));
    }

    public function testRejectsUnknownCodesAndRegions(): void
    {
        $catalogue = new CurrencyCatalog(dirname(__DIR__, 2));
        self::assertFalse($catalogue->contains('ZZZ'));
        self::assertFalse($catalogue->contains('DKK', 'unknown'));
        self::assertFalse($catalogue->contains('JPY', 'europe'));
        self::assertTrue($catalogue->contains('JPY', 'asia'));
    }

    public function testFundCodesAreNotSelectableButPreviouslySavedCodesCanStillBeDisplayed(): void
    {
        $catalogue = new CurrencyCatalog(dirname(__DIR__, 2));
        foreach (['BOV', 'CHE', 'CHW', 'CLF', 'COU', 'MXV', 'USN', 'UYI', 'UYW'] as $code) {
            self::assertFalse($catalogue->contains($code), $code);
        }
        self::assertSame('north_america', $catalogue->getRegion('USN'));
        self::assertSame(['BMD', 'CAD', 'MXN', 'USD'], $catalogue->getRegions()['north_america']);
    }
}
