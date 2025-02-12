<?php
/**
 * Created by PhpStorm.
 * User: devel
 * Date: 3/17/2019
 * Time: 11:55 AM
 */
namespace App\Controller;

use App\Entity\User;
use App\Entity\UserType;
use App\Entity\Shows;
use App\Entity\Order;

use mysql_xdevapi\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Session\Session;

use App\Form\Type\UserType as UserTypeForm;
use Symfony\Component\Validator\Constraints\File;

class UserController extends AbstractController
{
    private function getCurrentUser(){
        $currentUser = $this->get('session')->get('user');
        return $currentUser;
    }

   /**
     * @Route("/chat/{nickname}")
     * @param $nickname
     * @param $popup
     * @return mixed
     */
    public function chat($nickname)
    {
        $popup=true;
        $repository = $this->getDoctrine()->getRepository(User::class);
        $streamingUser = $repository->findOneBy(['nickname'=>$nickname]);

        if(!empty($streamingUser)){
            $user = $this->get('session')->get('user');
            if(!empty($user)){
                $nickname = $user->getNickname();
            }else{
                $nickname = 'Guest'.rnd(5);
            }

            return $this->render('user/chat.html.twig', [
                'streamID'=>$streamingUser->getStreamingKey(),
                'streamer'=>$streamingUser->getNickname(),
                'nickname'=>$nickname,
                'section' => 'live',
                'currentUser' => $this->getCurrentUser(),
                'killHeader' => $popup?true:false,
                'killFooter' => $popup?true:false,
            ]);
        }else{
            return $this->render('stream/notFound.html.twig', [
                'section' => 'live',
                'currentUser' => $this->getCurrentUser(),
            ]);
        }
    }

    /**
     * @Route("/profile/{nickname}/photo")
     * @return mixed
     */
    public function profilePhoto(Request $request, $nickname)
    {
        $repository = $this->getDoctrine()->getRepository(User::class);
        $streamingUser = $repository->findOneBy(['nickname'=>$nickname]);

        $currentUser = $repository->findBy(['nickname' => $this->getCurrentUser()->getNickname()]);

        if($streamingUser->id != $currentUser->id){
            throw new Exception("Correct user");
        }else{
            throw new Exception("Not correct user");
        }

        /*
        $form = $this->createFormBuilder(null, ['csrf_protection' => false])
            ->add('profileImage', TextType::class, ['label' => 'Profile Image', 'attr' => ['id' => 'uploadImage']])
            ->getForm();


        $form->handleRequest($request);
        */

        return $this->render('user/profileImage.html.twig', [
            'section'=>'users',
            'currentUser'=>$this->getCurrentUser(),
        ]);
    }

    /**
     * @Route("/category/{category}")
     * @param $category
     * @return mixed
     */
    public function category($category)
    {
        $repository = $this->getDoctrine()->getRepository(User::class);
        $streamingUsers = $repository->findBy(['category' => $category]);
        //$streamingServer = "";

        return $this->render('user/category.html.twig', [
            'streamingUsers'=>$streamingUsers,
            'category'=>$category,
            'section'=>$category,
            'currentUser'=>$this->getCurrentUser(),
        ]);
    }

