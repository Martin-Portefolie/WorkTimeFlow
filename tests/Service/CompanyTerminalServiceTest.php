<?php
namespace App\Tests\Service;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Repository\RateRepository;
use App\Service\Terminal\Admin\CompanyTerminalService;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CompanyTerminalServiceTest extends TestCase
{
    public static function names(): iterable
    {
        yield [['--name=hest', 'test'], 'hest test'];
        yield [['--name="hest', 'test"'], 'hest test'];
        yield [['--name=hest'], 'hest'];
        yield [['--name=<script>'], '<script>'];
    }

    #[DataProvider('names')]
    public function testFlagSavesEntireName(array $tokens, string $expected): void
    {
        $company = new Company();
        $companies = $this->createStub(CompanyRepository::class);
        $companies->method('findBy')->willReturn([$company]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $service = new CompanyTerminalService($companies, $this->createStub(RateRepository::class), $em,
            new TerminalStateService(new ArrayAdapter()), $this->createStub(Security::class));
        $response = $service->companySetNameCommand($tokens);
        self::assertTrue($response['success']);
        self::assertSame($expected, $company->getName());
        self::assertSame($expected, $response['companyName']);
        self::assertStringNotContainsString('<script>', $response['output']);
    }

    public function testRateFlagsPersistRate(): void
    {
        $company = new Company();
        $companies = $this->createStub(CompanyRepository::class);
        $companies->method('findBy')->willReturn([$company]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn ($rate) => $rate instanceof \App\Entity\Rate
                && $rate->getName() === 'gg' && $rate->getValue() === '11.00'
                && $rate->getCompany() === $company
        ));
        $em->expects(self::once())->method('flush');
        $service = new CompanyTerminalService($companies, $this->createStub(RateRepository::class), $em,
            new TerminalStateService(new ArrayAdapter()), $this->createStub(Security::class));
        $response = $service->ratesAddCommand(['--name=gg', '--value=11']);
        self::assertTrue($response['success']);
        self::assertSame('11.00', $response['vars']['value']);
        self::assertTrue($response['ratesChanged']);
    }

    public function testInvalidRateFlagsDoNotWrite(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $service = new CompanyTerminalService($this->createStub(CompanyRepository::class), $this->createStub(RateRepository::class), $em,
            new TerminalStateService(new ArrayAdapter()), $this->createStub(Security::class));
        foreach ([['--name=gg'], ['--name=gg', '--value=-1'], ['--name=gg', '--value=0.001']] as $tokens) {
            self::assertFalse($service->ratesAddCommand($tokens)['success']);
        }
    }

    public function testEmptyNameDoesNotWrite(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $service = new CompanyTerminalService($this->createStub(CompanyRepository::class), $this->createStub(RateRepository::class), $em,
            new TerminalStateService(new ArrayAdapter()), $this->createStub(Security::class));
        $response = $service->companySetNameCommand(['--name=']);
        self::assertFalse($response['success']);
        self::assertArrayNotHasKey('companyName', $response);
    }
}
