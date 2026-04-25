</div> 
</div> 
<script>
    document.addEventListener('click', function(event) {
        var sidebar = document.getElementById('sidebar');
        var toggle = document.querySelector('.menu-toggle');
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });

    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Keluar Sistem?',
            text: 'Sesi Anda akan diakhiri.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FF3B30',
            cancelButtonColor: '#E5E5EA',
            cancelButtonText: '<span style="color:#1C1C1E; font-weight:bold;">Batal</span>',
            confirmButtonText: 'Ya, Keluar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../proses.php?action=logout';
            }
        })
    }
</script>
</body>
</html>