    /**
     * @Route("/signup")
     */
    public function new(Request $request)
    {
        $section = 'signup';

        $user = new User();
        $form = $this->createFormBuilder(null, ['csrf_protection' => false])
            ->add('email', TextType::class, ['label' => 'Email Address'])
            ->add('password', PasswordType::class, ['label' => 'Password'])
            ->add('nickname', TextType::class, ['label' => 'Display Name'])
            ->add('firstName', TextType::class, ['label' => 'First Name'])
            ->add('lastName', TextType::class, ['label' => 'Last Name'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $userData = $form->getData();
            $repository = $this->getDoctrine()->getRepository(User::class);
            $emailCheck = $repository->findOneBy(['email'=>$userData['email']]);
            $nicknameCheck = $repository->findOneBy(['nickname'=>$userData['nickname']]);
            $process = true;

            if (!empty($emailCheck)) {
                $form->addError(new \Symfony\Component\Form\FormError("Email address already exists"));
                $process = false;
            }

            if (!empty($nicknameCheck)) {
                $form->addError(new \Symfony\Component\Form\FormError("Nickname already exists"));
                $process = false;
            }

            if ($process) {
                $entityManager = $this->getDoctrine()->getManager();

                $user->setEmail($userData['email']);
                $user->setPassword(password_hash($userData['password'], PASSWORD_DEFAULT));
                $user->setNickname($userData['nickname']);
                $user->setFirstName($userData['firstName']);
                $user->setLastName($userData['lastName']);

                $userType = $this->getDoctrine()->getRepository(UserType::class)->findOneBy(['type' => 'viewer']);
                $user->addUserType($userType);

                $entityManager->persist($user);
                $entityManager->flush();

                return $this->redirect('/signup/thank-you');
            }
        }

        return $this->render('signup/new.html.twig', [
            'form' => $form->createView(),
            'section' => $section,
            'currentUser'=>$this->getCurrentUser(),
        ]);
    }

    /**
     * @Route("/signup/thank-you")
     */
    public function thanks()
    {
        return $this->render('signup/thanks.html.twig', [
            'streamID' => '422686729988513539127548',
            'section'=>'signup',
            'currentUser'=>$this->getCurrentUser(),
        ]);
    }

    /**
     * @Route("/login")
     */
    public function login(Request $request)
    {
        if ($this->getCurrentUser()) {
            return $this->redirect('/');
        }

        $form = $this->createFormBuilder(null, ['csrf_protection' => false])
            ->add('email', TextType::class, ['label' => 'Email Address'])
            ->add('password', PasswordType::class, ['label' => 'Password'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $userData = $form->getData();

            $repository = $this->getDoctrine()->getRepository(User::class);
            $user = $repository->findOneBy(['email' => $userData['email']]);

            if(!empty($user) && password_verify($userData['password'], $user->getPassword())) {
                $this->get('session')->set('user', $user);

                if ($this->get('session')->get('purchaseTicketRedirectUrl')) {
                    return $this->redirect(base64_decode($this->get('session')->get('purchaseTicketRedirectUrl')));
                }

                if ($this->get('session')->get('viewStreamRedirectUrl')) {
                    return $this->redirect(base64_decode($this->get('session')->get('viewStreamRedirectUrl')));
                }

                return $this->redirect('/profile/'.$user->getNickname());
            }else{
                $form->addError(new \Symfony\Component\Form\FormError("Log-in Failed. Please try again."));
            }
        }

        return $this->render('login/login.html.twig', [
            'form' => $form->createView(),
            'section'=>'login',
            'currentUser'=>$this->getCurrentUser(),
        ]);
    }


    /**
     * @Route("/logout")
     */
    public function logout()
    {
        $this->get('session')->set('user', null);
        return $this->redirect('/');
    }

    /**
     * @Route("/profile/{nickname}")
     * @param $nickname
     * @return mixed
     */
    public function viewProfile($nickname)
    {
        $this->get('session')->remove('purchaseTicketRedirectUrl');

        //  venues
        $repository = $this->getDoctrine()->getRepository(User::class);
        $user = $repository->findOneBy(['nickname'=>$nickname]);

        $currentUser = $this->getCurrentUser();
        $currentNickname = empty($currentUser)?null:$currentUser->getNickname();

        $canEdit = false;
        if ($user && !empty($currentUser)) {
            $canEdit = ($user->getId() == $this->get('session')->get('user')->getId()) ? 1 : 0;
        }

        //  venues (shows)
        $showsRepo = $this->getDoctrine()->getRepository(Shows::class);
        $showsData = $showsRepo->findBy(['user' => $user], ['start' => 'DESC']);
        //echo "<pre>"; print_r($showsData);exit;
        if (!empty($currentUser) && count($showsData)) {
            foreach ($showsData as $shows) {
                $showsOrders = $shows->getOrders();
                foreach ($showsOrders as $showsOrder) {
                    if ($showsOrder->getUser()->getId() == $currentUser->getId()) {
                        $shows->setOrdered(true);
                    }
                }
            }
        }

        //  viewer (orders)
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);
        $orders = $orderRepo->findBy(['user' => $currentUser]);

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'showsData' => $showsData,
            'orders' => $orders,
            'nickname' =>$nickname,
            'currentUser' =>$currentUser,
            'currentTimestamp' => time(),
            'section'=>'users',
            'currentNickname'=> ($currentUser) ? $currentUser->getNickName() : '',
            'canEdit' => $canEdit,
            'isUserLoggedIn' => $currentUser,
        ]);
    }

    /**
     * @Route("/profile/edit/{id}")
     * @return mixed
     */
    public function editProfile(Request $request)
    {
        $currentUser = $this->get('session')->get('user');
        $userId = $currentUser->getId();
        
        $repository = $this->getDoctrine()->getRepository(User::class);
        $user = $repository->findOneBy(['id' => $userId]);

        $form = $this->createForm(UserTypeForm::class, $user);
        $form->handleRequest($request);

        $validateFields = array('email' => '', 'nickname' => '', 'firstName' => '', 'lastName' => '', 'profileImage' => '');
        if ($form->isSubmitted()) {
            $profileImage = $form->get('profileImage')->getData();

            $nickname = $form->get('nickname')->getData();
            $uniqueUsers = $repository->findBy(['nickname' => trim($nickname)]);
            $uniqueError = false;
            foreach ($uniqueUsers as $uniqueUser) {
                if ($uniqueUser->getId() != $userId) {
                    $uniqueError = true;
                }
            }

            if ($form->isValid() && !$uniqueError) {
                $userEmail = $user->getEmail();
                $user = $form->getData();
                $user->setEmail($userEmail);
                //  upload zprofile image
                if ($profileImage) {
                    $profileImageName = uniqid().'.'.$profileImage->guessExtension();
                    try {
                        $profileImage->move(
                            $this->getParameter('user_profile_path'),
                            $profileImageName
                        );

                        if ($user->getProfileImage() && file_exists($this->getParameter('user_profile_path').$user->getProfileImage())) {
                            unlink($this->getParameter('user_profile_path').$user->getProfileImage());
                        }                    
                    } catch (FileException $e) {
                    }

                    $user->setProfileImage($profileImageName);
                }
                //  end of upload zprofile image

                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();

                $this->addFlash('user_profile_edited_success', 'User profile updated successfully');
                return $this->redirect('/profile/'.$user->getNickname());

            } else {

                foreach ($validateFields as $key => $checkError) {
                    if ($key == 'nickname' && $uniqueError) {
                        $validateFields[$key] = 'Nickname not available';
                    } else {
                        $errors = $form[$key]->getErrors();
                        foreach ($errors as $k => $error) {
                            $validateFields[$key] = $error->getMessage();
                        }
                    }
                }

            }
        }

        return $this->render('user/editProfile.html.twig', [
            'form' => $form->createView(),
            'currentUser'=>$this->getCurrentUser(),
            'errors' => $form->getErrors(),
            'validateFields' => $validateFields
        ]);
    }

    /**
     * @Route("/list/profiles/")
     */
    public function listProfiles()
    {
        $repository = $this->getDoctrine()->getRepository(User::class);
        $users = $repository->findAll();

        return $this->render('user/list.html.twig', [
            'users' => $users,
            'section' => 'users',
            'currentUser'=>$this->getCurrentUser(),
        ]);
    }

}
