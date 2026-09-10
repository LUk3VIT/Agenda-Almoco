// API Base URL
const API_URL = '/Agenda-Almoco/api';

// Função para alternar (expandir/recolher) um bloco de horário
function toggleBlock(blockId) {
    const block = document.getElementById(blockId);
    if (!block) return;
    
    const icon = block.querySelector('.expand-icon');
    block.classList.toggle('expanded');

    if (icon) {
        if (block.classList.contains('expanded')) {
            icon.textContent = 'expand_less'; 
        } else {
            icon.textContent = 'expand_more';
        }
    }
}

// Armazena a grade de horários, dados do usuário logado e ID pendente para 2FA
let horariosData = [];
let usuarioLogado = null;
let idUserPendente2FA = null;
let isAgendaBloqueada = false;

// Retorna os cabeçalhos HTTP com o Token JWT do localStorage se existir
function getAuthHeaders(extraHeaders = {}) {
    const token = localStorage.getItem('jwt_token');
    const headers = { ...extraHeaders };
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    return headers;
}

// Função para buscar os horários da API PHP e renderizar na tela
async function carregarHorarios() {
    const container = document.getElementById('schedule-container');
    const selectHorario = document.getElementById('select-horario');

    try {
        const response = await fetch(`${API_URL}/horarios`, {
            headers: getAuthHeaders()
        });
        const json = await response.json();

        if (json.status !== 'sucesso') {
            container.innerHTML = `<div class="empty-state">Erro ao carregar os horários.</div>`;
            return;
        }

        isAgendaBloqueada = json.agenda_bloqueada === true;
        horariosData = json.dados || [];

        let htmlBanner = '';
        if (isAgendaBloqueada) {
            htmlBanner = `
                <div class="lock-banner">
                    <span class="material-icons">lock</span>
                    <span>A agenda está temporariamente bloqueada para novos agendamentos pela administração.</span>
                </div>
            `;
        }

        if (horariosData.length === 0) {
            container.innerHTML = htmlBanner + `<div class="empty-state">Nenhum horário cadastrado no sistema.</div>`;
            return;
        }

        // Renderizar os blocos no HTML
        const htmlBlocos = horariosData.map((bloco, index) => {
            const isFirst = index === 0;
            const expandedClass = isFirst ? 'expanded' : '';
            const iconName = isFirst ? 'expand_less' : 'expand_more';

            let colaboradoresHtml = (bloco.colaboradores || []).map(colab => `
                <li class="colaborador occuped">
                    <span class="status-dot"></span>
                    <span class="name">${escaparHtml(colab.nome)}</span>
                </li>
            `).join('');

            const disponiveis = bloco.limite - bloco.total_ocupado;
            for (let i = 0; i < disponiveis; i++) {
                colaboradoresHtml += `
                    <li class="colaborador available">
                        <span class="status-dot"></span>
                        <div class="placeholder-bar"></div>
                    </li>
                `;
            }

            return `
                <div class="schedule-block ${expandedClass}" id="block-${bloco.id}">
                    <div class="block-header" onclick="toggleBlock('block-${bloco.id}')">
                        <span class="time">Horário: ${bloco.horario}</span>
                        <div class="header-right">
                            <span class="count">Colaboradores <span id="count-${bloco.id}">${bloco.total_ocupado}/${bloco.limite}</span></span>
                            <span class="material-icons expand-icon">${iconName}</span>
                        </div>
                    </div>
                    <div class="block-details">
                        <ul class="colaborador-list">
                            ${colaboradoresHtml}
                        </ul>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = htmlBanner + htmlBlocos;

        // Preencher o select do Modal com horários que possuem vagas disponíveis
        selectHorario.innerHTML = `<option value="">Selecione um horário...</option>` +
            horariosData
                .filter(b => b.total_ocupado < b.limite)
                .map(b => `<option value="${b.id}">${b.horario} (${b.limite - b.total_ocupado} vaga(s) livre(s))</option>`)
                .join('');

    } catch (err) {
        console.error("Erro na requisição:", err);
        container.innerHTML = `<div class="empty-state">Erro de conexão com a API PHP/MySQL.</div>`;
    }
}

// Função utilitária para evitar XSS
function escaparHtml(text) {
    const div = document.createElement('div');
    div.innerText = text || '';
    return div.innerHTML;
}

// --- Gerenciamento da Sessão JWT e Cabeçalho do Usuário ---
async function checarSessao() {
    const userHeaderArea = document.getElementById('user-header-area');
    const token = localStorage.getItem('jwt_token');

    if (!token) {
        usuarioLogado = null;
        renderizarAreaDeslogada(userHeaderArea);
        return;
    }

    try {
        const response = await fetch(`${API_URL}/checar-sessao`, {
            headers: getAuthHeaders()
        });
        const json = await response.json();

        if (response.ok && json.logado && json.usuario) {
            usuarioLogado = json.usuario;
            renderizarAreaLogada(userHeaderArea, usuarioLogado);
        } else {
            // Se o token for inválido/expirado, remove do localStorage
            localStorage.removeItem('jwt_token');
            usuarioLogado = null;
            renderizarAreaDeslogada(userHeaderArea);
        }
    } catch (err) {
        console.error("Erro ao checar sessão JWT:", err);
        usuarioLogado = null;
        renderizarAreaDeslogada(userHeaderArea);
    }
}

function renderizarAreaLogada(container, usuario) {
    const isAdmin = usuario.permissao_user === 'admin';
    const adminLinkHtml = isAdmin ? `
        <a href="admin.html" class="admin-link-btn" title="Painel Administrativo">
            <span class="material-icons">admin_panel_settings</span>
            <span>Admin</span>
        </a>
    ` : '';

    container.innerHTML = `
        <div class="user-info">
            ${adminLinkHtml}
            <span class="user-name" title="${escaparHtml(usuario.nome_user)}">${escaparHtml(usuario.nome_user)}</span>
            <button class="logout-btn" id="logout-btn" title="Sair">
                <span class="material-icons">logout</span>
            </button>
        </div>
    `;
    document.getElementById('logout-btn').addEventListener('click', realizarLogout);

    const exportBtn = document.getElementById('export-excel-btn');
    if (exportBtn) {
        exportBtn.style.display = isAdmin ? 'flex' : 'none';
    }
}

function renderizarAreaDeslogada(container) {
    container.innerHTML = `
        <button class="user-btn" id="open-login-modal-btn">
            <span class="material-icons">account_circle</span>
            <span>Entrar</span>
        </button>
    `;
    document.getElementById('open-login-modal-btn').addEventListener('click', abrirLoginModal);

    const exportBtn = document.getElementById('export-excel-btn');
    if (exportBtn) {
        exportBtn.style.display = 'none';
    }
}


function realizarLogout() {
    localStorage.removeItem('jwt_token');
    usuarioLogado = null;
    checarSessao();
}

// --- Lógica do Modal de Agendamento ---
const modalAgendamento = document.getElementById('modal-agendamento');
const openModalBtn = document.getElementById('open-modal-btn');
const closeModalBtn = document.getElementById('close-modal-btn');
const cancelModalBtn = document.getElementById('cancel-modal-btn');
const formAgendamento = document.getElementById('form-agendamento');

function abrirAgendamentoModal() {
    if (isAgendaBloqueada) {
        alert("A agenda está temporariamente bloqueada para novos agendamentos pela administração.");
        return;
    }

    const inputNome = document.getElementById('input-nome');
    if (usuarioLogado) {
        inputNome.value = usuarioLogado.nome_user;
        inputNome.readOnly = true;
        inputNome.classList.add('readonly-input');
    } else {
        inputNome.value = '';
        inputNome.readOnly = false;
        inputNome.classList.remove('readonly-input');
    }

    modalAgendamento.classList.add('active');
}

function fecharAgendamentoModal() {
    modalAgendamento.classList.remove('active');
    formAgendamento.reset();
}

openModalBtn.addEventListener('click', abrirAgendamentoModal);
closeModalBtn.addEventListener('click', fecharAgendamentoModal);
cancelModalBtn.addEventListener('click', fecharAgendamentoModal);

modalAgendamento.addEventListener('click', (e) => {
    if (e.target === modalAgendamento) fecharAgendamentoModal();
});

formAgendamento.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (isAgendaBloqueada) {
        alert("A agenda está bloqueada para novos agendamentos.");
        return;
    }

    const idHorario = document.getElementById('select-horario').value;
    const inputNome = document.getElementById('input-nome').value.trim();

    if (!idHorario) {
        alert('Por favor, selecione um horário.');
        return;
    }

    if (!usuarioLogado && !inputNome) {
        alert('Por favor, digite seu nome completo.');
        return;
    }

    const payload = {
        id_horario: parseInt(idHorario)
    };

    if (!usuarioLogado) {
        payload.nome_colaborador = inputNome;
    }

    try {
        const response = await fetch(`${API_URL}/agendar`, {
            method: 'POST',
            headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload)
        });

        const json = await response.json();

        if (response.ok && json.status === 'sucesso') {
            alert(json.mensagem || 'Agendamento realizado com sucesso!');
            fecharAgendamentoModal();
            carregarHorarios();
        } else {
            alert(json.mensagem || 'Erro ao realizar agendamento.');
        }
    } catch (err) {
        console.error("Erro ao agendar:", err);
        alert("Erro de conexão ao salvar agendamento.");
    }
});


// --- Lógica do Modal de Login ---
const modalLogin = document.getElementById('modal-login');
const closeLoginModalBtn = document.getElementById('close-login-modal-btn');
const cancelLoginModalBtn = document.getElementById('cancel-login-modal-btn');
const formLogin = document.getElementById('form-login');

function abrirLoginModal() {
    modalLogin.classList.add('active');
}

function fecharLoginModal() {
    modalLogin.classList.remove('active');
    formLogin.reset();
}

closeLoginModalBtn.addEventListener('click', fecharLoginModal);
cancelLoginModalBtn.addEventListener('click', fecharLoginModal);

modalLogin.addEventListener('click', (e) => {
    if (e.target === modalLogin) fecharLoginModal();
});

formLogin.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nomeUser = document.getElementById('login-nome').value.trim();
    const senha = document.getElementById('login-senha').value;

    if (!nomeUser || !senha) {
        alert('Por favor, informe usuário e senha.');
        return;
    }

    try {
        const response = await fetch(`${API_URL}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome_user: nomeUser, senha: senha })
        });

        const json = await response.json();

        if (response.ok && json.status) {
            fecharLoginModal();

            // Se for o PRIMEIRO LOGIN do usuário (onboarding 2FA obrigatório)
            if (json.requer_setup_2fa && json.temp_2fa_token) {
                temp2faTokenPendente = json.temp_2fa_token;
                abrirSetup2FAModal(json.qr_code_url, json.otpauth_uri);
            } else if (json.requer_2fa) {
                // Se o 2FA já está ativo na conta (segundo login em diante)
                idUserPendente2FA = json.id_user;
                abrir2FAModal();
            } else if (json.token) {
                // Se não exige 2FA, salva o JWT e atualiza a sessão
                localStorage.setItem('jwt_token', json.token);
                await checarSessao();
            }
        } else {
            alert(json.mensagem || 'Login Incorreto.');
        }
    } catch (err) {
        console.error("Erro ao realizar login:", err);
        alert("Erro de conexão ao realizar login.");
    }
});

