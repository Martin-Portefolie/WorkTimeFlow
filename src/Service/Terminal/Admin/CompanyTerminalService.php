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
        private EntityManagerInterface  $em,
        private TerminalStateService    $wiz,
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
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);
        $name = isset($flags['name']) ? trim((string)$flags['name']) : null;

        // Non-interactive (flags-based)
        if ($name !== null && $name !== '') {
            return $this->companySetNameNonInteractive($name);
        }

        // Interactive wizard mode (single-step)
        $userId = $this->currentUserKey();
        $this->wiz->set($userId, [
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

        $rows = array_map(static function (Rate $r): array {
            return [
                'id'    => (string) $r->getId(),
                'name'  => (string) $r->getName(),
                'value' => (string) $r->getValue(),
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
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        // If flags present → non-interactive
        if (!empty($flags)) {
            return $this->ratesAddNonInteractive($flags);
        }

        // Interactive wizard
        $userId = $this->currentUserKey();
        $this->wiz->set($userId, [
            'mode' => self::WZ_RATES_ADD,
            'step' => 0,
            'data' => [],
        ]);

        $s     = self::RATES_ADD_STEPS[0];
        $total = count(self::RATES_ADD_STEPS);

        return [
            'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars'    => [
                'label'   => $s['label'],
                'example' => $s['example'] ?? null,
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
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: rates:update <id> [--name=... --value=...]', 'success' => false];
        }

        $id = (int) $args[0];
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

        $this->em->flush();

        return ['output' => 'Updated: ' . implode(', ', $changed), 'success' => true];
    }

    /**
     * rates:delete <id>
     *
     * Example:
     *   rates:delete 10
     */
    public function ratesDeleteCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: rates:delete <id>', 'success' => false];
        }

        $id = (int) $args[0];
        if ($id <= 0) {
            return ['output' => 'Invalid rate id.', 'success' => false];
        }

        /** @var Rate|null $rate */
        $rate = $this->rates->find($id);
        if (!$rate) {
            return ['output' => 'Rate not found.', 'success' => false];
        }

        $name = $rate->getName();
        $rid  = $rate->getId();

        $this->em->remove($rate);
        $this->em->flush();

        return [
            'output'  => sprintf('Rate id=%d (%s) deleted.', $rid, (string) $name),
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
        $state  = $this->wiz->get($userId);
        if (!$state) {
            return null; // no wizard active
        }

        $t = trim($rawInput);

        // Allow restarting rates-add wizard by typing the command again
        if ($t === 'rates:add') {
            $this->wiz->set($userId, [
                'mode' => self::WZ_RATES_ADD,
                'step' => 0,
                'data' => [],
            ]);

            $s     = self::RATES_ADD_STEPS[0];
            $total = count(self::RATES_ADD_STEPS);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $s['label'],
                    'example' => $s['example'] ?? null,
                    'step'    => 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // Allow restarting company:set-name wizard by typing the command again
        if ($t === 'company:set-name') {
            $this->wiz->set($userId, [
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
        if ($t === 'cancel') {
            $this->wiz->clear($userId);
            return ['output' => 'Canceled.', 'success' => true];
        }

        // Route to the active wizard handler
        return match ($state['mode'] ?? null) {
            self::WZ_RATES_ADD        => $this->handleRatesAddStep($state, $t, $userId),
            self::WZ_COMPANY_SET_NAME => $this->handleCompanySetNameStep($state, $t, $userId),
            default                   => null,
        };
    }

    private function handleRatesAddStep(array $state, string $rawInput, string $userId): array
    {
        $i     = (int) ($state['step'] ?? 0);
        $steps = self::RATES_ADD_STEPS;

        // Save answer from previous step
        if ($i < count($steps)) {
            $key = $steps[$i]['key'];
            $val = ($rawInput === '') ? null : trim($rawInput);
            $state['data'][$key] = $val;
            $i++;
        }

        // More steps? ask next question
        if ($i < count($steps)) {
            $state['step'] = $i;
            $this->wiz->set($userId, $state);

            $s     = $steps[$i];
            $total = count($steps);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $s['label'],
                    'example' => $s['example'] ?? null,
                    'step'    => $i + 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // All answers collected → create Rate
        $data = $state['data'] ?? [];
        $name = (string) ($data['name'] ?? '');
        $value = (string) ($data['value'] ?? '');

        if ($name === '' || $value === '' || !is_numeric($value)) {
            $this->wiz->clear($userId);
            return [
                'output'  => 'Invalid rate data. Name and numeric value are required.',
                'success' => false,
            ];
        }

        $company = $this->getOrCreateCompany();

        $rate = new Rate();
        $rate->setName($name);
        $rate->setValue($value);
        $rate->setCompany($company);

        $this->em->persist($rate);
        $this->em->flush();

        // Clear wizard
        $this->wiz->clear($userId);

        return [
            'view'    => 'terminal/admin/terminal_commands/_rate_created_summary.html.twig',
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
        // Single-step wizard: just take the input as name
        $name = trim($rawInput);

        if ($name === '') {
            $this->wiz->clear($userId);
            return [
                'output'  => 'Company name cannot be empty.',
                'success' => false,
            ];
        }

        $company = $this->findCompany();
        if (!$company) {
            $company = new Company();
            $this->em->persist($company);
        }

        $company->setName($name);
        $this->em->flush();

        $this->wiz->clear($userId);

        return [
            'output'  => sprintf('Company name set to "%s".', $name),
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
        $this->em->persist($company);
        $this->em->flush();

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
