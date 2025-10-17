<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

#[Route('/user')]
#[IsGranted('IS_AUTHENTICATED_FULLY')] // Protegemos todo el controlador
class UserController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        // Un ADMIN solo puede editar usuarios de su propia empresa.
        // Un SUPER_ADMIN puede editar a cualquiera.
        $this->denyAccessUnlessGranted('EDIT', $user);

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Usuario actualizado correctamente.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'edit_form' => $form->createView(),
        ]);
    }
    #[Route('/', name: 'app_user_index')]
    public function index(UserRepository $userRepository, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        // --- Lógica para el formulario de subida de ficheros ---
        $form = $this->createFormBuilder()
            ->add('user_file', FileType::class, [
                'label' => 'Fichero CSV o Excel de Usuarios',
                'mapped' => false, // No está asociado a ninguna entidad
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '5120k', // 5 MB
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain', // Mime type para CSV en algunos sistemas
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                        'mimeTypesMessage' => 'Por favor, sube un fichero CSV o Excel válido.',
                    ])
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('user_file')->getData();
            if ($file) {
                $this->processUserFile($file, $em, $passwordHasher);
                $this->addFlash('success', '¡Fichero de usuarios procesado correctamente!');
            }
            return $this->redirectToRoute('app_user_index');
        }

        // --- Lógica para mostrar los usuarios ---
        $users = [];
        // Si es SUPER ADMIN, puede ver a todos los usuarios de todas las empresas
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            $users = $userRepository->findAll();
        } 
        // Si es ADMIN (pero no SUPER ADMIN), solo puede ver los de su empresa
        elseif ($this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            $users = $userRepository->findBy(['company' => $currentUser->getCompany()]);
        }
        // Un usuario normal no debería poder acceder a esta página
        else {
            // Lanza una excepción de acceso denegado
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'upload_form' => $form->createView(),
        ]);
    }

    private function processUserFile($file, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): void
    {
        /** @var \App\Entity\User $adminUser */
        $adminUser = $this->getUser();
        
        // Usamos PhpSpreadsheet para leer el fichero
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Saltamos la primera fila (cabeceras)
        array_shift($rows);

        foreach ($rows as $row) {
            // Asumimos el orden: Nombre, Apellidos, Email, DNI, Puesto
            $nombre = $row[0];
            $apellidos = $row[1];
            $email = $row[2];
            $dni = $row[3] ?? null;
            $puesto = $row[4] ?? null;

            // Evitamos crear usuarios duplicados por email
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                continue; // O podrías actualizarlo si lo prefieres
            }

            $user = new User();
            $user->setNombre($nombre);
            $user->setApellidos($apellidos);
            $user->setEmail($email);
            $user->setDni($dni);
            $user->setPuesto($puesto);
            $user->setCalificacion(0); // Calificación inicial
            $user->setCompany($adminUser->getCompany());

            // Generamos una contraseña aleatoria y segura para el nuevo usuario
            $randomPassword = bin2hex(random_bytes(10));
            $user->setPassword(
                $passwordHasher->hashPassword($user, $randomPassword)
            );

            $em->persist($user);
        }
        $em->flush();
    }

    #[Route('/profile', name: 'app_user_profile')]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Obtenemos el usuario actualmente logueado
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Creamos el formulario, pasándole el objeto $user para que se rellene
        // con los datos actuales del usuario.
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        // Comprobamos si el formulario se ha enviado y es válido
        if ($form->isSubmitted() && $form->isValid()) {
            // No hace falta hacer $entityManager->persist($user).
            // Doctrine ya está "vigilando" el objeto $user,
            // así que solo necesitamos guardar los cambios.
            $entityManager->flush();

            // Añadimos un mensaje de éxito para el usuario
            $this->addFlash('success', '¡Tu perfil ha sido actualizado correctamente!');

            // Redirigimos a la misma página para evitar reenvíos del formulario
            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('user/profile.html.twig', [
            'profile_form' => $form->createView(),
        ]);
    }
}