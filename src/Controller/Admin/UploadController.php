<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Todo;
use App\Enum\Priority;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\NoReturn;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/upload', name: 'admin_upload_index')]
    public function index(): Response
    {
        return $this->render('admin/upload/index.html.twig');
    }

    #[Route('/admin/upload/clients', name: 'admin_upload_clients', methods: ['POST'])]
    public function uploadClients(Request $request): Response
    {
        $uploadedFile = $request->files->get('client_docx');

        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'No file uploaded.');
            return $this->redirectToRoute('admin_upload_index');
        }

        if ($uploadedFile->getClientOriginalExtension() !== 'docx') {
            $this->addFlash('error', 'Invalid file type. Please upload a .docx file.');
            return $this->redirectToRoute('admin_upload_index');
        }

        $filePath = $uploadedFile->getPathname();
        $phpWord = IOFactory::load($filePath);

        $clientData = [];
        $keys = [
            "Company Name" => "name",
            "Address" => "adress",
            "City" => "city",
            "Postal Code" => "postalCode",
            "Country" => "country",
            "Contact Person" => "contactPerson",
            "Contact Email" => "contactEmail",
            "Contact Phone" => "contactPhone",
        ];

        // Extract text from the document
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        // Convert extracted text into an array of lines
        $lines = array_map('trim', explode("\n", trim($text)));

        // Loop through lines to extract key-value pairs
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2); // Split by the first occurrence of ":"
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                if (isset($keys[$key])) {
                    $clientData[$keys[$key]] = $value;
                }
            }
        }

        // Debugging: Log extracted data (optional)
        if (empty($clientData)) {
            $this->addFlash('error', 'No data extracted from document.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Validate extracted data
        if (empty($clientData['name']) || empty($clientData['contactEmail'])) {
            $this->addFlash('error', 'Invalid document format or missing required fields.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Create and save the Client entity
        $client = new Client();
        $client->setName($clientData['name']);
        $client->setAdress($clientData['adress'] ?? '');
        $client->setCity($clientData['city'] ?? '');
        $client->setPostalCode($clientData['postalCode'] ?? '');
        $client->setCountry($clientData['country'] ?? '');
        $client->setContactPerson($clientData['contactPerson'] ?? '');
        $client->setContactEmail($clientData['contactEmail']);
        $client->setContactPhone($clientData['contactPhone'] ?? '');

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $this->addFlash('success', 'Client uploaded successfully.');
        return $this->redirectToRoute('admin_upload_index');
    }

    #[Route('/admin/upload/projects', name: 'admin_upload_projects', methods: ['POST'])]
    public function uploadProjects(Request $request): Response
    {
        $uploadedFile = $request->files->get('project_docx');

        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'No file uploaded.');
            return $this->redirectToRoute('admin_upload_index');
        }

        if ($uploadedFile->getClientOriginalExtension() !== 'docx') {
            $this->addFlash('error', 'Invalid file type. Please upload a .docx file.');
            return $this->redirectToRoute('admin_upload_index');
        }

        $filePath = $uploadedFile->getPathname();
        $phpWord = IOFactory::load($filePath);

        $projectData = [];
        $keys = [
            "Project Name" => "name",
            "Client Name" => "clientName",
            "Description" => "description",
            "Priority" => "priority",
            "Deadline" => "deadline",
            "Estimated Budget" => "estimatedBudget",
            "Estimated Time (hours)" => "estimatedTimeHours",
            "Estimated Time (minutes)" => "estimatedTimeMinutes",
            "Archived" => "archived",
        ];

        // Extract text from the document
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        // Convert extracted text into an array of lines
        $lines = array_map('trim', explode("\n", trim($text)));

        // Loop through lines to extract key-value pairs
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2); // Split by the first occurrence of ":"
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                if (isset($keys[$key])) {
                    $projectData[$keys[$key]] = $value;
                }
            }
        }

        // Debugging: Log extracted data (optional)
        if (empty($projectData)) {
            $this->addFlash('error', 'No data extracted from document.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Validate extracted data
        if (empty($projectData['name']) || empty($projectData['clientName'])) {
            $this->addFlash('error', 'Invalid document format or missing required fields.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Find the associated Client entity
        $client = $this->entityManager->getRepository(Client::class)
            ->findOneBy(['name' => $projectData['clientName']]);

        if (!$client) {
            $this->addFlash('error', 'Client "' . $projectData['clientName'] . '" not found.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Convert values to the correct types
        $priorityMap = [
            "Low" => Priority::LOW,
            "Medium" => Priority::MEDIUM,
            "High" => Priority::HIGH
        ];
        $priority = $priorityMap[$projectData['priority']] ?? Priority::MEDIUM;

        $deadline = \DateTime::createFromFormat('Y-m-d', $projectData['deadline']) ?: new \DateTime();
        $estimatedBudget = floatval($projectData['estimatedBudget']);
        $estimatedTime = ((int) $projectData['estimatedTimeHours'] * 60) + (int) $projectData['estimatedTimeMinutes'];
        $archived = strtolower($projectData['archived']) === "yes";

        // Create and save the Project entity
        $project = new Project();
        $project->setName($projectData['name']);
        $project->setDescription($projectData['description']);
        $project->setClient($client);
        $project->setPriority($priority);
        $project->setDeadline($deadline);
        $project->setEstimatedBudget($estimatedBudget);
        $project->setEstimatedTime($estimatedTime);
        $project->setArchived($archived);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $this->addFlash('success', 'Project uploaded successfully.');
        return $this->redirectToRoute('admin_upload_index');
    }

    #[Route('/admin/upload/todos', name: 'admin_upload_todos', methods: ['POST'])]
    public function uploadTodos(Request $request): Response
    {
        $uploadedFile = $request->files->get('todo_docx');

        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'No file uploaded.');
            return $this->redirectToRoute('admin_upload_index');
        }

        if ($uploadedFile->getClientOriginalExtension() !== 'docx') {
            $this->addFlash('error', 'Invalid file type. Please upload a .docx file.');
            return $this->redirectToRoute('admin_upload_index');
        }

        $filePath = $uploadedFile->getPathname();
        $phpWord = IOFactory::load($filePath);

        $todoData = [];
        $keys = [
            "Task Name" => "name",
            "Project Name" => "projectName",
            "Start Date" => "dateStart",
            "End Date" => "dateEnd",
        ];

        // Extract text from the document
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        // Convert extracted text into an array of lines
        $lines = array_map('trim', explode("\n", trim($text)));

        // Loop through lines to extract key-value pairs
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2); // Split by the first occurrence of ":"
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                if (isset($keys[$key])) {
                    $todoData[$keys[$key]] = $value;
                }
            }
        }

        // Debugging: Log extracted data (optional)
        if (empty($todoData)) {
            $this->addFlash('error', 'No data extracted from document.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Validate extracted data
        if (empty($todoData['name']) || empty($todoData['projectName'])) {
            $this->addFlash('error', 'Invalid document format or missing required fields.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Find the associated Project entity
        $project = $this->entityManager->getRepository(Project::class)
            ->findOneBy(['name' => $todoData['projectName']]);

        if (!$project) {
            $this->addFlash('error', 'Project "' . $todoData['projectName'] . '" not found.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Convert dates
        $dateStart = \DateTime::createFromFormat('Y-m-d', $todoData['dateStart']) ?: new \DateTime();
        $dateEnd = \DateTime::createFromFormat('Y-m-d', $todoData['dateEnd']) ?: new \DateTime();

        // Create and save the Todo entity
        $todo = new Todo();
        $todo->setName($todoData['name']);
        $todo->setDateStart($dateStart);
        $todo->setDateEnd($dateEnd);
        $todo->setProject($project);

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        $this->addFlash('success', 'Todo uploaded successfully.');
        return $this->redirectToRoute('admin_upload_index');
    }


}