// --- Lógica do Modal de Onboarding / Setup 2FA Mandatório ---
let temp2faTokenPendente = null;
const modalSetup2FA = document.getElementById('modal-setup-2fa');
const closeSetupModalBtn = document.getElementById('close-setup-modal-btn');
const cancelSetupModalBtn = document.getElementById('cancel-setup-2fa-btn');
const formSetup2FA = document.getElementById('form-setup-2fa');

function abrirSetup2FAModal(qrCodeUrl, otpauthUri = null) {
    const target = document.getElementById('qr-code-target');
    const qrCodeImg = document.getElementById('qr-code-img');

    if (target) {
        target.innerHTML = '';
        const uriToRender = otpauthUri || qrCodeUrl;
        if (typeof QRCode !== 'undefined' && uriToRender) {
            new QRCode(target, {
                text: uriToRender,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            if (qrCodeImg) qrCodeImg.style.display = 'none';
        } else if (qrCodeImg && qrCodeUrl) {
            qrCodeImg.src = qrCodeUrl;
            qrCodeImg.style.display = 'block';
        }
    } else if (qrCodeImg && qrCodeUrl) {
        qrCodeImg.src = qrCodeUrl;
        qrCodeImg.style.display = 'block';
    }

    modalSetup2FA.classList.add('active');
    document.getElementById('input-setup-2fa-code').focus();
}

function fecharSetup2FAModal() {
    modalSetup2FA.classList.remove('active');
    if (formSetup2FA) formSetup2FA.reset();
    temp2faTokenPendente = null;
}

if (closeSetupModalBtn) closeSetupModalBtn.addEventListener('click', fecharSetup2FAModal);
if (cancelSetupModalBtn) cancelSetupModalBtn.addEventListener('click', fecharSetup2FAModal);

if (formSetup2FA) {
    formSetup2FA.addEventListener('submit', async (e) => {
        e.preventDefault();

        const codigo2FA = document.getElementById('input-setup-2fa-code').value.trim();

        if (!temp2faTokenPendente || !codigo2FA || codigo2FA.length !== 6) {
            alert('Por favor, digite o código de 6 dígitos do Microsoft Authenticator.');
            return;
        }

        try {
            const response = await fetch(`${API_URL}/confirmar-setup-2fa`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    temp_2fa_token: temp2faTokenPendente,
                    codigo_2fa: codigo2FA
                })
            });

            const json = await response.json();

            if (response.ok && json.status && json.token) {
                localStorage.setItem('jwt_token', json.token);
                fecharSetup2FAModal();
                alert(json.mensagem || 'Autenticação 2FA ativada com sucesso!');
                await checarSessao();
            } else {
                alert(json.mensagem || 'Código de 6 dígitos incorreto. Verifique no aplicativo.');
            }
        } catch (err) {
            console.error("Erro ao confirmar 2FA:", err);
            alert("Erro de conexão ao ativar 2FA.");
        }
    });
}

