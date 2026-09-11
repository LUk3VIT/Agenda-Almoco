<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';

$router = new \Bramus\Router\Router();

$router->setBasePath('/Agenda-Almoco/api');

// Rotas do Módulo de Agenda
$router->get('/horarios', 'App\Controllers\AgendaController@listar');
$router->post('/agendar', 'App\Controllers\AgendaController@agendar');

// Rotas Administrativas de Agenda (Exigem permissao_user === 'admin')
$router->post('/limpar', 'App\Controllers\AgendaController@limpar');
$router->get('/exportar', 'App\Controllers\AgendaController@exportar');
$router->post('/configuracoes/bloquear', 'App\Controllers\AgendaController@toggleBloqueio');
$router->post('/horarios/criar', 'App\Controllers\AgendaController@cadastrarHorario');
$router->post('/horarios/atualizar', 'App\Controllers\AgendaController@atualizarHorario');
$router->post('/horarios/excluir', 'App\Controllers\AgendaController@excluirHorario');

// Rotas de Autenticação, 2FA e Gestão de Usuários (Exigem privilégios de Admin para alteração/criação)
$router->post('/login', 'App\Controllers\UserController@login');
$router->get('/checar-sessao', 'App\Controllers\UserController@checarSessao');
$router->post('/criar-usuario', 'App\Controllers\UserController@criar');
$router->get('/usuarios', 'App\Controllers\UserController@listar');
$router->post('/usuarios/permissao', 'App\Controllers\UserController@atualizarPermissao');
$router->post('/usuarios/atualizar', 'App\Controllers\UserController@atualizar');
$router->post('/usuarios/excluir', 'App\Controllers\UserController@excluir');

$router->post('/validar-2fa', 'App\Controllers\UserController@validar2FA');
$router->post('/confirmar-setup-2fa', 'App\Controllers\UserController@confirmarSetup2FA');
$router->get('/setup-2fa', 'App\Controllers\UserController@setup2FA');


$router->set404(function() {
    http_response_code(404);
    echo json_encode(["status" => "erro", "mensagem" => "Rota não encontrada."]);
});

$router->run();

?>