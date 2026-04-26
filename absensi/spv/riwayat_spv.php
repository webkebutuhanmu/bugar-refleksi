<?php include '../header.php'; ?>

<div class="card slide-up">
    <div class="card-title" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span><i class="fas fa-building" style="color:var(--primary)"></i> Manajemen Cabang & Staf</span>
        <button onclick="tambahCabang()" style="background:var(--primary); color:white; border:none; padding:10px 18px; border-radius:12px; font-size:13px; font-weight:bold; cursor:pointer; box-shadow:0 4px 12px rgba(88,86,214,0.25); transition:0.3s;">
            <i class="fas fa-plus"></i> Tambah Cabang
        </button>
    </div>
    
    <?php
    $branches = $pdo->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
    if(!$branches): ?>
        <div style="text-align:center; padding:40px 20px; color:#8E8E93;">
            <i class="fas fa-store-slash" style="font-size:40px; opacity:0.3; margin-bottom:15px; display:block;"></i>
            Belum ada data cabang. Silakan tambah cabang baru.
        </div>
    <?php else: 
    foreach($branches as $b):
    ?>
    <div style="margin-bottom: 30px; border: 1px solid rgba(0,0,0,0.05); border-radius: 18px; overflow: hidden; background:white; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:center; background:#F9F9F9; padding:15px 20px; border-bottom:1px solid rgba(0,0,0,0.05); flex-wrap: wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; background:rgba(255,59,48,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-map-marker-alt" style="color:var(--danger); font-size:16px;"></i>
                </div>
                <h3 style="font-size:15px; margin:0; color:#1C1C1E; font-weight:800;"><?= htmlspecialchars($b['nama_cabang']) ?></h3>
            </div>
            <div style="display:flex; gap:8px;">
                <button onclick="editCabang(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama_cabang']) ?>')" style="background:white; border:1px solid #E5E5EA; padding:8px 12px; border-radius:10px; font-size:12px; color:#1C1C1E; cursor:pointer; font-weight:600;"><i class="fas fa-edit"></i></button>
                <button onclick="hapusData('cabang', <?= $b['id'] ?>)" style="background:white; border:1px solid #FFE5E5; padding:8px 12px; border-radius:10px; font-size:12px; color:var(--danger); cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                <button onclick="tambahStaf(<?= $b['id'] ?>)" style="background:var(--success); color:white; border:none; padding:8px 15px; border-radius:10px; font-size:12px; font-weight:bold; cursor:pointer;"><i class="fas fa-user-plus"></i> Staf</button>
            </div>
        </div>

        <div class="table-res" style="padding:10px;">
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #F2F2F7;">
                        <th style="padding:12px; font-size:11px; color:#8E8E93; text-transform:uppercase; letter-spacing:0.5px;">Username</th>
                        <th style="padding:12px; font-size:11px; color:#8E8E93; text-transform:uppercase; letter-spacing:0.5px;">Nama Lengkap</th>
                        <th style="padding:12px; font-size:11px; color:#8E8E93; text-transform:uppercase; letter-spacing:0.5px;">Role</th>
                        <th style="padding:12px; font-size:11px; color:#8E8E93; text-transform:uppercase; letter-spacing:0.5px; text-align:center;">Skor</th>
                        <th style="padding:12px; font-size:11px; color:#8E8E93; text-transform:uppercase; letter-spacing:0.5px; text-align:right;">Opsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmtStaf = $pdo->prepare("SELECT * FROM users WHERE branch_id = ? AND role != 'owner' ORDER BY role, nama_lengkap");
                    $stmtStaf->execute([$b['id']]);
                    $staf = $stmtStaf->fetchAll();
                    if(!$staf):
                    ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px; color:#C7C7CC; font-size:13px; font-style:italic;">Belum ada staf di cabang ini.</td></tr>
                    <?php else: foreach($staf as $s): ?>
                    <tr style="border-bottom:1px solid #F9F9F9;">
                        <td style="padding:12px; font-size:13px; font-weight:600; color:#1C1C1E;">@<?= htmlspecialchars($s['username']) ?></td>
                        <td style="padding:12px; font-size:13px; color:#3A3A3C;"><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                        <td style="padding:12px;">
                            <span style="font-size:10px; background:#F2F2F7; padding:4px 8px; border-radius:6px; font-weight:800; color:#8E8E93; text-transform:uppercase;"><?= $s['role'] ?></span>
                        </td>
                        <td style="padding:12px; text-align:center;">
                            <b style="color:<?= $s['credit_score'] < 80 ? 'var(--danger)' : 'var(--success)' ?>; font-size:14px;"><?= $s['credit_score'] ?></b>
                        </td>
                        <td style="padding:12px; text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                <button onclick='editStaf(<?= json_encode($s) ?>)' style="background:#F2F2F7; color:#1C1C1E; border:none; width:32px; height:32px; border-radius:8px; cursor:pointer;"><i class="fas fa-edit" style="font-size:12px;"></i></button>
                                <button onclick="hapusData('staf', <?= $s['id'] ?>)" style="background:rgba(255,59,48,0.05); color:var(--danger); border:none; width:32px; height:32px; border-radius:8px; cursor:pointer;"><i class="fas fa-trash-alt" style="font-size:12px;"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php include '../footer.php'; ?>

