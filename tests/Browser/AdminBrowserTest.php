<?php

namespace App\Tests\Browser;

use App\Entity\Client as Customer;
use App\Entity\Company;
use App\Entity\Project;
use App\Entity\Rate;
use App\Entity\User;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSelect;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminBrowserTest extends PantherTestCase
{
    private Client $browser;
    private string $password;
    private int $customerId;
    private int $activeProjectId;
    private int $inactiveProjectId;
    private int $memberId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        // Refuse any schema reset unless the dedicated browser bootstrap owns this database.
        $expected = $_SERVER['PANTHER_TEST_DATABASE'] ?? '';
        if (!$db->getDatabasePlatform() instanceof SQLitePlatform || $expected === ''
            || ($db->getParams()['path'] ?? '') !== $expected
            || !str_starts_with($expected, dirname(__DIR__, 2) . '/var/browser-tests/')) {
            throw new \RuntimeException('Browser tests require the isolated database from phpunit.panther.xml.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $this->password = bin2hex(random_bytes(16));
        $admin = (new User())->setEmail('panther@example.test')->setUsername('Panther admin')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($admin, $this->password));
        $member = (new User())->setEmail('member@example.test')->setUsername('Existing member')
            ->setRoles(['ROLE_USER'])->setIsActive(false)->setPassword($admin->getPassword());
        $company = (new Company())->setName('Panther company');
        $rate = (new Rate())->setName('Standard')->setValue('1250.75')->setCompany($company);
        $customer = (new Customer())->setName('Existing client')->setContactPerson('Alice')
            ->setContactEmail('alice@example.test')->setContactPhone('+45 12 34 56 78')
            ->setAdress('Test street 12')->setPostalCode('0123')->setCity('Aarhus')->setCountry('Danmark');
        $active = (new Project())->setName('Active website')->setClient($customer)->setRate($rate)->setDeadline(new \DateTime('+1 month'));
        $inactive = (new Project())->setName('Archived website')->setClient($customer)->setArchived(true)->setDeadline(new \DateTime('-1 month'));
        foreach ([$admin, $member, $company, $rate, $customer, $active, $inactive] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        self::assertSame(1, $company->getId());
        $this->customerId = $customer->getId();
        $this->activeProjectId = $active->getId();
        $this->inactiveProjectId = $inactive->getId();
        $this->memberId = $member->getId();
        self::ensureKernelShutdown();

        $this->browser = self::createPantherClient([
            'browser' => self::CHROME,
            'router' => __DIR__ . '/router.php',
        ]);
        $this->browser->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(1600, 1000));
        $this->browser->getWebDriver()->manage()->deleteAllCookies();
    }

    protected function tearDown(): void
    {
        // Only remove an upload belonging to this run's isolated company.
        if (self::$kernel !== null) {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            $logo = $em->find(Company::class, 1)?->getLogo();
            if ($logo && preg_match('#^uploads/company/[a-f0-9]{32}\.(png|jpg|jpeg|webp)$#', $logo)) {
                $file = dirname(__DIR__, 2) . '/public/' . $logo;
                if (is_file($file)) unlink($file);
            }
        }
        parent::tearDown();
    }

    public static function locales(): iterable
    {
        yield 'Danish' => ['da'];
        yield 'English' => ['en'];
    }

    #[DataProvider('locales')]
    public function testInlineUsersAndCreation(string $locale): void
    {
        $this->login($locale);
        $this->click('[data-testid="nav-users"]');
        $this->browser->waitForVisibility('[data-testid="users-panel"]');
        self::assertSelectorExists('[data-testid="nav-users"][aria-current="page"]');
        $row = '[data-user-row="' . $this->memberId . '"]';
        self::assertSame('0', $this->value($row . ' [data-testid="user-field-active"]'));
        self::assertSelectorExists('[data-user-row="1"] [data-testid="user-field-role"][disabled]');
        self::assertSelectorExists('[data-user-row="1"] [data-testid="user-field-active"][disabled]');
        (new WebDriverSelect($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($row . ' [data-testid="user-field-role"]'))))->selectByValue('ROLE_ADMIN');
        (new WebDriverSelect($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($row . ' [data-testid="user-field-active"]'))))->selectByValue('1');
        $this->fill($row . ' [data-testid="user-field-username"]', 'Updated member');
        $this->fill($row . ' [data-testid="user-field-email"]', 'UPDATED@example.test');
        $this->click($row . ' [data-testid="user-save"]');
        $this->saved($row, $locale);
        self::assertSame('updated@example.test', $this->value($row . ' [data-testid="user-field-email"]'));

        $this->fill($row . ' [data-testid="user-field-username"]', 'Must not persist');
        $this->fill($row . ' [data-testid="user-field-email"]', 'PANTHER@example.test');
        $this->click($row . ' [data-testid="user-save"]');
        $this->browser->waitForVisibility($row . ' [data-testid="save-status"][data-state="error"]');

        $this->click('[data-testid="user-create"]');
        $this->click('[data-testid="user-create"]');
        $draft = '[data-user-row="new"]';
        self::assertCount(1, $this->browser->getWebDriver()->findElements(WebDriverBy::cssSelector($draft)));
        foreach (['username' => 'New member', 'email' => 'new@example.test'] as $field => $value) {
            $this->fill($draft . ' [data-testid="user-field-' . $field . '"]', $value);
        }
        $this->click($draft . ' [data-testid="user-save"]');
        $this->browser->waitForInvisibility($draft);
        self::assertSame('New member', $this->value('[data-testid="user-rows"] > tr:first-child input[name="username"]'));
        self::assertSelectorNotExists('[data-testid="user-field-password"]');
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));

        $this->click('[data-testid="user-create"]');
        $this->click($draft . ' [data-testid="user-cancel"]');
        $this->browser->waitForInvisibility($draft);
        $this->click('[data-testid="nav-dashboard"]');
        $this->browser->waitForVisibility('[data-testid="company-name-input"]');
        $this->click('[data-testid="nav-users"]');
        $this->browser->waitForVisibility('[data-testid="users-panel"]');
        self::assertSame('Updated member', $this->value($row . ' [data-testid="user-field-username"]'));
        self::assertSame('updated@example.test', $this->value($row . ' [data-testid="user-field-email"]'));
        self::assertSelectorExists('input[value="new@example.test"]');
        self::assertSame('ROLE_ADMIN', $this->value($row . ' [data-testid="user-field-role"]'));
        self::assertSame('1', $this->value($row . ' [data-testid="user-field-active"]'));

        // The generated password is only sent by email, never returned to the GUI.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $createdUser = $em->getRepository(User::class)->findOneBy(['email' => 'new@example.test']);
        self::assertNotNull($createdUser);
        self::assertSame(['ROLE_USER'], $createdUser->getRoles());
        self::assertTrue($createdUser->isActive());
        self::assertNotEmpty($createdUser->getPassword());
        self::assertStringNotContainsString($createdUser->getPassword(), $this->browser->getWebDriver()->getPageSource());
    }

    private function login(string $locale): void
    {
        $this->browser->request('GET', '/' . $locale . '/login');
        $this->browser->submitForm('Login', ['_username' => 'panther@example.test', '_password' => $this->password]);
        $this->browser->waitForInvisibility('#username');
        $this->browser->request('GET', '/' . $locale . '/admin/');
        $this->browser->waitForVisibility('[data-testid="company-name-input"]');
        $this->browser->executeScript('window.pantherPageMarker = "same-document";');
    }

    private function click(string $selector): void
    {
        $this->browser->waitForVisibility($selector);
        $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($selector))->click();
    }

    private function fill(string $selector, string $value): void
    {
        $element = $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($selector));
        $element->clear();
        $element->sendKeys($value);
    }

    private function value(string $selector): string
    {
        return (string) $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($selector))->getAttribute('value');
    }

    private function saved(string $scope, string $locale): void
    {
        $this->browser->waitForElementToContain($scope . ' [data-testid="save-status"][data-state="success"]', $locale === 'da' ? 'Gemt.' : 'Saved.');
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));
    }

    private function openClients(): void
    {
        $this->click('[data-testid="nav-clients"]');
        $this->browser->waitForVisibility('[data-testid="clients-panel"]');
    }

    #[DataProvider('locales')]
    public function testCompanyNameLogoAndDashboardNavigation(string $locale): void
    {
        $this->login($locale);
        $this->fill('[data-testid="company-name-input"]', 'Updated company');
        $this->click('[data-testid="company-name-save"]');
        $this->saved('[data-testid="company-name-editor"]', $locale);
        self::assertSelectorTextContains('[data-testid="company-brand-name"]', 'Updated company');

        $file = dirname($_SERVER['PANTHER_TEST_DATABASE']) . '/logo.png';
        $image = imagecreatetruecolor(32, 32);
        imagepng($image, $file);
        $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector('[data-testid="company-logo-input"]'))->sendKeys($file);
        $this->click('[data-testid="company-logo-save"]');
        $this->saved('[data-testid="company-logo-editor"]', $locale);
        $preview = $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector('[data-testid="company-logo-preview"]'))->getAttribute('src');
        self::assertStringContainsString('/uploads/company/', $preview);
        self::assertSame($preview, $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector('[data-testid="company-brand-logo"]'))->getAttribute('src'));
        $this->browser->wait()->until(fn () => $this->browser->executeScript('const image = document.querySelector("[data-testid=company-brand-logo]"); return image.complete && image.naturalWidth > 0;'));

        $this->openClients();
        $this->click('[data-testid="nav-dashboard"]');
        $this->browser->waitForVisibility('[data-testid="company-name-input"]');
        self::assertSame('Updated company', $this->value('[data-testid="company-name-input"]'));
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));
        self::assertSelectorExists('[data-testid="nav-dashboard"][aria-current="page"]');
    }

    #[DataProvider('locales')]
    public function testCurrencyAndRateCrud(string $locale): void
    {
        $this->login($locale);
        (new WebDriverSelect($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector('[data-testid="company-currency-region"]'))))->selectByValue('north_america');
        $this->browser->waitFor('[data-testid="company-currency"] option[value="USD"]');
        self::assertSelectorNotExists('[data-testid="company-currency"] option[value="EUR"]');
        self::assertSelectorNotExists('[data-testid="company-currency"] option[value="USN"]');
        (new WebDriverSelect($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector('[data-testid="company-currency"]'))))->selectByValue('USD');
        $this->click('[data-testid="company-currency-save"]');
        $this->saved('[data-testid="company-rates"]', $locale);
        self::assertSame('USD', $this->value('[data-testid="company-currency"]'));
        self::assertSame('1250.75', $this->value('[data-rate-id="1"] [data-testid="rate-value"]'));

        $this->fill('[data-testid="company-rate-new"] [data-testid="rate-name"]', 'Browser rate');
        $this->fill('[data-testid="company-rate-new"] [data-testid="rate-value"]', '350.50');
        $this->click('[data-testid="company-rate-new"] [data-testid="rate-save"]');
        $this->browser->waitFor('input[value="Browser rate"]');
        $this->saved('[data-testid="company-rates"]', $locale);
        $rateId = $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector('input[value="Browser rate"]'))->findElement(WebDriverBy::xpath('ancestor::tr'))->getAttribute('data-rate-id');
        $row = '[data-testid="company-rate-row"][data-rate-id="' . $rateId . '"]';
        $this->fill($row . ' [data-testid="rate-value"]', '425.75');
        $this->click($row . ' [data-testid="rate-save"]');
        $this->browser->waitFor($row . ' input[value="425.75"]');
        $this->click($row . ' [data-testid="rate-delete"]');
        $this->browser->getWebDriver()->switchTo()->alert()->accept();
        $this->browser->waitForInvisibility($row);
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));
    }

    #[DataProvider('locales')]
    public function testInlineClientsAndProjectModal(string $locale): void
    {
        $this->login($locale);
        $this->openClients();
        $row = '[data-client-row="' . $this->customerId . '"]';
        $this->fill($row . ' [data-testid="client-field-contactPerson"]', 'Updated contact');
        $this->click($row . ' [data-testid="client-save"]');
        $this->saved($row, $locale);
        self::assertSame('Updated contact', $this->value($row . ' [data-testid="client-field-contactPerson"]'));
        $this->fill($row . ' [data-testid="client-field-contactPhone"]', 'invalid');
        $this->click($row . ' [data-testid="client-save"]');
        $this->browser->waitForVisibility($row . ' [data-testid="save-status"][data-state="error"]');
        self::assertSame('invalid', $this->value($row . ' [data-testid="client-field-contactPhone"]'));

        $this->click('[data-testid="client-create"]');
        $this->click('[data-testid="client-create"]');
        self::assertCount(1, $this->browser->getWebDriver()->findElements(WebDriverBy::cssSelector('[data-client-row="new"]')));
        $draft = '[data-client-row="new"]';
        foreach (['name' => 'ZZZ Panther client', 'contactPerson' => 'Bob', 'contactEmail' => 'bob@example.test', 'contactPhone' => '+45 87 65 43 21', 'adress' => 'New street 1', 'postalCode' => '0123', 'city' => 'Aarhus', 'country' => 'Danmark'] as $field => $value) {
            $this->fill($draft . ' [data-testid="client-field-' . $field . '"]', $value);
        }
        $this->click($draft . ' [data-testid="client-save"]');
        $this->browser->waitForInvisibility($draft);
        self::assertSame('ZZZ Panther client', $this->value('[data-testid="client-rows"] > tr:first-child input[name="name"]'));
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));

        $this->click($row . ' [data-testid="client-projects-open"]');
        $dialog = $row . ' [data-testid="client-projects-dialog"][open]';
        $this->browser->waitForVisibility($dialog);
        self::assertSelectorTextContains($dialog . ' [data-testid="client-projects-active"]', 'Active website');
        self::assertSelectorTextContains($dialog . ' [data-testid="client-projects-inactive"]', 'Archived website');
        self::assertSelectorNotExists($dialog . ' [data-testid="client-projects-active"] [data-project-id="' . $this->inactiveProjectId . '"]');
        $this->click($dialog . ' [data-testid="client-projects-close"]');
        $this->browser->waitForInvisibility($row . ' [data-testid="client-projects-dialog"]');
        $this->click($row . ' [data-testid="client-projects-open"]');
        $this->browser->getWebDriver()->getKeyboard()->pressKey(WebDriverKeys::ESCAPE);
        $this->browser->waitForInvisibility($row . ' [data-testid="client-projects-dialog"]');

        // Reload from the server to verify persistence and rejection of the invalid phone.
        $this->click('[data-testid="nav-dashboard"]');
        $this->browser->waitForVisibility('[data-testid="company-name-input"]');
        $this->openClients();
        self::assertSame('Updated contact', $this->value($row . ' [data-testid="client-field-contactPerson"]'));
        self::assertSame('+45 12 34 56 78', $this->value($row . ' [data-testid="client-field-contactPhone"]'));
        self::assertSelectorExists('input[value="ZZZ Panther client"]');
        $this->click('[data-testid="client-create"]');
        $this->click($draft . ' [data-testid="client-cancel"]');
        $this->browser->waitForInvisibility($draft);
    }
}
