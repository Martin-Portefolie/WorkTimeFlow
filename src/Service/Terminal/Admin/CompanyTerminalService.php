<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Company;
use App\Entity\Rate;
use App\Repository\CompanyRepository;
use App\Repository\RateRepository;
use App\Service\Terminal\ArgsParser;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class CompanyTerminalService
{
    /** Wizard modes */
    private const WZ_COMPANY_SET_NAME = 'company:set-name';
    private const WZ_RATES_ADD        = 'rates:add';

    /** Wizard steps for rates:add */
    private const RATES_ADD_STEPS = [
        ['key' => 'name',  'label' => 'Rate name?',  'example' => 'Normal'],
        ['key' => 'value', 'label' => 'Rate value (decimal, e.g. 500 or 499.99)?', 'example' => '500'],
    ];

    public function __construct(
        private CompanyRepository       $companies,
        private RateRepository          $rates,
        private EntityManagerInterface  $entityManager,
        private TerminalStateService    $wizardState,
        private Security                $security,
    ) {}


    // -------------------------------------------------------------------------
    // COMPANY COMMANDS
    // -------------------------------------------------------------------------

    /**
     * company:show
     *
     * Shows the single company (if any), with logo and associated rates.
     *
     * Usage: company:show
     */
    public function companyShowCommand(array $tokens): array
    {
        // No args expected – ignore anything else and just show.
        $company = $this->findCompany();
        $rates   = [];

        if ($company) {
            // Eager-load via relation or repository – simple version here:
            $rates = $this->rates->findBy(['company' => $company], ['name' => 'ASC']);
        }

        return [
            'view'    => 'terminal/admin/terminal_commands/_company_show_admin.html.twig',
            'vars'    => [
                'company' => $company,
                'rates'   => $rates,
            ],
            'success' => true,
        ];
    }

    /**
     * company:set-name [--name="ACME A/S"]
     *
     * If --name is given → non-interactive.
     * If no flags → start interactive wizard using TerminalStateService.
     */
    public function companySetNameCommand(array $tokens): array
    {
        ['args' => $arguments, 'flags' => $flags] = ArgsParser::parse($tokens);
        if ($tokens !== []) {
            if (!isset($flags['name']) || !is_string($flags['name']) || count($flags) !== 1) {
                return ['output' => 'Usage: company:set-name --name="Company name"', 'success' => false];
            }
            // This command has no positional arguments: remaining words belong to the name.
            $name = trim(implode(' ', [$flags['name'], ...$arguments]));
            if (strlen($name) >= 2 && in_array($name[0], ['"', "'"], true) && $name[-1] === $name[0]) {
                $name = substr($name, 1, -1);
            }
            return $this->companySetNameNonInteractive($name);
        }

        // Interactive wizard mode (single-step)
        $userId = $this->currentUserKey();
        $this->wizardState->set($userId, [
            'mode' => self::WZ_COMPANY_SET_NAME,
            'step' => 0,
            'data' => [],
        ]);

        return [
            'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars'    => [
                'label'   => 'What is the company name?',
                'example' => 'ACME A/S',
                'step'    => 1,
                'total'   => 1,
            ],
            'success' => true,
            'await'   => true,
        ];
    }


    // -------------------------------------------------------------------------
    // RATES COMMANDS
    // -------------------------------------------------------------------------

    /**
     * rates:list
     *
     * Lists all rates for the single company.
     *
     * Usage: rates:list
     */
    public function ratesListCommand(array $tokens): array
    {
        $company = $this->findCompany();
        if (!$company) {
            return [
                'output'  => 'Company not initialized yet. Use company:set-name first.',
                'success' => false,
            ];
        }

        $rateEntities = $this->rates->findBy(['company' => $company], ['name' => 'ASC', 'id' => 'ASC']);

        $rows = array_map(static function (Rate $rate): array {
            return [
                'id'    => (string) $rate->getId(),
                'name'  => (string) $rate->getName(),
                'value' => (string) $rate->getValue(),
            ];
        }, $rateEntities);

        return [
            'view'    => 'terminal/admin/terminal_commands/_rates_list_admin.html.twig',
            'vars'    => [
                'rows'        => $rows,
                'companyName' => $company->getName(),
            ],
            'success' => true,
        ];
    }

    /**
     * rates:add
     *
     * Non-interactive:
     *   rates:add --name="Normal" --value=500
     *
     * Interactive (wizard) if no flags:
     *   rates:add
     */
    public function ratesAddCommand(array $tokens): array
    {
        ['args' => $arguments, 'flags' => $flags] = ArgsParser::parse($tokens);

        // If flags present → non-interactive
        if (!empty($flags)) {
            return $this->ratesAddNonInteractive($flags);
        }

        // Interactive wizard
        $userId = $this->currentUserKey();
        $this->wizardState->set($userId, [
            'mode' => self::WZ_RATES_ADD,
            'step' => 0,
            'data' => [],
        ]);

        $currentStep     = self::RATES_ADD_STEPS[0];
        $total = count(self::RATES_ADD_STEPS);

        return [
            'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars'    => [
                'label'   => $currentStep['label'],
                'example' => $currentStep['example'] ?? null,
                'step'    => 1,
                'total'   => $total,
            ],
            'success' => true,
            'await'   => true,
        ];
    }

    /**
     * rates:update <id> [--name= --value=]
     *
     * Example:
     *   rates:update 5 --name=VIP --value=999.95
     */
    public function ratesUpdateCommand(array $tokens): array
    {
        ['args' => $arguments, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($arguments[0])) {
            return ['output' => 'Usage: rates:update <id> [--name=... --value=...]', 'success' => false];
        }

        $id = (int) $arguments[0];
        if ($id <= 0) {
            return ['output' => 'Invalid rate id.', 'success' => false];
        }

        /** @var Rate|null $rate */
        $rate = $this->rates->find($id);
        if (!$rate) {
            return ['output' => 'Rate not found.', 'success' => false];
        }

        $changed = [];

        if (array_key_exists('name', $flags)) {
            $rate->setName((string) $flags['name']);
            $changed[] = 'name';
        }

        if (array_key_exists('value', $flags)) {
            $value = (string) $flags['value'];
            if (!is_numeric($value)) {
                return ['output' => 'Invalid value. Use decimal like 500 or 499.99.', 'success' => false];
            }
            $rate->setValue($value);
            $changed[] = 'value';
        }

        if (!$changed) {
            return ['output' => 'Nothing to update.', 'success' => false];
        }

        $this->entityManager->flush();

        return ['output' => 'Updated: ' . implode(', ', $changed), 'success' => true, 'ratesChanged' => true];
    }

    /**
     * rates:delete <id>
     *
     * Example:
     *   rates:delete 10
     */
    public function ratesDeleteCommand(array $tokens): array
    {
        ['args' => $arguments, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($arguments[0])) {
            return ['output' => 'Usage: rates:delete <id>', 'success' => false];
        }

        $id = (int) $arguments[0];
        if ($id <= 0) {
            return ['output' => 'Invalid rate id.', 'success' => false];
        }

        /** @var Rate|null $rate */
        $rate = $this->rates->find($id);
        if (!$rate) {
            return ['output' => 'Rate not found.', 'success' => false];
        }

        $name = $rate->getName();
        $rateId  = $rate->getId();

        $this->entityManager->remove($rate);
        $this->entityManager->flush();

        return [
            'output'  => sprintf('Rate id=%d (%s) deleted.', $rateId, (string) $name),
            'ratesChanged' => true,
            'success' => true,
        ];
    }


    // -------------------------------------------------------------------------
    // WIZARD HANDLING
    // -------------------------------------------------------------------------

    /**
     * Called from TerminalService *before* normal command routing,
     * similar to ClientTerminalService::handleInteractive().
     */
    public function handleInteractive(string $rawInput): ?array
    {
        $userId = $this->currentUserKey();
        $state  = $this->wizardState->get($userId);
        if (!$state) {
            return null; // no wizard active
        }

        $trimmedInput = trim($rawInput);

        // Allow restarting rates-add wizard by typing the command again
        if ($trimmedInput === 'rates:add') {
            $this->wizardState->set($userId, [
                'mode' => self::WZ_RATES_ADD,
                'step' => 0,
                'data' => [],
            ]);

            $currentStep     = self::RATES_ADD_STEPS[0];
            $total = count(self::RATES_ADD_STEPS);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $currentStep['label'],
                    'example' => $currentStep['example'] ?? null,
                    'step'    => 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // Allow restarting company:set-name wizard by typing the command again
        if ($trimmedInput === 'company:set-name') {
            $this->wizardState->set($userId, [
                'mode' => self::WZ_COMPANY_SET_NAME,
                'step' => 0,
                'data' => [],
            ]);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => 'What is the company name?',
                    'example' => 'ACME A/S',
                    'step'    => 1,
                    'total'   => 1,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // Cancel keyword
        if ($trimmedInput === 'cancel') {
            $this->wizardState->clear($userId);
            return ['output' => 'Canceled.', 'success' => true];
        }

        // Route to the active wizard handler
        return match ($state['mode'] ?? null) {
            self::WZ_RATES_ADD        => $this->handleRatesAddStep($state, $trimmedInput, $userId),
            self::WZ_COMPANY_SET_NAME => $this->handleCompanySetNameStep($state, $trimmedInput, $userId),
            default                   => null,
        };
    }

    private function handleRatesAddStep(array $state, string $rawInput, string $userId): array
    {
        $stepIndex     = (int) ($state['step'] ?? 0);
        $steps = self::RATES_ADD_STEPS;

        // Save answer from previous step
        if ($stepIndex < count($steps)) {
            $key = $steps[$stepIndex]['key'];
            $stepValue = ($rawInput === '') ? null : trim($rawInput);
            $state['data'][$key] = $stepValue;
            $stepIndex++;
        }

        // More steps? ask next question
        if ($stepIndex < count($steps)) {
            $state['step'] = $stepIndex;
            $this->wizardState->set($userId, $state);

            $currentStep     = $steps[$stepIndex];
            $total = count($steps);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $currentStep['label'],
                    'example' => $currentStep['example'] ?? null,
                    'step'    => $stepIndex + 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        $response = $this->ratesAddNonInteractive($state['data'] ?? []);
        $this->wizardState->clear($userId);
        return $response;
    }

    private function ratesAddNonInteractive(array $flags): array
    {
        $name = $flags['name'] ?? null;
        $value = $flags['value'] ?? null;
        if (!is_string($name) || !is_string($value) || array_diff(array_keys($flags), ['name', 'value'])) {
            return ['output' => 'Usage: rates:add --name=Normal --value=500', 'success' => false];
        }
        $name = trim($name);
        $value = str_replace(',', '.', trim($value));
        if ($name === '' || mb_strlen($name) > 255 || !preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
            return ['output' => 'A name (1–255 characters) and value from 0 to 99999999.99 with at most two decimals are required.', 'success' => false];
        }
        $value = (ltrim($parts[1], '0') ?: '0') . '.' . str_pad($parts[2] ?? '', 2, '0');

        $company = $this->getOrCreateCompany();

        $rate = new Rate();
        $rate->setName($name);
        $rate->setValue($value);
        $rate->setCompany($company);

        $this->entityManager->persist($rate);
        $this->entityManager->flush();

        return [
            'view'    => 'terminal/admin/terminal_commands/_rate_created_summary.html.twig',
            'ratesChanged' => true,
            'vars'    => [
                'id'          => $rate->getId(),
                'name'        => $name,
                'value'       => $value,
                'companyName' => $company->getName(),
            ],
            'success' => true,
        ];
    }

    private function handleCompanySetNameStep(array $state, string $rawInput, string $userId): array
    {
        $response = $this->companySetNameNonInteractive($rawInput);
        $this->wizardState->clear($userId);
        return $response;
    }

    private function companySetNameNonInteractive(string $name): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            return ['output' => 'Company name must contain 1 to 255 characters.', 'success' => false];
        }
        $company = $this->findCompany();
        if (!$company) {
            $company = new Company();
            $this->entityManager->persist($company);
        }
        $company->setName($name);
        $this->entityManager->flush();
        return [
            'output' => sprintf('Company name set to "%s".', htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            'companyName' => $company->getName(),
            'success' => true,
        ];
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    private function findCompany(): ?Company
    {
        // There should only be one – pick the first if multiple somehow exist.
        $all = $this->companies->findBy([], ['id' => 'ASC'], 1);
        return $all[0] ?? null;
    }

    private function getOrCreateCompany(): Company
    {
        $company = $this->findCompany();
        if ($company) {
            return $company;
        }

        $company = new Company();
        // Name can be null initially – admin can set it later.
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $company;
    }

    private function currentUserKey(): string
    {
        $user = $this->security->getUser();
        return method_exists($user, 'getUserIdentifier')
            ? (string) $user->getUserIdentifier()
            : 'guest';
    }
}
