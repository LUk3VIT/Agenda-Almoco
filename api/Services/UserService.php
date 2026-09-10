<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Security\PasswordHasher;
use App\Security\Encrypter;
use App\Security\JwtManager;
use App\Security\TwoFactorAuth;
use PDOException;

class UserService {
    private UserRepository $userRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
    }

    /**
     * Tenta realizar o login. Se o 2FA estiver ativado, solicita o código de 6 dígitos.
     * Caso contrário ou após validação do 2FA, emite o JWT.
     */
    public function loginUser(string $nomeUser, string $senha): array {
        if (empty(trim($nomeUser)) || empty(trim($senha))) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Nome de usuário e senha não podem ser vazios.'
            ];
        }

        try {
            $user = $this->userRepository->buscarPorNome($nomeUser);

            if (!$user || !PasswordHasher::verifyPassword($senha, $user['senha_user'])) {
                return [
                    'status' => false,
                    'codigo' => 401,
                    'mensagem' => 'Usuário ou senha incorretos.'
                ];
            }

            // Se a conta já possui 2FA ativado (segundo login em diante)
            if (!empty($user['is_2fa_enabled']) && !empty($user['secret_2fa'])) {
                return [
                    'status' => true,
                    'requer_2fa' => true,
                    'id_user' => (int)$user['id_user'],
                    'codigo' => 200,
                    'mensagem' => 'Por favor, informe o código de 6 dígitos do Microsoft Authenticator.'
                ];
            }

            // SE O 2FA AINDA NÃO ESTÁ ATIVADO (PRIMEIRO LOGIN MANDATÓRIO)
            // Gera chave temporária em memória, gera QR Code URL e envia em um token temporário sem salvar no banco de dados!
            $tempSecret = TwoFactorAuth::generateSecret();
            $qrCodeUrl = TwoFactorAuth::getQrCodeUrl($user['nome_user'], $tempSecret);
            $otpauthUri = TwoFactorAuth::getOtpauthUri($user['nome_user'], $tempSecret);

            $tempTokenPayload = [
                'id_user' => (int)$user['id_user'],
                'nome_user' => $user['nome_user'],
                'temp_secret' => $tempSecret,
                'type' => 'setup_2fa'
            ];
            $temp2faToken = JwtManager::generateToken($tempTokenPayload);

            return [
                'status' => true,
                'requer_setup_2fa' => true,
                'temp_2fa_token' => $temp2faToken,
                'qr_code_url' => $qrCodeUrl,
                'otpauth_uri' => $otpauthUri,
                'codigo' => 200,
                'mensagem' => 'Por razões de segurança, escaneie o QR Code no Microsoft Authenticator para ativar sua conta.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno de banco de dados ao realizar login.'
            ];
        }
    }

    /**
     * Valida os 6 dígitos digitados no onboarding e ativa o 2FA salvando no banco de dados MySQL.
     */
    public function confirmarSetup2FA(string $tempToken, string $codigo6Digitos): array {
        if (empty(trim($tempToken)) || empty(trim($codigo6Digitos))) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Token de setup e código de 6 dígitos são obrigatórios.'
            ];
        }

        try {
            $payload = JwtManager::validateToken($tempToken);

            if (!$payload || !isset($payload->user->id_user) || !isset($payload->user->temp_secret) || ($payload->user->type ?? '') !== 'setup_2fa') {
                return [
                    'status' => false,
                    'codigo' => 401,
                    'mensagem' => 'Sessão de configuração de 2FA inválida ou expirada. Faça login novamente.'
                ];
            }

            $idUser = (int)$payload->user->id_user;
            $tempSecret = (string)$payload->user->temp_secret;

            // Valida o código digitado contra a chave secreta temporária
            $valido = TwoFactorAuth::verifyCode($tempSecret, $codigo6Digitos);

            if (!$valido) {
                return [
                    'status' => false,
                    'codigo' => 401,
                    'mensagem' => 'Código de 6 dígitos incorreto. Tente novamente após ler o QR Code no Microsoft Authenticator.'
                ];
            }

            // APENAS APÓS CONFIRMAÇÃO DOS 6 DÍGITOS: Criptografa e grava no MySQL!
            $secretCriptografado = Encrypter::encrypt($tempSecret);
            $this->userRepository->salvarSecret2FA($idUser, $secretCriptografado);
            $this->userRepository->ativar2FA($idUser);

            $user = $this->userRepository->buscarPorId($idUser);
            $tokenDefinitivo = JwtManager::generateToken($user);

            return [
                'status' => true,
                'token' => $tokenDefinitivo,
                'codigo' => 200,
                'mensagem' => 'Autenticação de 2 fatores ativada com sucesso!',
                'usuario' => [
                    'id' => (int)$user['id_user'],
                    'nome' => $user['nome_user'],
                    'permissao' => $user['permissao_user'] ?? 'colaborador'
                ]
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro ao ativar autenticação 2FA no banco de dados.'
            ];
        }
    }

    /**
     * Valida o código 2FA de 6 dígitos e emite o JWT final após sucesso.
     */
    public function validar2FA(int $idUser, string $codigo2FA): array {
        if ($idUser <= 0 || empty(trim($codigo2FA))) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Código de 6 dígitos obrigatório.'
            ];
        }

        try {
            $user = $this->userRepository->buscarPorId($idUser);

            if (!$user || empty($user['secret_2fa'])) {
                return [
                    'status' => false,
                    'codigo' => 400,
                    'mensagem' => 'Configuração de 2FA não encontrada para este usuário.'
                ];
            }

            // Descriptografa o secret 2FA temporariamente com AES-256
            $secretReal = Encrypter::decrypt($user['secret_2fa']);

            // Valida os 6 dígitos contra o algoritmo TOTP (RFC 6238)
            $valido = TwoFactorAuth::verifyCode($secretReal, $codigo2FA);

            if (!$valido) {
                return [
                    'status' => false,
                    'codigo' => 401,
                    'mensagem' => 'Código 2FA incorreto ou expirado. Verifique o Microsoft Authenticator.'
                ];
            }

            // Emite o JWT assinado
            $token = JwtManager::generateToken($user);

            return [
                'status' => true,
                'token' => $token,
                'codigo' => 200,
                'mensagem' => 'Autenticação 2FA realizada com sucesso.',
                'usuario' => [
                    'id' => (int)$user['id_user'],
                    'nome' => $user['nome_user'],
                    'permissao' => $user['permissao_user'] ?? 'colaborador'
                ]
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro ao validar código de dois fatores.'
            ];
        }
    }

    /**
     * Cria um novo usuário com senha hash BCrypt.
     */
    public function criarUser(string $nomeUser, string $senhaUser, string $permissao = 'colaborador'): array {
        if (empty(trim($nomeUser)) || empty(trim($senhaUser))) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Nome de usuário e senha não podem ser vazios.'
            ];
        }

        try {
            $userExiste = $this->userRepository->buscarPorNome($nomeUser);
            if ($userExiste) {
                return [
                    'status' => false,
                    'codigo' => 409,
                    'mensagem' => 'Usuário já existe no sistema.'
                ];
            }

            // Criptografa a senha com BCrypt
            $senhaHash = PasswordHasher::hashPassword($senhaUser);

            $criado = $this->userRepository->criarUser($nomeUser, $senhaHash, $permissao);

            if (!$criado) {
                return [
                    'status' => false,
                    'codigo' => 500,
                    'mensagem' => 'Erro ao criar usuário.'
                ];
            }

            return [
                'status' => true,
                'codigo' => 201,
                'mensagem' => 'Usuário criado com sucesso com hash BCrypt.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno de banco de dados ao criar usuário.'
            ];
        }
    }

    public function listarUsuarios(): array {
        try {
            $usuarios = $this->userRepository->listarTodosUsuarios();
            return [
                'status' => true,
                'codigo' => 200,
                'dados' => $usuarios
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro ao listar usuários.'
            ];
        }
    }

    public function alterarPermissaoUser(int $idUserTarget, string $novaPermissao, int $usuarioLogadoId): array {
        if ($idUserTarget === $usuarioLogadoId) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Você não pode alterar a sua própria permissão para evitar a perda acidental de acesso administrativo.'
            ];
        }

        $permissaoLimpa = strtolower(trim($novaPermissao));
        if (!in_array($permissaoLimpa, ['colaborador', 'admin'])) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Permissão inválida. Escolha entre colaborador ou admin.'
            ];
        }

        try {
            $atualizado = $this->userRepository->atualizarPermissao($idUserTarget, $permissaoLimpa);
            return [
                'status' => $atualizado,
                'codigo' => $atualizado ? 200 : 500,
                'mensagem' => $atualizado ? 'Permissão do usuário atualizada com sucesso.' : 'Erro ao atualizar permissão.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno ao alterar permissão do usuário.'
            ];
        }
    }

    public function atualizarUser(int $idUserTarget, string $nomeUser, ?string $senhaUser, string $permissao, int $usuarioLogadoId): array {
        if ($idUserTarget === $usuarioLogadoId && strtolower(trim($permissao)) !== 'admin') {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Você não pode alterar o seu próprio cargo para colaborador.'
            ];
        }

        if (empty(trim($nomeUser))) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'O nome do usuário é obrigatório.'
            ];
        }

        try {
            $senhaHash = !empty($senhaUser) ? PasswordHasher::hashPassword($senhaUser) : null;
            $atualizado = $this->userRepository->atualizarUser($idUserTarget, trim($nomeUser), $senhaHash, strtolower(trim($permissao)));

            return [
                'status' => $atualizado,
                'codigo' => $atualizado ? 200 : 500,
                'mensagem' => $atualizado ? 'Usuário atualizado com sucesso.' : 'Erro ao atualizar usuário.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro ao atualizar dados do usuário no banco de dados.'
            ];
        }
    }

    public function excluirUser(int $idUserTarget, int $usuarioLogadoId): array {
        if ($idUserTarget === $usuarioLogadoId) {
            return [
                'status' => false,
                'codigo' => 400,
                'mensagem' => 'Você não pode excluir a sua própria conta de administrador.'
            ];
        }

        try {
            $excluido = $this->userRepository->excluirUser($idUserTarget);
            return [
                'status' => $excluido,
                'codigo' => $excluido ? 200 : 500,
                'mensagem' => $excluido ? 'Usuário excluído com sucesso.' : 'Erro ao excluir usuário.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'codigo' => 500,
                'mensagem' => 'Erro interno ao excluir usuário.'
            ];
        }
    }
}