<script>
// --- Fungsi Manajemen Cabang ---
function tambahCabang() {
    Swal.fire({
        title: 'Tambah Cabang',
        input: 'text',
        inputPlaceholder: 'Nama cabang baru...',
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        confirmButtonColor: '#5856D6',
        preConfirm: (nama) => { if (!nama) return Swal.showValidationMessage('Nama cabang wajib diisi!'); return nama; }
    }).then((res) => { if (res.isConfirmed) window.location.href = `../proses.php?action=add_branch&nama=${res.value}`; });
}

function editCabang(id, nama) {
    Swal.fire({
        title: 'Edit Cabang',
        input: 'text',
        inputValue: nama,
        showCancelButton: true,
        confirmButtonText: 'Update',
        confirmButtonColor: '#5856D6',
        preConfirm: (n) => { if (!n) return Swal.showValidationMessage('Nama cabang wajib diisi!'); return n; }
    }).then((res) => { if (res.isConfirmed) window.location.href = `../proses.php?action=edit_branch&id=${id}&nama=${res.value}`; });
}

// --- Fungsi MANAJEMEN STAF (Responsive Update) ---
function tambahStaf(branchId) {
    Swal.fire({
        title: '<div style="font-size:18px;font-weight:800;margin-bottom:5px;">Tambah Staf Baru</div>',
        html: `
            <div style="text-align:left; margin-top:15px;">
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">USERNAME LOGIN</label>
                <input id="swal-user" class="swal2-input" placeholder="Contoh: budi123" style="margin-top:6px;margin-bottom:15px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;">
                
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">NAMA LENGKAP</label>
                <input id="swal-nama" class="swal2-input" placeholder="Nama asli staf" style="margin-top:6px;margin-bottom:15px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;">
                
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">PASSWORD</label>
                <input id="swal-pass" type="password" class="swal2-input" placeholder="Minimal 6 karakter" style="margin-top:6px;margin-bottom:15px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;">
                
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">JABATAN / ROLE</label>
                <select id="swal-role" class="swal2-input" style="margin-top:6px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;-webkit-appearance:none;background:white;">
                    <option value="karyawan">Karyawan</option>
                    <option value="kasir">Kasir</option>
                    <option value="supervisor">Supervisor</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Simpan Staf',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#5856D6',
        cancelButtonColor: '#E5E5EA',
        buttonsStyling: true,
        focusConfirm: false,
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const nama = document.getElementById('swal-nama').value.trim();
            const pass = document.getElementById('swal-pass').value;
            const role = document.getElementById('swal-role').value;
            if(!user || !nama || !pass) return Swal.showValidationMessage('Semua kolom wajib diisi!');
            if(pass.length < 6) return Swal.showValidationMessage('Password minimal 6 karakter!');
            return { user, nama, pass, role };
        }
    }).then((res) => {
        if (res.isConfirmed) {
            const d = res.value;
            const f = document.createElement('form');
            f.method='POST'; f.action=`../proses.php?action=save_user&branch_id=${branchId}`;
            ['user','nama','pass','role'].forEach(k => {
                const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=d[k]; f.appendChild(i);
            });
            document.body.appendChild(f); f.submit();
        }
    });
}

function editStaf(user) {
    Swal.fire({
        title: '<div style="font-size:18px;font-weight:800;">Edit Profil Staf</div>',
        html: `
            <div style="text-align:left; margin-top:15px;">
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">NAMA LENGKAP</label>
                <input id="edit-nama" class="swal2-input" value="${user.nama_lengkap}" style="margin-top:6px;margin-bottom:15px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;">
                
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">CREDIT SCORE</label>
                <input id="edit-skor" type="number" class="swal2-input" value="${user.credit_score}" style="margin-top:6px;margin-bottom:15px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;">
                
                <label style="font-size:11px;font-weight:800;color:#8E8E93;margin-left:5px;letter-spacing:0.5px;">GANTI PASSWORD</label>
                <input id="edit-pass" type="password" class="swal2-input" placeholder="Kosongkan jika tidak diganti" style="margin-top:6px;height:48px;border-radius:14px;font-size:15px;width:100%;box-sizing:border-box;border:1.5px solid #E5E5EA;outline:none;">
                <p style="font-size:10px; color:#8E8E93; margin:5px 0 0 5px;">*Minimal 6 karakter jika ingin mengganti</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update Staf',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#34C759',
        cancelButtonColor: '#E5E5EA',
        preConfirm: () => {
            const nama = document.getElementById('edit-nama').value.trim();
            const skor = document.getElementById('edit-skor').value;
            const pass = document.getElementById('edit-pass').value;
            if(!nama || !skor) return Swal.showValidationMessage('Nama dan Skor wajib diisi!');
            if(pass && pass.length < 6) return Swal.showValidationMessage('Password baru minimal 6 karakter!');
            return { id: user.id, nama, skor, pass };
        }
    }).then((res) => {
        if (res.isConfirmed) {
            const d = res.value;
            window.location.href = `../proses.php?action=edit_user&id=${d.id}&nama=${d.nama}&skor=${d.skor}&pass=${d.pass}`;
        }
    });
}

function hapusData(tipe, id) {
    const isStaf = (tipe === 'staf');
    Swal.fire({
        title: isStaf ? 'Hapus Staf?' : 'Hapus Cabang?',
        text: isStaf ? 'Data akun dan riwayat staf ini akan dihapus permanen.' : 'Menghapus cabang akan berdampak pada data staf di dalamnya.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF3B30',
        cancelButtonColor: '#E5E5EA',
        confirmButtonText: 'Ya, Hapus'
    }).then((r) => {
        if (r.isConfirmed) window.location.href = `../proses.php?action=${isStaf ? 'del_staf' : 'del_cabang'}&id=${id}`;
    });
}
</script>