<?php

namespace App\Controller\Admin;

use App\Entity\Todo;
use App\Form\TodoType;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TodosController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/todos', name: 'admin_todos')]
    public function index(Request $request): Response
    {
        $searchTerm = $request->query->get('search');
        // Build query for search & pagination
        $queryBuilder = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t')
            ->leftJoin('t.project', 'p');

        if ($searchTerm) {
            $queryBuilder->andWhere('t.name LIKE :search OR p.name LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        // Pagination setup
        $pagerfanta = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));

        $todoDataArray = [];
        foreach ($pagerfanta->getCurrentPageResults() as $todo) {
            $todoDataArray[] = [
                'id' => $todo->getId(),
                'name' => $todo->getName(),
                'dateStart' => $todo->getDateStart(),
                'dateEnd' => $todo->getDateEnd(),
                'project' => $todo->getProject() ? $todo->getProject()->getName() : 'Unassigned',
            ];
        }

        return $this->render('admin/todos/index.html.twig', [
            'controller_name' => 'TodosController',
            'todoDataArray' => $todoDataArray,
            'pager' => $pagerfanta
        ]);
    }

    #[Route('/admin/todo/new', name: 'admin_todo_new')]
    public function new(Request $request): Response
    {
        $todo = new Todo();
        $form = $this->createForm(TodoType::class, $todo);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($todo);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_todos');
        }

        return $this->render('admin/todos/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/admin/todo/update/{id}', name: 'admin_todo_update', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $todo = $this->entityManager->getRepository(Todo::class)->find($id);

        if (!$todo) {
            throw $this->createNotFoundException('Todo not found');
        }

        // Update fields based on form submission
        $todo->setName($request->request->get('name'));
        $todo->setDateStart(new \DateTime($request->request->get('dateStart')));
        $todo->setDateEnd(new \DateTime($request->request->get('dateEnd')));

        $this->entityManager->flush();

        return $this->redirectToRoute('admin_todos');
    }
}
