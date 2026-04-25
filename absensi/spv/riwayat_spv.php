<?php include '../header.php'; ?>
<div class="card slide-up">
    <div class="card-title" style="justify-content: space-between;">
        <span><i class="fas fa-building" style="color:var(--primary)"></i> Manajemen Cabang & Staf</span>
        <button onclick="tambahCabang()" style="background:var(--primary); color:white; border:none; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:bold; cursor:pointer;"><i class="fas fa-plus"></i> Cabang</button>
    </div>
    
    <?php
    $branches = $pdo->query("SELECT * FROM branches")->fetchAll();
    foreach($branches as $b):
    ?>
    <div style="margin-bottom: 35px; border: 1px solid rgba(0,0,0,0.05); border-radius: 15px; overflow: hidden; background:white; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:center; background:#F9F9F9; padding:15px 20px; border-bottom:1px solid rgba(0,0,0,0.05);">
            <h3 style="font-size:15px; margin:0; color:#1C1C1E;"><i class="fas fa-map-marker-alt" style="color:var(--danger); margin-right:8px;"></i> <?= $b['nama_cabang'] ?></h3>
            <div style="display:flex; gap:10px;">
                <button onclick="editCabang(<?= $b['id'] ?>, '<?= $b['nama_cabang'] ?>')" style="background:none; border:none; color:var(--primary); cursor:pointer;"><i class="fas fa-edit"></i></button>
                <button onclick="hapusData('cabang', <?= $b['id'] ?>)" style="background:none; border:none; color:var(--danger); cursor:pointer;"><i class="fas fa-trash"></i></button>
                <button onclick="tambahStaf(<?= $b['id'] ?>)" style="background:var(--success); color:white; border:none; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;"><i class="fas fa-plus"></i> Staf</button>
            </div>
        </div>
        <div class="table-res" style="padding:10px;">
            <table>
                <thead><tr><th>Staf</th><th>Role</th><th>Absen</th><th>Skor</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php
                    $stmtU = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? AND role != 'owner'");
                    $stmtU->execute([$b['id']]);
                    while($u = $stmtU->fetch()):
                        $last = $pdo->query("SELECT status_kehadiran FROM attendance WHERE user_id = ".$u['id']." ORDER BY id DESC LIMIT 1")->fetch();
                    ?>
                    <tr>
                        <td><b style="color:#1C1C1E;"><?= $u['nama_lengkap'] ?></b><br><small style="color:#8E8E93;">@<?= $u['username'] ?></small></td>
                        <td><span style="font-size:10px; background:#F2F2F7; padding:4px 8px; border-radius:6px; text-transform:uppercase; font-weight:700; color:#8E8E93;"><?= $u['role'] ?></span></td>
                        <td>
                            <?php if($last): ?>
                                <span class="status-pill <?= $last['status_kehadiran'] == 'Tepat Waktu' ? 'pill-tepat' : 'pill-telat' ?>"><?= $last['status_kehadiran'] ?></span>
                            <?php else: ?><span style="color:#A1A1A6; font-size:12px;">-</span><?php endif; ?>
                        </td>
                        <td><b style="color: <?= $u['credit_score'] < 80 ? 'var(--danger)' : 'var(--success)' ?>"><?= $u['credit_score'] ?></b></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <button onclick="editStaf(<?= htmlspecialchars(json_encode($u)) ?>)" style="background:none; border:none; color:var(--primary); cursor:pointer;"><i class="fas fa-edit"></i></button>
                                <button onclick="hapusData('staf', <?= $u['id'] ?>)" style="background:none; border:none; color:var(--danger); cursor:pointer;"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function tambahCabang() {
    Swal.fire({ title: 'Tambah Cabang Baru', input: 'text', inputPlaceholder: 'Nama Cabang', showCancelButton: true, confirmButtonText: 'Simpan' }).then((res) => {
        if (res.isConfirmed && res.value) window.location.href = `../proses.php?action=add_branch&nama=${res.value}`;
    });
}
function editCabang(id, nama) {
    Swal.fire({ title: 'Edit Cabang', input: 'text', inputValue: nama, showCancelButton: true, confirmButtonText: 'Update' }).then((res) => {
        if (res.isConfirmed && res.value) window.location.href = `../proses.php?action=edit_branch&id=${id}&nama=${res.value}`;
    });
}
function tambahStaf(branchId) {
    Swal.fire({
        title: 'Tambah Staf',
        html: `<input id="swal-nama" class="swal2-input" placeholder="Nama Lengkap"><input id="swal-user" class="swal2-input" placeholder="Username"><input id="swal-pass" type="password" class="swal2-input" placeholder="Password"><select id="swal-role" class="swal2-input"><option value="karyawan">Karyawan / Terapis</option><option value="kasir">Kasir</option><option value="supervisor">Supervisor</option></select>`,
        focusConfirm: false, showCancelButton: true, confirmButtonText: 'Simpan',
        preConfirm: () => { return { nama: document.getElementById('swal-nama').value, user: document.getElementById('swal-user').value, pass: document.getElementById('swal-pass').value, role: document.getElementById('swal-role').value } }
    }).then((res) => {
        if (res.isConfirmed) {
            const d = res.value;
            const form = document.createElement('form'); form.method = 'POST'; form.action = `../proses.php?action=save_user&branch_id=${branchId}`;
            for(let key in d) { const input = document.createElement('input'); input.type = 'hidden'; input.name = key; input.value = d[key]; form.appendChild(input); }
            document.body.appendChild(form); form.submit();
        }
    });
}
function editStaf(user) {
    Swal.fire({
        title: 'Edit Staf',
        html: `<label style="font-size:12px;">Nama Lengkap</label><input id="edit-nama" class="swal2-input" value="${user.nama_lengkap}"><label style="font-size:12px;">Credit Score</label><input id="edit-skor" type="number" class="swal2-input" value="${user.credit_score}"><label style="font-size:12px;">Ganti Password (kosongkan jika tidak)</label><input id="edit-pass" type="password" class="swal2-input" placeholder="Password Baru">`,
        showCancelButton: true, confirmButtonText: 'Update',
        preConfirm: () => { return { id: user.id, nama: document.getElementById('edit-nama').value, skor: document.getElementById('edit-skor').value, pass: document.getElementById('edit-pass').value } }
    }).then((res) => {
        if (res.isConfirmed) { const d = res.value; window.location.href = `../proses.php?action=edit_user&id=${d.id}&nama=${d.nama}&skor=${d.skor}&pass=${d.pass}`; }
    });
}
function hapusData(tipe, id) {
    Swal.fire({ title: 'Yakin hapus?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#FF3B30', confirmButtonText: 'Ya, Hapus!' }).then((res) => {
        if (res.isConfirmed) window.location.href = `../proses.php?action=del_${tipe}&id=${id}`;
    });
}
</script>
<?php include '../footer.php'; ?>