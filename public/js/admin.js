document.addEventListener('DOMContentLoaded', () => {
    const API_BASE = '/Agenda-Almoco/api';
    let currentToken = localStorage.getItem('jwt_token') || null;
    let isAgendaLocked = false;
    let currentAdminId = null;

    // Elementos DOM
    const adminUserName = document.getElementById('admin-user-name');
    const logoutBtn = document.getElementById('logout-btn');
    const agendaStatusTag = document.getElementById('agenda-status-tag');
    const toggleLockBtn = document.getElementById('toggle-lock-btn');
    const clearAgendaBtn = document.getElementById('clear-agenda-btn');
    const exportCsvBtn = document.getElementById('export-csv-btn');
    const formNovoHorario = document.getElementById('form-novo-horario');
    const formNovoUser = document.getElementById('form-novo-user');
    const horariosTableBody = document.getElementById('horarios-table-body');
    const usersTableBody = document.getElementById('users-table-body');

    // Elementos dos Modais de Edição
    const modalEditarUser = document.getElementById('modal-editar-user');
    const formEditarUser = document.getElementById('form-editar-user');
    const closeModalEditUser = document.getElementById('close-modal-edit-user');
    const cancelModalEditUser = document.getElementById('cancel-modal-edit-user');

    const modalEditarHorario = document.getElementById('modal-editar-horario');
    const formEditarHorario = document.getElementById('form-editar-horario');
    const closeModalEditHorario = document.getElementById('close-modal-edit-horario');
    const cancelModalEditHorario = document.getElementById('cancel-modal-edit-horario');

    // Validação Inicial de Acesso Admin
    initAdminPage();

    async function initAdminPage() {
        if (!currentToken) {
            alert('Acesso negado. Por favor, faça login como Administrador.');
            window.location.href = 'index.html';
            return;
        }

        try {
            const res = await fetch(`${API_BASE}/checar-sessao`, {
                headers: { 'Authorization': `Bearer ${currentToken}` }
            });
            const data = await res.json();

            if (res.status !== 200 || !data.logado || (data.usuario && data.usuario.permissao_user !== 'admin')) {
                alert('Acesso restrito! Seu usuário não possui permissão de Administrador.');
                window.location.href = 'index.html';
                return;
            }

            currentAdminId = parseInt(data.usuario.id_user);
            adminUserName.textContent = data.usuario.nome_user;

            // Carrega dados do painel
            await carregarStatusEHorarios();
            await carregarListaUsuarios();
        } catch (err) {
            alert('Erro ao conectar ao servidor. Redirecionando...');
            window.location.href = 'index.html';
        }
    }

    // Carrega o status de bloqueio da agenda e a tabela de horários
    async function carregarStatusEHorarios() {
        try {
            const res = await fetch(`${API_BASE}/horarios`);
            const data = await res.json();

            isAgendaLocked = data.agenda_bloqueada === true;
            atualizarUIBloqueio();

            const horarios = data.dados || [];

            if (horarios.length === 0) {
                horariosTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #64748b;">Nenhum horário cadastrado.</td></tr>`;
                return;
            }

            horariosTableBody.innerHTML = '';
            horarios.forEach(h => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${h.id}</td>
                    <td><strong>${escapeHtml(h.horario)}</strong></td>
                    <td>${h.limite} vagas</td>
                    <td>${h.total_ocupado} ocupante(s)</td>
                    <td>
                        <div style="display: flex; gap: 6px;">
                            <button class="btn btn-secondary btn-edit-horario" data-id="${h.id}" data-horario="${escapeHtml(h.horario)}" style="padding: 4px 8px; font-size: 0.8rem;">✏️ Editar</button>
                            <button class="btn btn-danger btn-del-horario" data-id="${h.id}" style="padding: 4px 8px; font-size: 0.8rem;">🗑️ Excluir</button>
                        </div>
                    </td>
                `;
                horariosTableBody.appendChild(tr);
            });

            // Eventos dos botões de editar e excluir horários
            document.querySelectorAll('.btn-edit-horario').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.target.getAttribute('data-id');
                    const horStr = e.target.getAttribute('data-horario');
                    const partes = horStr.split('-').map(s => s.trim());
                    
                    document.getElementById('edit-horario-id').value = id;
                    document.getElementById('edit-horario-inicio').value = partes[0] || '';
                    document.getElementById('edit-horario-fim').value = partes[1] || '';
                    modalEditarHorario.classList.add('active');
                });
            });

            document.querySelectorAll('.btn-del-horario').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const id = e.target.getAttribute('data-id');
                    if (!confirm('Deseja realmente excluir este horário e todos os agendamentos associados?')) return;

                    await excluirHorario(id);
                });
            });

        } catch (err) {
            console.error('Erro ao carregar horários:', err);
            horariosTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: red;">Erro ao carregar horários.</td></tr>`;
        }
    }

    function atualizarUIBloqueio() {
        if (isAgendaLocked) {
            agendaStatusTag.textContent = 'BLOQUEADA';
            agendaStatusTag.className = 'status-tag locked';
            toggleLockBtn.textContent = 'Liberar Agenda';
            toggleLockBtn.className = 'btn btn-primary';
        } else {
            agendaStatusTag.textContent = 'LIBERADA';
            agendaStatusTag.className = 'status-tag unlocked';
            toggleLockBtn.textContent = 'Bloquear Agenda';
            toggleLockBtn.className = 'btn btn-warning';
        }
    }

    // Alternar Bloqueio da Agenda
    toggleLockBtn.addEventListener('click', async () => {
        const acao = isAgendaLocked ? 'desbloquear' : 'bloquear';
        if (!confirm(`Tem certeza que deseja ${acao} a agenda pública?`)) return;

        try {
            const res = await fetch(`${API_BASE}/configuracoes/bloquear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ bloquear: !isAgendaLocked })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 200) {
                isAgendaLocked = data.agenda_bloqueada;
                atualizarUIBloqueio();
            }
        } catch (err) {
            alert('Erro ao alterar status de bloqueio.');
        }
    });

    // Limpar Agenda
    clearAgendaBtn.addEventListener('click', async () => {
        if (!confirm('ATENÇÃO: Deseja realmente APAGAR TODOS os agendamentos de almoço cadastrados?')) return;

        try {
            const res = await fetch(`${API_BASE}/limpar`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${currentToken}` }
            });
            const data = await res.json();
            alert(data.mensagem);
            await carregarStatusEHorarios();
        } catch (err) {
            alert('Erro ao limpar a agenda.');
        }
    });

    // Exportar CSV
    exportCsvBtn.addEventListener('click', () => {
        window.location.href = `${API_BASE}/exportar?token=${encodeURIComponent(currentToken)}`;
    });

    // Cadastrar Novo Horário
    formNovoHorario.addEventListener('submit', async (e) => {
        e.preventDefault();
        const inicio = document.getElementById('horario-inicio').value;
        const fim = document.getElementById('horario-fim').value;

        if (!inicio || !fim) {
            alert('Preencha os campos de início e fim.');
            return;
        }

        try {
            const res = await fetch(`${API_BASE}/horarios/criar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ horario_inicio: inicio, horario_fim: fim })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 201) {
                formNovoHorario.reset();
                await carregarStatusEHorarios();
            }
        } catch (err) {
            alert('Erro ao cadastrar novo horário.');
        }
    });

    // Salvar Edição de Horário
    formEditarHorario.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edit-horario-id').value;
        const inicio = document.getElementById('edit-horario-inicio').value;
        const fim = document.getElementById('edit-horario-fim').value;

        try {
            const res = await fetch(`${API_BASE}/horarios/atualizar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ id_horario: id, horario_inicio: inicio, horario_fim: fim })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 200) {
                modalEditarHorario.classList.remove('active');
                await carregarStatusEHorarios();
            }
        } catch (err) {
            alert('Erro ao atualizar horário.');
        }
    });

    async function excluirHorario(idHorario) {
        try {
            const res = await fetch(`${API_BASE}/horarios/excluir`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ id_horario: idHorario })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 200) {
                await carregarStatusEHorarios();
            }
        } catch (err) {
            alert('Erro ao excluir horário.');
        }
    }

    // Cadastrar Novo Usuário
    formNovoUser.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nome = document.getElementById('novo-nome-user').value.trim();
        const senha = document.getElementById('nova-senha-user').value.trim();
        const permissao = document.getElementById('nova-permissao-user').value;

        try {
            const res = await fetch(`${API_BASE}/criar-usuario`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ nome_user: nome, senha_user: senha, permissao_user: permissao })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 201) {
                formNovoUser.reset();
                await carregarListaUsuarios();
            }
        } catch (err) {
            alert('Erro ao criar usuário.');
        }
    });

    // Carregar Lista de Usuários
    async function carregarListaUsuarios() {
        try {
            const res = await fetch(`${API_BASE}/usuarios`, {
                headers: { 'Authorization': `Bearer ${currentToken}` }
            });

            const data = await res.json();

            if (res.status !== 200 || !data.dados) {
                usersTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">Erro ao carregar usuários.</td></tr>`;
                return;
            }

            usersTableBody.innerHTML = '';
            data.dados.forEach(user => {
                const tr = document.createElement('tr');
                const isSelf = parseInt(user.id_user) === currentAdminId;
                
                const is2FA = user.is_2fa_enabled == 1 ? '<span style="color: #2ecc71; font-weight: bold;">Ativo 🔒</span>' : '<span style="color: #94a3b8;">Inativo</span>';
                const dataFormatada = user.criado_em ? new Date(user.criado_em).toLocaleDateString('pt-BR') : '-';

                const disabledAttr = isSelf ? 'disabled' : '';
                const selfBadge = isSelf ? '<span style="font-size: 0.75rem; color: #64748b; margin-left: 4px;">(Sua conta)</span>' : '';

                tr.innerHTML = `
                    <td>${user.id_user}</td>
                    <td><strong>${escapeHtml(user.nome_user)}</strong> ${selfBadge}</td>
                    <td>
                        <select id="perm-select-${user.id_user}" ${disabledAttr}>
                            <option value="colaborador" ${user.permissao_user === 'colaborador' ? 'selected' : ''}>Colaborador</option>
                            <option value="admin" ${user.permissao_user === 'admin' ? 'selected' : ''}>Administrador</option>
                        </select>
                    </td>
                    <td>${is2FA}</td>
                    <td>${dataFormatada}</td>
                    <td>
                        <div style="display: flex; gap: 6px;">
                            ${!isSelf ? `<button class="btn btn-primary btn-salvar-perm" data-id="${user.id_user}" style="padding: 4px 8px; font-size: 0.8rem;">Salvar Cargo</button>` : ''}
                            <button class="btn btn-secondary btn-edit-user" data-id="${user.id_user}" data-nome="${escapeHtml(user.nome_user)}" data-perm="${user.permissao_user}" style="padding: 4px 8px; font-size: 0.8rem;">✏️ Editar</button>
                            ${!isSelf ? `<button class="btn btn-danger btn-del-user" data-id="${user.id_user}" style="padding: 4px 8px; font-size: 0.8rem;">🗑️ Excluir</button>` : ''}
                        </div>
                    </td>
                `;

                usersTableBody.appendChild(tr);
            });

            // Eventos dos botões de salvar cargo
            document.querySelectorAll('.btn-salvar-perm').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const idUser = e.target.getAttribute('data-id');
                    const selectEl = document.getElementById(`perm-select-${idUser}`);
                    const novaPermissao = selectEl.value;

                    await atualizarPermissaoUser(idUser, novaPermissao);
                });
            });

            // Eventos de Editar Usuário
            document.querySelectorAll('.btn-edit-user').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.target.getAttribute('data-id');
                    const nome = e.target.getAttribute('data-nome');
                    const perm = e.target.getAttribute('data-perm');

                    document.getElementById('edit-user-id').value = id;
                    document.getElementById('edit-nome-user').value = nome;
                    document.getElementById('edit-senha-user').value = '';
                    document.getElementById('edit-permissao-user').value = perm;

                    // Se for ele mesmo, desabilita a troca de permissão no modal
                    const editPermSelect = document.getElementById('edit-permissao-user');
                    if (parseInt(id) === currentAdminId) {
                        editPermSelect.disabled = true;
                    } else {
                        editPermSelect.disabled = false;
                    }

                    modalEditarUser.classList.add('active');
                });
            });

            // Eventos de Excluir Usuário
            document.querySelectorAll('.btn-del-user').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const idUser = e.target.getAttribute('data-id');
                    if (!confirm('Tem certeza que deseja excluir este usuário?')) return;

                    await excluirUsuario(idUser);
                });
            });

        } catch (err) {
            usersTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">Erro ao conectar com o servidor.</td></tr>`;
        }
    }

    // Salvar Edição de Usuário
    formEditarUser.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edit-user-id').value;
        const nome = document.getElementById('edit-nome-user').value.trim();
        const senha = document.getElementById('edit-senha-user').value.trim();
        const permissao = document.getElementById('edit-permissao-user').value;

        try {
            const res = await fetch(`${API_BASE}/usuarios/atualizar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ id_user: id, nome_user: nome, senha_user: senha, permissao_user: permissao })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 200) {
                modalEditarUser.classList.remove('active');
                await carregarListaUsuarios();
            }
        } catch (err) {
            alert('Erro ao atualizar usuário.');
        }
    });

    async function atualizarPermissaoUser(idUser, novaPermissao) {
        try {
            const res = await fetch(`${API_BASE}/usuarios/permissao`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ id_user: idUser, permissao_user: novaPermissao })
            });

            const data = await res.json();
            alert(data.mensagem);
            if (res.status === 200) {
                await carregarListaUsuarios();
            }
        } catch (err) {
            alert('Erro ao atualizar permissão do usuário.');
        }
    }

    async function excluirUsuario(idUser) {
        try {
            const res = await fetch(`${API_BASE}/usuarios/excluir`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`
                },
                body: JSON.stringify({ id_user: idUser })
            });

            const data = await res.json();
            alert(data.mensagem);

            if (res.status === 200) {
                await carregarListaUsuarios();
            }
        } catch (err) {
            alert('Erro ao excluir usuário.');
        }
    }

    // Fechar modais
    if (closeModalEditUser) closeModalEditUser.addEventListener('click', () => modalEditarUser.classList.remove('active'));
    if (cancelModalEditUser) cancelModalEditUser.addEventListener('click', () => modalEditarUser.classList.remove('active'));

    if (closeModalEditHorario) closeModalEditHorario.addEventListener('click', () => modalEditarHorario.classList.remove('active'));
    if (cancelModalEditHorario) cancelModalEditHorario.addEventListener('click', () => modalEditarHorario.classList.remove('active'));

    // Logout
    logoutBtn.addEventListener('click', () => {
        localStorage.removeItem('jwt_token');
        window.location.href = 'index.html';
    });

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
});
