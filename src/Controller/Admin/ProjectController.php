<?php

namespace App\Controller\Admin;


use App\Entity\Company;
use App\Entity\Project;
use App\Entity\Rate;
use App\Entity\Team;
use App\Form\ProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/project', name: 'admin_project')]
    public function index(Request $request): Response
    {
        $searchTerm = $request->query->get('search');
        $queryBuilder = $this->entityManager->getRepository(Project::class)->createQueryBuilder('p')
            ->leftJoin('p.client', 'c') // Join client table for searching
            ->leftJoin('p.teams', 't'); // Join teams for searching

        if ($searchTerm) {
            $queryBuilder->andWhere('p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        // Pagination setup
        $pagerfanta = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));


        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        $allTeams = $this->entityManager->getRepository(Team::class)->findAll();
        $company = $this->entityManager->getRepository(Company::class)->find(1);

        $projectsDataArray = [];

        foreach ($pagerfanta->getCurrentPageResults() as $project) {
            $projectsDataArray[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'client' => $project->getClient()->getName(),
                'teams' => $project->getTeams()->toArray(), // Convert collection to array
                'todo' => $project->getTodos()->toArray(), // Convert collection to array
                'is_archived' => $project->isArchived(),
                'total_minutes' => $project->getTotalMinutesUsed(),
                'deadline' => $project->getDeadline()?->format('Y-m-d'),
                'last_updated'=>$project->getLastUpdated()?->format('Y-m-d'),
                'priority' => $project->getPriority()->value,
                'estimated_budget' => $project->getEstimatedBudget(),
                'estimated_minutes' => $project->getEstimatedMinutes() ?? 0,
                'remaining_minutes' => $project->getRemainingMinutes() ?? 0,
                'rate' => $project->getRate(),
                'is_paid' => $project->isPaid(),
            ];
        }

        return $this->render('admin/project/test.html.twig', [
            'projectDataArray' => $projectsDataArray,
            'allTeams' => $allTeams,
            'company' => $company,
            'pager' => $pagerfanta,
        ]);
    }

    #[Route('/admin/project/new', name: 'admin_project_new')]
    public function new(Request $request): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedTeams = $form->get('teams')->getData();
            foreach ($selectedTeams as $team) {
                $project->addTeam($team);
            }

            $this->entityManager->persist($project);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_project');
        }

        return $this->render('admin/project/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/admin/project/update/{id}', name: 'admin_project_update', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        // name, description, is_archived
        $project->setName($request->request->get('name'));
        $project->setDescription($request->request->get('description'));
        $project->setArchived($request->request->has('is_archived'));
        $project->setIsPaid($request->request->has('is_paid'));


        // Priority
        $priorityValue = $request->request->get('priority');
        if (in_array($priorityValue, \App\Enum\Priority::getValues())) {
            $project->setPriority(\App\Enum\Priority::from($priorityValue));
        }

        // Budget
        $budgetValue = $request->request->get('estimated_budget');
        $project->setEstimatedBudget(null !== $budgetValue ? (float) $budgetValue : null);

        // Estimated hours
        $estimatedTime = $request->request->get('estimated_time');
        $project->setEstimatedTime(null !== $estimatedTime ? (int) $estimatedTime : 0);

        // Handle Rate Selection
        $selectedRateId = $request->request->get('rate'); // Get rate from form submission

        if ($selectedRateId) {
            $rate = $this->entityManager->getRepository(Rate::class)->find($selectedRateId);
            if ($rate) {
                $project->setRate($rate); // Assign rate object to project
            }
        } else {
            $project->setRate(null);
        }

        // Handle Deadline
        $deadlineValue = $request->request->get('deadline');
        if ($deadlineValue) {
            $project->setDeadline(new \DateTime($deadlineValue));
        } else {
            $project->setDeadline(null);
        }





        // Manage team associations
        $selectedTeamIds = $request->request->all('team_ids', []);

        // Detach unselected teams
        foreach ($project->getTeams() as $team) {
            if (!in_array($team->getId(), $selectedTeamIds)) {
                $project->removeTeam($team);
            }
        }

        foreach ($selectedTeamIds as $teamId) {
            $team = $this->entityManager->getRepository(Team::class)->find($teamId);
            if ($team && !$project->getTeams()->contains($team)) {
                $project->addTeam($team);
            }
        }



        $this->entityManager->flush();

        return $this->redirectToRoute('admin_project');
    }

    #[Route('/admin/project/download/{id}', name: 'admin_project_download')]
    public function generateInvoice(int $id): Response
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        // Styles
        $phpWord->addTitleStyle(1, ['size' => 20, 'bold' => true, 'color' => '1E90FF']);
        $phpWord->addTitleStyle(2, ['size' => 14, 'bold' => true, 'color' => '333333']);
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80
        ];
        $phpWord->addTableStyle('InvoiceTable', $tableStyle);

        $section = $phpWord->addSection();

        // **Header**
        $section->addText("INVOICE", ['size' => 18, 'bold' => true], ['alignment' => 'center']);
        $section->addText("DATE: " . date('Y-m-d'), ['size' => 12]);
        $section->addText("INVOICE #: " . $project->getId(), ['size' => 12, 'bold' => true]);
        $section->addText("Customer ID: " . strtoupper(substr($project->getClient()->getName(), 0, 3)) . $project->getId(), ['size' => 12]);

        // **Customer Details**
        $section->addText("To:", ['size' => 14, 'bold' => true]);
        $section->addText($project->getClient()->getName());
        $section->addText($project->getClient()->getContactPerson());
        $section->addText($project->getClient()->getAdress() ?? "No address provided");
        $section->addText($project->getClient()->getContactPhone() ?? "No phone number");
        $section->addText($project->getClient()->getContactEmail() ?? "No phone number");

        $section->addTextBreak(1);

        // **Sales & Payment Terms**
        $table = $section->addTable('InvoiceTable');

        $table->addRow();
        $table->addCell(4000)->addText("Our Company", ['bold' => true]);
        $table->addCell(8000)->addText("Assigned Team");


        $table->addRow();
        $table->addCell(4000)->addText("Salesperson:", ['bold' => true]);
        $table->addCell(8000)->addText("Person Name");

        $table->addRow();
        $table->addCell(4000)->addText("Job:", ['bold' => true]);
        $table->addCell(8000)->addText($project->getDescription());

        $table->addRow();
        $table->addCell(4000)->addText("Payment Terms:", ['bold' => true]);
        $table->addCell(8000)->addText("Due on receipt");

        $table->addRow();
        $table->addCell(4000)->addText("Due Date:", ['bold' => true]);
        $table->addCell(8000)->addText($project->getDeadline()?->format('Y-m-d') ?? 'N/A');

        $section->addTextBreak(1);

        // **Service Table (Qty, Description, Rate, Line Total)**
        $section->addText("Project Breakdown", ['size' => 14, 'bold' => true]);
        $serviceTable = $section->addTable('InvoiceTable');

        // Table Header
        $serviceTable->addRow();
        $serviceTable->addCell(2000)->addText("Qty", ['bold' => true]);
        $serviceTable->addCell(5000)->addText("Description", ['bold' => true]);
        $serviceTable->addCell(3000)->addText("Unit Price", ['bold' => true]);
        $serviceTable->addCell(3000)->addText("Line Total", ['bold' => true]);

        // Service Entries
        $estimatedHours = $project->getEstimatedMinutes() ? ($project->getEstimatedMinutes() / 60) : 0;
        $rate = $project->getRate() ? $project->getRate()->getValue() : 0;
        $lineTotal = $estimatedHours * $rate;

        $serviceTable->addRow();
        $serviceTable->addCell(2000)->addText(number_format($estimatedHours, 2));
        $serviceTable->addCell(5000)->addText("Estimated Work Hours");
        $serviceTable->addCell(3000)->addText(number_format($rate, 2) . " EUR/hour");
        $serviceTable->addCell(3000)->addText(number_format($lineTotal, 2) . " EUR");

        $section->addTextBreak(1);

        // **Total Calculation**
        $subtotal = $lineTotal;
        $tax = $subtotal * 0.25; // 25% Tax (Changeable)
        $total = $subtotal + $tax;

        $totalTable = $section->addTable('InvoiceTable');

        $totalTable->addRow();
        $totalTable->addCell(6000)->addText("Subtotal", ['bold' => true]);
        $totalTable->addCell(3000)->addText(number_format($subtotal, 2) . " EUR");

        $totalTable->addRow();
        $totalTable->addCell(6000)->addText("Sales Tax (5%)", ['bold' => true]);
        $totalTable->addCell(3000)->addText(number_format($tax, 2) . " EUR");

        $totalTable->addRow();
        $totalTable->addCell(6000)->addText("Total", ['bold' => true, 'size' => 14]);
        $totalTable->addCell(3000)->addText(number_format($total, 2) . " EUR", ['bold' => true, 'size' => 14]);

        $section->addTextBreak(1);

        // **Footer: Payment Info**
        $section->addText("Make all payments to: Your Company Name", ['size' => 12, 'bold' => true]);
        $section->addText("Thank you for your business!", ['size' => 12, 'italic' => true]);

        $footer = $section->addFooter();
        $footer->addText("Generated on " . date('Y-m-d H:i'), ['italic' => true], ['alignment' => 'center']);

        // Save as temp file
        $fileName = 'Invoice_Project_' . $project->getId() . '.docx';
        $tempFile = sys_get_temp_dir() . '/' . $fileName;
        $phpWord->save($tempFile, 'Word2007');

        // Return as downloadable response
        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }


}
