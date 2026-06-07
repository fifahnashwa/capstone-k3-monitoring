@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-gray-900">Pengguna</h1>
    <button onclick="openModal()" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ Tambah pengguna</button>
</div>

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div id="loading" class="py-12 text-center text-sm text-gray-400">Memuat...</div>
    <table id="users-table" class="hidden w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                <th class="px-4 py-3 font-medium">Nama</th>
                <th class="px-4 py-3 font-medium">Email</th>
                <th class="px-4 py-3 font-medium">Role</th>
                <th class="px-4 py-3 font-medium">Dibuat</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody id="users-body" class="divide-y divide-gray-50"></tbody>
    </table>
</div>

{{-- MODAL --}}
<div id="modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 id="modal-title" class="text-base font-semibold text-gray-900 mb-4">Tambah pengguna</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Nama</label>
                <input type="text" id="f-name" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2" placeholder="Nama lengkap">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Email</label>
                <input type="email" id="f-email" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2" placeholder="email@example.com">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Password <span id="pw-hint" class="text-gray-300">(kosongkan jika tidak diubah)</span></label>
                <input type="password" id="f-password" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Role</label>
                <select id="f-role" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white">
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="hr">HR</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-5">
            <button onclick="saveUser()" class="flex-1 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Simpan</button>
            <button onclick="closeModal()" class="flex-1 py-2 text-sm border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50">Batal</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let editingId = null;
const roleBadge = {
    admin:   'bg-purple-50 text-purple-700',
    manager: 'bg-blue-50 text-blue-700',
    hr:      'bg-green-50 text-green-700',
};

async function loadUsers() {
    try {
        const data = await api('GET', '/api/users');
        renderUsers(data.data ?? data);
    } catch(e) { toast('Gagal memuat data', 'error'); }
}

function renderUsers(users) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('users-table').classList.remove('hidden');
    document.getElementById('users-body').innerHTML = users.map(u => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-800">${u.name}</td>
            <td class="px-4 py-3 text-gray-500">${u.email}</td>
            <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium ${roleBadge[u.role] ?? ''}">${u.role}</span>
            </td>
            <td class="px-4 py-3 text-gray-400 text-xs">${new Date(u.created_at).toLocaleDateString('id-ID')}</td>
            <td class="px-4 py-3 text-right">
                <button onclick='editUser(${JSON.stringify(u)})' class="text-xs text-blue-600 hover:underline mr-3">Edit</button>
                <button onclick="deleteUser(${u.id}, '${u.name}')" class="text-xs text-red-500 hover:underline">Hapus</button>
            </td>
        </tr>
    `).join('');
}

function openModal(user = null) {
    editingId = user?.id ?? null;
    document.getElementById('modal-title').textContent = user ? 'Edit pengguna' : 'Tambah pengguna';
    document.getElementById('f-name').value     = user?.name ?? '';
    document.getElementById('f-email').value    = user?.email ?? '';
    document.getElementById('f-password').value = '';
    document.getElementById('f-role').value     = user?.role ?? 'hr';
    document.getElementById('pw-hint').classList.toggle('hidden', !user);
    document.getElementById('modal').classList.remove('hidden');
}

function editUser(user) { openModal(user); }
function closeModal() { document.getElementById('modal').classList.add('hidden'); }

async function saveUser() {
    const body = {
        name:  document.getElementById('f-name').value.trim(),
        email: document.getElementById('f-email').value.trim(),
        role:  document.getElementById('f-role').value,
    };
    const pw = document.getElementById('f-password').value;
    if (pw) body.password = pw;
    if (!body.name || !body.email) { toast('Nama dan email wajib diisi', 'error'); return; }
    try {
        if (editingId) {
            await api('PUT', `/api/users/${editingId}`, body);
            toast('Pengguna diperbarui.');
        } else {
            if (!pw) { toast('Password wajib untuk pengguna baru', 'error'); return; }
            await api('POST', '/api/users', body);
            toast('Pengguna ditambahkan.');
        }
        closeModal();
        loadUsers();
    } catch(e) { toast(e.message ?? 'Gagal menyimpan', 'error'); }
}

async function deleteUser(id, name) {
    if (!confirm(`Hapus pengguna "${name}"?`)) return;
    try {
        await api('DELETE', `/api/users/${id}`);
        toast('Pengguna dihapus.');
        loadUsers();
    } catch(e) { toast(e.message ?? 'Gagal menghapus', 'error'); }
}

document.addEventListener('DOMContentLoaded', loadUsers);
</script>
@endpush