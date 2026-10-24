<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Project;
use App\Service\Admin\ProjectAdminService;
use App\Entity\User;
use App\Service\Admin\UserAdminService;
use App\Service\Admin\UserWelcomeMailException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Service\Admin\ClientAdminService;
use App\Form\Admin\CompanyFlowData;
use App\Form\Admin\CompanyFlowType;
use App\Service\Admin\CompanyAdminService;
use App\Service\Terminal\TerminalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/projects', name: 'admin_projects_panel', methods: ['GET'])]
    public function projects(ProjectAdminService $service): Response
    {
        return $this->render('gui/admin/_projects_list.html.twig', ['projects' => $service->list()] + $service->choices());
    }

    #[Route('/admin/projects/new', name: 'admin_project_create_panel', methods: ['POST'])]
    public function createProject(Request $request, ProjectAdminService $service, TranslatorInterface $translator): Response
    {
        return $this->saveProjectRow($request, new Project(), $service, $translator);
    }

    #[Route('/admin/projects/{id}', name: 'admin_project_panel', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function project(Request $request, Project $project, ProjectAdminService $service, TranslatorInterface $translator): Response
    {
        if ($request->isMethod('POST')) {
            return $this->saveProjectRow($request, $project, $service, $translator);
        }
        return $this->render('gui/admin/_projects_list.html.twig', ['projects' => [$project]] + $service->choices());
    }

    private function saveProjectRow(Request $request, Project $project, ProjectAdminService $service, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('project_' . ($project->getId() ?? 'new'), $request->request->get('_token'))) {
            return $this->json(['error' => $translator->trans('client.expired')], Response::HTTP_FORBIDDEN);
        }
        try {
            $service->save($project, $request->request->all());
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $this->render('gui/admin/_project_row_response.html.twig', ['project' => $project, 'saved' => true] + $service->choices());
    }

    #[Route('/admin/users', name: 'admin_users_panel', methods: ['GET'])]
    public function users(UserAdminService $service): Response
    {
        return $this->render('gui/admin/_users_list.html.twig', ['data' => ['rows' => $service->list()]]);
    }

    #[Route('/admin/users/new', name: 'admin_user_create_panel', methods: ['POST'])]
    public function createUser(Request $request, UserAdminService $service, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('user_new', $request->request->get('_token'))) {
            return $this->json(['error' => $translator->trans('client.expired')], Response::HTTP_FORBIDDEN);
        }
        try {
            $user = $service->create($request->request->all());
        } catch (UserWelcomeMailException) {
            return $this->json(['error' => $translator->trans('user.mail_failed')], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => $translator->trans('user.email_taken')], Response::HTTP_CONFLICT);
        }

        return $this->render('gui/admin/_user_row_response.html.twig', ['user' => $user, 'saved' => true, 'created' => true]);
    }

    #[Route('/admin/users/{id}', name: 'admin_user_panel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateUser(Request $request, User $user, UserAdminService $service, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('user_' . $user->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => $translator->trans('client.expired')], Response::HTTP_FORBIDDEN);
        }
        try {
            $service->update($user, $request->request->all(), $this->getUser());
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => $translator->trans('user.email_taken')], Response::HTTP_CONFLICT);
        }

        return $this->render('gui/admin/_user_row_response.html.twig', ['user' => $user, 'saved' => true]);
    }

    #[Route('/admin/dashboard/panel', name: 'admin_dashboard_panel', methods: ['GET'])]
    public function dashboardPanel(): Response
    {
        return $this->render('gui/admin/_overview.html.twig');
    }

    #[Route('/admin/clients', name: 'admin_clients_panel', methods: ['GET'])]
    public function clients(ClientAdminService $service): Response
    {
        return $this->render('gui/admin/_clients_list.html.twig', ['data' => ['rows' => $service->list()]]);
    }

    #[Route('/admin/clients/new', name: 'admin_client_create_panel', methods: ['GET', 'POST'])]
    public function createClient(Request $request, ClientAdminService $service, TranslatorInterface $translator): Response
    {
        $client = new Client();
        $saved = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('client_new', $request->request->get('_token'))) {
                return $this->json(['error' => $translator->trans('client.expired')], Response::HTTP_FORBIDDEN);
            }
            try {
                $client = $service->create($request->request->all());
                $saved = true;
            } catch (\InvalidArgumentException $exception) {
                return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->render(
            $request->request->get('_view') === 'row' ? 'gui/admin/_client_row_response.html.twig' : 'gui/admin/_clients_show.html.twig',
            ['client' => $client, 'saved' => $saved],
        );
    }

    #[Route('/admin/clients/{id}', name: 'admin_client_panel', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function client(Request $request, Client $client, ClientAdminService $service, TranslatorInterface $translator): Response
    {
        $saved = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('client_' . $client->getId(), $request->request->get('_token'))) {
                return $this->json(['error' => $translator->trans('client.expired')], Response::HTTP_FORBIDDEN);
            }
            try {
                $service->update($client, $request->request->all());
                $saved = true;
            } catch (\InvalidArgumentException $exception) {
                return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->render(
            $request->request->get('_view') === 'row' ? 'gui/admin/_client_row_response.html.twig' : 'gui/admin/_clients_show.html.twig',
            ['client' => $client, 'saved' => $saved],
        );
    }

    #[Route('/admin/', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {

        return $this->render('admin/index.html.twig');

    }

    #[Route('/admin/company/form', name: 'admin_company_form', methods: ['GET', 'POST'])]
    public function companyForm(
        Request $request,
        CompanyAdminService $companyAdminService,
    ): Response {
        $data = new CompanyFlowData();
        $company = $companyAdminService->getCompany();
        $data->name = $company?->getName();
        $data->rates = $company?->getRates()->toArray() ?? [];

        $flow = $this->createForm(CompanyFlowType::class, $data);
        $flow->handleRequest($request);
        $saved = false;

        if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished()) {
            if ($data->name !== null) {
                $companyAdminService->updateName($data->name);
            }
            $saved = true;
        }

        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('gui/admin/company_flow_frame.html.twig', [
                'form' => $flow->getStepForm()->createView(),
                'saved' => $saved,
            ]);
        }

        return $this->render('gui/admin/company_flow.html.twig', [
            'form' => $flow->getStepForm()->createView(),
            'flow' => $flow,
            'saved' => $saved,
        ]);
    }

    #[Route('/admin/company/name', name: 'admin_company_name_update', methods: ['POST'])]
    public function updateCompanyName(Request $request, CompanyAdminService $companyAdminService): JsonResponse
    {
        try {
            $company = $companyAdminService->updateName((string) $request->request->get('company_name', ''));
            return $this->json(['success' => true, 'name' => $company->getName()]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/admin/company/logo', name: 'admin_company_logo_update', methods: ['POST'])]
    public function updateCompanyLogo(Request $request, CompanyAdminService $service, Packages $assets): JsonResponse
    {
        if (!$this->isCsrfTokenValid('company_logo', $request->request->get('_token'))) {
            return $this->json(['success' => false], Response::HTTP_FORBIDDEN);
        }
        $file = $request->files->get('logo');
        if (!$file instanceof UploadedFile) {
            return $this->json(['success' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $company = $service->uploadLogo($file);
            return $this->json(['success' => true, 'url' => $assets->getUrl($company->getLogo())]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }


    #[Route('/admin/company/currency', name: 'admin_company_currency_update', methods: ['POST'])]
    public function updateCompanyCurrency(Request $request, CompanyAdminService $service, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('company_currency', $request->request->get('_token'))) {
            return $this->json(['error' => $translator->trans('company.rates.expired')], Response::HTTP_FORBIDDEN);
        }
        try {
            $region = (string) $request->request->get('region', '');
            $company = $service->updateCurrency((string) $request->request->get('currency', ''), $region);
            $request->getSession()->set('company_currency_region', $region);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $this->render('gui/admin/_company_rates.html.twig', [
            'rates' => $service->getRates(),
            'currency' => $company->getCurrency(),
            'saved' => true,
        ]);
    }

    #[Route('/admin/company/rates/{id}', name: 'admin_company_rate_save', defaults: ['id' => 0], requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveCompanyRate(Request $request, int $id, CompanyAdminService $service, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('company_rates', $request->request->get('_token'))) {
            return $this->json(['error' => $translator->trans('company.rates.expired')], Response::HTTP_FORBIDDEN);
        }
        try {
            $action = $request->request->get('action', 'save');
            if ($action === 'delete' && $id > 0) {
                $service->deleteRate($id);
            } elseif ($action === 'save') {
                $name = (string) $request->request->get('name', '');
                $value = (string) $request->request->get('value', '');
                if ($id > 0) {
                    $service->updateRate($id, $name, $value);
                } else {
                    $service->createRate($name, $value);
                }
            } else {
                return $this->json(['error' => $translator->trans('company.rates.failed')], Response::HTTP_BAD_REQUEST);
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ForeignKeyConstraintViolationException) {
            return $this->json(['error' => $translator->trans('company.rates.in_use')], Response::HTTP_CONFLICT);
        }

        return $this->render('gui/admin/_company_rates.html.twig', [
            'rates' => $service->getRates(),
            'currency' => $service->getCompany()?->getCurrency() ?? 'DKK',
            'saved' => true,
        ]);
    }

    #[Route('/admin/terminal/run', name: 'admin_terminal_run', methods: ['POST'])]
    public function run(Request $request, TerminalService $terminal, CompanyAdminService $companyService): Response
    {
        $input  = (string) $request->request->get('input', '');
        $result = $terminal->execute($input);

        $output = $result['output'] ?? null;

        if (!$output && isset($result['view'])) {
            $output = $this->renderView(
                $result['view'],
                $result['vars'] ?? []
            );
        }

        $guiHtml = null;

        if (isset($result['gui']['view'])) {
            $guiHtml = $this->renderView(
                'gui/admin/_content_inner.html.twig',
                [
                    'view' => $result['gui']['view'] ?? 'overview',
                    'data' => $result['gui']['data'] ?? [],
                ]
            );
        }

        $ratesHtml = null;
        if (($result['success'] ?? false) && ($result['ratesChanged'] ?? false)) {
            $ratesHtml = $this->renderView('gui/admin/_company_rates.html.twig', [
                'rates' => $companyService->getRates(),
                'currency' => $companyService->getCompany()?->getCurrency() ?? 'DKK',
            ]);
        }

        return $this->render('terminals/_line.html.twig', [
            'input'    => $input,
            'output'   => $output ?? '',
            'success'  => (bool)($result['success'] ?? false),
            'await'    => (bool)($result['await'] ?? false),
            'user'     => $this->getUser()?->getUserIdentifier() ?? 'guest',
            'gui'      => $result['gui'] ?? null,
            'guiHtml'  => $guiHtml,
            'companyName' => $result['companyName'] ?? null,
            'ratesHtml' => $ratesHtml,
        ]);
    }

}
