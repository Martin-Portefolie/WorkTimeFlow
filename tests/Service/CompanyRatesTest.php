<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Rate;
use App\Repository\CompanyRepository;
use App\Repository\RateRepository;
use App\Service\Admin\CompanyAdminService;
use App\Service\CurrencyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyRatesTest extends TestCase
{
    private function service(EntityManagerInterface $em, Company $company, ?Rate $rate = null, bool $inUse = false): CompanyAdminService
    {
        $companies = $this->createStub(CompanyRepository::class);
        $companies->method('find')->willReturn($company);
        $rates = $this->createStub(RateRepository::class);
        $rates->method('find')->willReturn($rate);
        $rates->method('isUsedByProject')->willReturn($inUse);
        return new CompanyAdminService($companies, $rates, $em, $this->createStub(ValidatorInterface::class), sys_get_temp_dir(), new CurrencyCatalog(dirname(__DIR__, 2)));
    }

    public function testCurrencyChangeDoesNotConvertRates(): void
    {
        $company = new Company();
        self::assertSame('DKK', $company->getCurrency());
        $rate = (new Rate())->setName('Normal')->setValue('1250.75');
        $company->addRate($rate);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(5))->method('flush');
        $service = $this->service($em, $company);
        foreach (['EUR', 'USD', 'JPY', 'CAD', 'SEK'] as $currency) {
            $service->updateCurrency($currency);
            self::assertSame($currency, $company->getCurrency());
            self::assertSame('1250.75', $rate->getValue());
        }
    }

    public function testInvalidCurrencyIsNotSaved(): void
    {
        $company = new Company();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        try {
            $this->service($em, $company)->updateCurrency('INVALID');
            self::fail('Invalid currency accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame('DKK', $company->getCurrency());
        }
    }

    public function testCurrencyFromAnotherRegionIsNotSaved(): void
    {
        $company = new Company();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        try {
            $this->service($em, $company)->updateCurrency('JPY', 'europe');
            self::fail('Currency from another region accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame('DKK', $company->getCurrency());
        }
    }

    public function testFundCodeCannotBeSavedAsCompanyCurrency(): void
    {
        $company = new Company();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        try {
            $this->service($em, $company)->updateCurrency('USN', 'north_america');
            self::fail('Fund code accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame('DKK', $company->getCurrency());
        }
    }

    public function testCreatePreservesDecimalValueAndAssignsCompany(): void
    {
        $company = new Company();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Rate::class));
        $em->expects(self::once())->method('flush');
        $rate = $this->service($em, $company)->createRate(' Normal ', '99999999,99');
        self::assertSame('Normal', $rate->getName());
        self::assertSame('99999999.99', $rate->getValue());
        self::assertSame($company, $rate->getCompany());
    }

    public function testUpdateAllowsZero(): void
    {
        $company = new Company();
        $rate = (new Rate())->setCompany($company)->setName('Old')->setValue('50.00');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $this->service($em, $company, $rate)->updateRate(1, 'Free', '0');
        self::assertSame('Free', $rate->getName());
        self::assertSame('0.00', $rate->getValue());
    }

    public static function invalidRates(): iterable
    {
        yield ['', '1'];
        yield [str_repeat('x', 256), '1'];
        yield ['Rate', '-1'];
        yield ['Rate', '1e2'];
        yield ['Rate', '1.234'];
        yield ['Rate', '100000000'];
        yield ['Rate', ''];
    }

    #[DataProvider('invalidRates')]
    public function testInvalidUpdateDoesNotMutateOrFlush(string $name, string $value): void
    {
        $company = new Company();
        $rate = (new Rate())->setCompany($company)->setName('Original')->setValue('10.00');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        try {
            $this->service($em, $company, $rate)->updateRate(1, $name, $value);
            self::fail('Invalid rate accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame('Original', $rate->getName());
            self::assertSame('10.00', $rate->getValue());
        }
    }

    public function testCannotDeleteUsedRate(): void
    {
        $company = new Company();
        $rate = (new Rate())->setCompany($company);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('flush');
        $this->expectExceptionMessage('company.rates.in_use');
        $this->service($em, $company, $rate, true)->deleteRate(1);
    }

    public function testDeleteUnusedRate(): void
    {
        $company = new Company();
        $rate = (new Rate())->setCompany($company);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($rate);
        $em->expects(self::once())->method('flush');
        $this->service($em, $company, $rate)->deleteRate(1);
    }

    public function testCannotDeleteAnotherCompanyRate(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $this->expectExceptionMessage('company.rates.not_found');
        $this->service($em, new Company(), (new Rate())->setCompany(new Company()))->deleteRate(1);
    }
}