// --- Lógica do Modal de Validação 2FA (Microsoft Authenticator) ---
const modal2FA = document.getElementById('modal-2fa');
const close2FAModalBtn = document.getElementById('close-2fa-modal-btn');
const cancel2FAModalBtn = document.getElementById('cancel-2fa-modal-btn');
const form2FA = document.getElementById('form-2fa');

function abrir2FAModal() {
    modal2FA.classList.add('active');
    document.getElementById('input-2fa-code').focus();
}

function fechar2FAModal() {
    modal2FA.classList.remove('active');
    form2FA.reset();
    idUserPendente2FA = null;
}

close2FAModalBtn.addEventListener('click', fechar2FAModal);
cancel2FAModalBtn.addEventListener('click', fechar2FAModal);

modal2FA.addEventListener('click', (e) => {
    if (e.target === modal2FA) fechar2FAModal();
});

form2FA.addEventListener('submit', async (e) => {
    e.preventDefault();

    const codigo2FA = document.getElementById('input-2fa-code').value.trim();

    if (!idUserPendente2FA || !codigo2FA || codigo2FA.length !== 6) {
        alert('Por favor, digite o código de 6 dígitos completo.');
        return;
    }

    try {
        const response = await fetch(`${API_URL}/validar-2fa`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_user: idUserPendente2FA,
                codigo_2fa: codigo2FA
            })
        });

        const json = await response.json();

        if (response.ok && json.status && json.token) {
            localStorage.setItem('jwt_token', json.token);
            fechar2FAModal();
            await checarSessao();
        } else {
            alert(json.mensagem || 'Código 2FA incorreto ou expirado.');
        }
    } catch (err) {
        console.error("Erro ao validar 2FA:", err);
        alert("Erro de conexão ao validar 2FA.");
    }
});


// --- Lógica do Botão de Exportar Planilha Excel/CSV ---
const exportExcelBtn = document.getElementById('export-excel-btn');
if (exportExcelBtn) {
    exportExcelBtn.addEventListener('click', () => {
        if (!usuarioLogado) {
            alert("Por favor, faça login para baixar a planilha.");
            abrirLoginModal();
            return;
        }

        const token = localStorage.getItem('jwt_token');
        window.location.href = `${API_URL}/exportar?token=${encodeURIComponent(token)}`;
    });
}

// Inicialização da página
document.addEventListener('DOMContentLoaded', async () => {
    await checarSessao();
    await carregarHorarios();
});