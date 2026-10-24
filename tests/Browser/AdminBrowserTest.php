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
    private int $teamId;

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
        $hourlyRate = (new Rate())->setName('Hourly 30')->setValue('30.00')->setCompany($company);
        $customer = (new Customer())->setName('Existing client')->setContactPerson('Alice')
            ->setContactEmail('alice@example.test')->setContactPhone('+45 12 34 56 78')
            ->setAdress('Test street 12')->setPostalCode('0123')->setCity('Aarhus')->setCountry('Danmark');
        $team = (new \App\Entity\Team())->setName('Project team');
        $active = (new Project())->setEstimatedTime(90)->setName('Active website')->setClient($customer)->setRate($rate)->setDeadline(new \DateTime('-1 day'));
        $inactive = (new Project())->setName('Archived website')->setClient($customer)->setArchived(true)->setDeadline(new \DateTime('-1 month'));
        $todo = (new \App\Entity\Todo())->setName('Browser task')->setDateStart(new \DateTime('2026-10-01'))->setDateEnd(new \DateTime('2026-10-02'));
        $log = (new \App\Entity\Timelog())->setUser($member)->setDate(new \DateTime('2026-10-01'))->setTotalMinutes(60)->setDescription('Browser work');
        $active->addTodo($todo);
        $todo->addTimelog($log);
        foreach ([$admin, $member, $company, $rate, $hourlyRate, $customer, $team, $active, $inactive, $todo, $log] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        self::assertSame(1, $company->getId());
        $this->customerId = $customer->getId();
        $this->activeProjectId = $active->getId();
        $this->inactiveProjectId = $inactive->getId();
        $this->memberId = $member->getId();
        $this->teamId = $team->getId();
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

    #[DataProvider('locales')]
    public function testInlineProjectsAndRelations(string $locale): void
    {
        $this->login($locale);
        $this->click('[data-testid="nav-projects"]');
        $this->browser->waitForVisibility('[data-testid="projects-panel"]');
        $row = '[data-project-row="' . $this->activeProjectId . '"]';
        $this->browser->waitForVisibility($row . ' [data-testid="project-field-deadline"]');
        self::assertSelectorTextContains($row . ' [data-testid="project-overdue"]', $locale === 'da' ? 'overskredet' : 'passed');
        self::assertSelectorNotExists('[data-project-row="' . $this->inactiveProjectId . '"] [data-testid="project-overdue"]');
        $this->fill($row . ' [data-testid="project-field-name"]', 'Updated website');
        $this->projectDialog($row, 'details');
        $this->select($row . ' [data-testid="project-field-priority"]', 'high');
        $this->select($row . ' [data-testid="project-field-archived"]', '1');
        $this->closeProjectDialog($row);
        $this->projectDialog($row, 'budget');
        self::assertSame('90', $this->value($row . ' [data-testid="project-field-estimatedMinutes"]'));
        self::assertSelectorTextContains($row . ' [data-testid="project-estimated-time"]', '1h 30m');
        $slider = $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($row . ' [data-testid="project-field-estimatedHours"]'));
        $slider->sendKeys([WebDriverKeys::HOME, WebDriverKeys::ARROW_RIGHT]);
        self::assertSame('300', $this->value($row . ' [data-testid="project-field-estimatedMinutes"]'));
        self::assertSelectorTextContains($row . ' [data-testid="project-estimated-time"]', '5h');
        self::assertSame('6253.75', $this->value($row . ' [data-testid="project-field-estimatedBudget"]'));
        $this->fill($row . ' [data-testid="project-field-estimatedBudget"]', '1200,50');
        $this->closeProjectDialog($row);
        $this->projectDialog($row, 'teams');
        $this->click($row . ' [data-testid="project-field-team"][value="' . $this->teamId . '"]');
        $this->click($row . ' dialog[open] [data-testid="project-dialog-save"]');
        $this->saved($row . ' dialog[open]', $locale);
        $this->browser->waitForVisibility($row . ' [data-testid="project-dialog-teams"][open]');
        $this->closeProjectDialog($row);
        self::assertSame('high', $this->value($row . ' [data-testid="project-field-priority"]'));
        self::assertSame('1', $this->value($row . ' [data-testid="project-field-archived"]'));

        $this->projectDialog($row, 'budget');
        self::assertSelectorTextContains($row . ' [data-testid="project-time-used"]', '1h 0m');
        self::assertSelectorTextContains($row . ' [data-testid="project-time-remaining"]', '4h 0m');
        // Tampering with the hidden minutes must still be rejected by the server.
        $this->browser->executeScript('document.querySelector("[data-project-row=\"' . $this->activeProjectId . '\"] input[name=estimatedMinutes]").value = "-1";');
        $this->click($row . ' dialog[open] [data-testid="project-dialog-save"]');
        $this->browser->waitForVisibility($row . ' dialog[open] [data-testid="save-status"][data-state="error"]');
        $this->closeProjectDialog($row);
        $this->projectDialog($row, 'todos');
        $this->click($row . ' [data-testid="project-todo"] summary');
        self::assertSelectorTextContains($row . ' [data-testid="project-timelog"]', 'Existing member');
        self::assertSelectorTextContains($row . ' [data-testid="project-timelog"]', 'Browser work');
        $this->browser->getWebDriver()->getKeyboard()->pressKey(WebDriverKeys::ESCAPE);
        $this->browser->waitForInvisibility($row . ' dialog[open]');

        $this->click('[data-testid="project-create"]');
        $this->click('[data-testid="project-create"]');
        $draft = '[data-project-row="new"]';
        self::assertCount(1, $this->browser->getWebDriver()->findElements(WebDriverBy::cssSelector($draft)));
        $this->fill($draft . ' [data-testid="project-field-name"]', 'New project');
        $this->select($draft . ' [data-testid="project-field-client"]', (string) $this->customerId);
        // The required deadline stays visible in the row.
        $this->click($draft . ' [data-testid="project-save"]');
        self::assertSelectorNotExists($draft . ' dialog[open]');
        $this->browser->executeScript('document.querySelector("[data-project-row=new] input[name=deadline]").value = "2027-03-15";');
        $this->projectDialog($draft, 'budget');
        $this->select($draft . ' [data-testid="project-field-rate"]', '1');
        $this->closeProjectDialog($draft);
        $this->click($draft . ' [data-testid="project-save"]');
        $this->browser->waitForInvisibility($draft);
        self::assertSame('New project', $this->value('[data-testid="project-rows"] > tr:first-child input[name="name"]'));
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));
        $this->click('[data-testid="project-create"]');
        $this->click($draft . ' [data-testid="project-cancel"]');
        $this->browser->waitForInvisibility($draft);

        $this->openClients();
        $client = '[data-client-row="' . $this->customerId . '"]';
        $this->click($client . ' [data-testid="client-projects-open"]');
        $dialog = $client . ' [data-testid="client-projects-dialog"][open]';
        self::assertSelectorTextContains($dialog . ' [data-testid="client-projects-inactive"]', 'Updated website');
        $this->click($dialog . ' [data-project-id="' . $this->activeProjectId . '"]');
        $this->browser->waitForVisibility('[data-testid="projects-panel"]');
        $this->projectDialog($row, 'budget');
        self::assertSame('1200.50', $this->value($row . ' [data-testid="project-field-estimatedBudget"]'));
        self::assertSame('300', $this->value($row . ' [data-testid="project-field-estimatedMinutes"]'));
        $this->select($row . ' [data-testid="project-field-rate"]', '');
        $this->closeProjectDialog($row);
        $this->projectDialog($row, 'teams');
        self::assertTrue($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($row . ' [data-testid="project-field-team"]'))->isSelected());
        $this->click($row . ' [data-testid="project-field-team"]');
        $this->click($row . ' dialog[open] [data-testid="project-dialog-save"]');
        $this->saved($row . ' dialog[open]', $locale);
        $this->browser->waitForVisibility($row . ' dialog[open]');
        $this->closeProjectDialog($row);
        $this->click('[data-testid="nav-dashboard"]');
        $this->browser->waitForVisibility('[data-testid="company-name-input"]');
        $this->click('[data-testid="nav-projects"]');
        $this->browser->waitForVisibility('[data-testid="projects-panel"]');
        $this->projectDialog($row, 'teams');
        self::assertFalse($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($row . ' [data-testid="project-field-team"]'))->isSelected());
        self::assertSame('', $this->value($row . ' [data-testid="project-field-rate"]'));
        self::assertSame('same-document', $this->browser->executeScript('return window.pantherPageMarker;'));
        $this->closeProjectDialog($row);
    }

    #[DataProvider('locales')]
    public function testBudgetButtonWithThirtyPerHour(string $locale): void
    {
        $this->login($locale);
        $this->click('[data-testid="nav-projects"]');
        $this->browser->waitForVisibility('[data-testid="projects-panel"]');
        $row = '[data-project-row="' . $this->activeProjectId . '"]';
        $this->projectDialog($row, 'budget');
        $this->select($row . ' [data-testid="project-field-rate"]', '2');
        // Existing 90-minute estimate must be used even though it is between slider steps.
        $this->fill($row . ' [data-testid="project-field-estimatedBudget"]', '0');
        $this->click($row . ' [data-testid="project-calculate-budget"]');
        self::assertSame('45.00', $this->value($row . ' [data-testid="project-field-estimatedBudget"]'));
        $slider = $this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($row . ' [data-testid="project-field-estimatedHours"]'));
        $slider->sendKeys([WebDriverKeys::HOME, WebDriverKeys::ARROW_RIGHT]);
        self::assertSame('150.00', $this->value($row . ' [data-testid="project-field-estimatedBudget"]'));
        $this->fill($row . ' [data-testid="project-field-estimatedBudget"]', '0');
        $this->click($row . ' [data-testid="project-calculate-budget"]');
        self::assertSame('150.00', $this->value($row . ' [data-testid="project-field-estimatedBudget"]'));
        self::assertSelectorTextContains($row . ' [data-testid="project-calculation-status"]', '150.00');
        $this->select($row . ' [data-testid="project-field-rate"]', '');
        $this->click($row . ' [data-testid="project-calculate-budget"]');
        self::assertSelectorTextContains($row . ' [data-testid="project-calculation-status"]', $locale === 'da' ? 'Vælg en rate' : 'Choose a rate');
        $this->closeProjectDialog($row);
    }

    private function projectDialog(string $row, string $category): void
    {
        $this->click($row . ' [data-testid="project-open-' . $category . '"]');
        $this->browser->waitForVisibility($row . ' [data-testid="project-dialog-' . $category . '"][open]');
    }

    private function closeProjectDialog(string $row): void
    {
        $this->click($row . ' dialog[open] [data-testid="project-dialog-close"]');
        $this->browser->waitForInvisibility($row . ' dialog[open]');
    }

    private function select(string $selector, string $value): void
    {
        (new WebDriverSelect($this->browser->getWebDriver()->findElement(WebDriverBy::cssSelector($selector))))->selectByValue($value);
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
