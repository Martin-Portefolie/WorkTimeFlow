<?php

namespace App\Controller\profile;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/profile/user', name: 'app_user')]

   public function index(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
   {
       $user = $this->getUser();
       if (!$user) {
           return $this->redirectToRoute('app_login');
       }

       // Create the form manually
       $form = $this->createFormBuilder($user)
           ->add('username', TextType::class, [
               'label' => 'Username',
               'attr' => ['class' => 'form-control'],
           ])
           ->add('email', EmailType::class, [
               'label' => 'Email',
               'attr' => ['class' => 'form-control'],
           ])
           ->add('plainPassword', PasswordType::class, [
               'label' => 'New Password (optional)',
               'mapped' => false,
               'required' => false,
               'attr' => ['class' => 'form-control'],
           ])
           ->add('save', SubmitType::class, [
               'label' => 'Update Profile',
               'attr' => ['class' => 'btn btn-primary'],
           ])
           ->getForm();

       $form->handleRequest($request);

       if ($form->isSubmitted() && $form->isValid()) {
           $data = $form->getData();
           $plainPassword = $form->get('plainPassword')->getData();

           if ($plainPassword) {
               $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
               $user->setPassword($hashedPassword);
           }

           $entityManager->flush();
           $this->addFlash('success', 'Profile updated successfully!');

           return $this->redirectToRoute('app_user');
       }

       return $this->render('profile/user/index.html.twig', [
           'form' => $form->createView(),
       ]);
   }
}
