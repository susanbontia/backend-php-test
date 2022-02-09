<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

CONST PAGINATION_LIMIT = 10;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});

$app->match('/login', function (Request $request) use ($app) {

        $username = $request->get('username');
        $password = $request->get('password');

        if ($username) {

            $entityManager = $app['orm.em'];
            $user = $entityManager->getRepository('\App\Entity\User')
                ->findBy([
                    'username' => $username,
                    'password' => $password
                ]);

            if ($user) {
                $app['session']->set('user', $user[0]);
                return $app->redirect('/todo');

            } else {
                $app['session']->getFlashBag()->add('warning', 'Invalid username or password.');
            }

        }

        return $app['twig']->render('login.html', array());
    });

$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});

$app->get('/todo/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $entityManager = $app['orm.em'];

    if ($id){

        return $app['twig']->render('todo.html', [
            'todo' => $entityManager->find('\App\Entity\Todo', $id),
        ]);
    } else {

        $limit = PAGINATION_LIMIT;
        $offset = isset($_GET['page']) ? ($_GET['page'] - 1) * $limit: 0;

        $results = $entityManager
            ->getRepository('\App\Entity\Todo')
            ->findBy(
                ['userId' => $user->getId()]
            );

        return $app['twig']->render('todos.html', [
            'todos' => $entityManager
                ->getRepository('\App\Entity\Todo')
                ->findBy(
                    ['userId' => $user->getId()],
                    null,
                    $limit,
                    $offset
                ),
            'total_pages' => ceil(count($results) / $limit),
        ]);
    }
})
->value('id', null);

$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $description = $request->get('description');

        if(empty($description) && $description === '') {
            $app['session']->getFlashBag()->add('warning', 'Please enter description to add a to do.');
        } else {

            $entityManager = $app['orm.em'];
            $todo = new \App\Entity\Todo();
            $todo->setDescription($description)
                ->setUser_Id($user->getId())
                ->setStatus('Pending');
            $entityManager->persist($todo);
            $entityManager->flush();
            $app['session']->getFlashBag()->add('notice', 'Todo \'' . $description . '\' is successfully added.');
        }

    return $app->redirect('/todo');
});

$app->match('/todo/edit/{id}', function ($id) use ($app) {

    $entityManager = $app['orm.em'];
    $todo = new \App\Entity\Todo();
    $todo = $entityManager->find('\App\Entity\Todo', $id);
    $todo->setStatus('Completed');
    $entityManager->persist($todo);
    $entityManager->flush();
    $app['session']->getFlashBag()->add('notice', 'Todo \'[' . $id . ']: ' .$todo->getDescription() . '\' is successfully marked as completed.');
    return $app->redirect('/todo');
});

$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $entityManager = $app['orm.em'];
    $todo = new \App\Entity\Todo();
    $todo = $entityManager->find('\App\Entity\Todo', $id);
    $entityManager->remove($todo);
    $entityManager->flush();
    $app['session']->getFlashBag()->add('notice', 'Todo \'[' . $id . ']: ' .$todo->getDescription() . '\' is successfully deleted.');
    return $app->redirect('/todo');
});

$app->get('/todo/{id}/json', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $entityManager = $app['orm.em'];
    $todo = $entityManager->find('\App\Entity\Todo', $id);

    return $app['twig']->render('todo.html', [
        'todo' => $todo,
        'json' => $todo->serializer()
    ]);


})
    ->value('id', null);